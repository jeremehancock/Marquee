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
     * @param bool                $force       re-download even when the poster is unchanged in Plex
     */
    public function import(array $sectionKeys, array $mediaTypes, bool $force = false): ImportResult
    {
        $result = new ImportResult();

        foreach ($this->plex->libraries() as $library) {
            if (!in_array($library->key, $sectionKeys, true)) {
                continue;
            }
            $this->libraries->sync($library);
            $this->importLibrary($library, $mediaTypes, $result, $force);
        }

        return $result;
    }

    /**
     * @param list<PlexMediaType> $mediaTypes
     */
    private function importLibrary(PlexLibrary $library, array $mediaTypes, ImportResult $result, bool $force): void
    {
        $wants = static fn (PlexMediaType $type): bool => in_array($type, $mediaTypes, true);

        if ($library->isMovie() && $wants(PlexMediaType::Movie)) {
            foreach ($this->plex->items($library) as $movie) {
                $this->importItem($movie, $result, $force);
            }
        }

        if ($library->isShow() && ($wants(PlexMediaType::Show) || $wants(PlexMediaType::Season))) {
            foreach ($this->plex->items($library) as $show) {
                if ($wants(PlexMediaType::Show)) {
                    $this->importItem($show, $result, $force);
                }
                if ($wants(PlexMediaType::Season)) {
                    foreach ($this->plex->seasons($show) as $season) {
                        $this->importItem($season, $result, $force);
                    }
                }
            }
        }

        if ($wants(PlexMediaType::Collection)) {
            foreach ($this->plex->collections($library) as $collection) {
                $this->importItem($collection, $result, $force);
            }
        }
    }

    private function importItem(PlexItem $item, ImportResult $result, bool $force): void
    {
        try {
            $category = $item->mediaType->category();
            $existing = $this->items->findByRatingKey($item->ratingKey);
            $thumb = $item->thumb ?? '';

            // Skip the poster download when Plex's artwork version is unchanged
            // since our last import and the local file still exists. Plex embeds
            // a version token in the thumb path, so an identical path means the
            // poster has not changed — no need to pull the image again.
            if (
                !$force
                && $existing !== null
                && $thumb !== ''
                && $existing->thumb === $thumb
                && $this->storage->exists($category, $existing->filename)
            ) {
                $result->recordSkipped();

                return;
            }

            $bytes = $this->plex->downloadPoster($item);

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
                sectionKey: $item->sectionKey,
                thumb: $thumb,
                addedAt: $item->addedAt ?? 0,
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
