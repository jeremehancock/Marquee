<?php

declare(strict_types=1);

namespace App\Plex\Import;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Database\PlexLibraryRepository;
use App\Plex\PlexClient;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Poster\PosterStorage;
use Throwable;

/**
 * Imports the current Plex poster for each selected item into the poster
 * library, recording the item→file mapping so re-imports overwrite in place.
 */
final class ImportService
{
    public function __construct(
        private readonly PlexClient $plex,
        private readonly PosterStorage $storage,
        private readonly PlexItemRepository $items,
        private readonly PlexLibraryRepository $libraries,
    ) {
    }

    /**
     * @param list<string>        $sectionKeys
     * @param list<PlexMediaType> $mediaTypes
     */
    public function import(array $sectionKeys, array $mediaTypes): ImportResult
    {
        $result = new ImportResult();

        foreach ($this->plex->libraries() as $library) {
            if (!in_array($library->key, $sectionKeys, true)) {
                continue;
            }
            $this->libraries->sync($library);
            $this->importLibrary($library, $mediaTypes, $result);
        }

        return $result;
    }

    /**
     * @param list<PlexMediaType> $mediaTypes
     */
    private function importLibrary(PlexLibrary $library, array $mediaTypes, ImportResult $result): void
    {
        $wants = static fn (PlexMediaType $type): bool => in_array($type, $mediaTypes, true);

        if ($library->isMovie() && $wants(PlexMediaType::Movie)) {
            foreach ($this->plex->items($library) as $movie) {
                $this->importItem($movie, $result);
            }
        }

        if ($library->isShow() && ($wants(PlexMediaType::Show) || $wants(PlexMediaType::Season))) {
            foreach ($this->plex->items($library) as $show) {
                if ($wants(PlexMediaType::Show)) {
                    $this->importItem($show, $result);
                }
                if ($wants(PlexMediaType::Season)) {
                    foreach ($this->plex->seasons($show) as $season) {
                        $this->importItem($season, $result);
                    }
                }
            }
        }

        if ($wants(PlexMediaType::Collection)) {
            foreach ($this->plex->collections($library) as $collection) {
                $this->importItem($collection, $result);
            }
        }
    }

    private function importItem(PlexItem $item, ImportResult $result): void
    {
        try {
            $bytes = $this->plex->downloadPoster($item);
            $category = $item->mediaType->category();
            $existing = $this->items->findByRatingKey($item->ratingKey);

            $temp = $this->writeTempFile($bytes);
            try {
                if ($existing !== null) {
                    $filename = $existing->filename;
                    $this->storage->replace($category, $filename, $temp);
                } else {
                    $filename = $this->storage->store($category, $this->deriveFilename($item, $bytes), $temp);
                }
            } finally {
                if (is_file($temp)) {
                    @unlink($temp);
                }
            }

            $this->items->upsert(new PlexItemRecord(
                ratingKey: $item->ratingKey,
                mediaType: $item->mediaType->value,
                category: $category->value,
                libraryTitle: $item->libraryTitle,
                title: $item->displayTitle(),
                filename: $filename,
                updatedAt: time(),
            ));

            $result->recordImported($category);
        } catch (Throwable) {
            $result->recordFailed();
        }
    }

    private function deriveFilename(PlexItem $item, string $bytes): string
    {
        $title = $item->displayTitle();
        if ($item->mediaType === PlexMediaType::Movie && $item->year !== null) {
            $title .= ' (' . $item->year . ')';
        }
        $title .= ' [' . $item->libraryTitle . ']';

        return $title . '.' . $this->extensionFor($bytes);
    }

    private function extensionFor(string $bytes): string
    {
        $info = @getimagesizefromstring($bytes);

        return match ($info === false ? null : $info[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
    }

    private function writeTempFile(string $bytes): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'marquee_plex_');
        if ($temp === false) {
            throw new \RuntimeException('Could not create a temporary file for the import.');
        }
        file_put_contents($temp, $bytes);

        return $temp;
    }
}
