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
use App\Poster\PosterCategory;
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

            // Correcting a bad match in Plex ("Fix Match") keeps the item's
            // rating key but replaces the work behind it. The stored filename is
            // what the gallery sorts by and what a search matches, so it is
            // brought back in step here — before the skip check reads it, and
            // before the download path writes through it.
            $filename = $existing === null ? '' : $this->renamedToMatch($existing, $item, $category);

            // Skip the poster download when Plex's artwork version is unchanged
            // since our last import and the local file still exists. Plex embeds
            // a version token in the thumb path, so an identical path means the
            // poster has not changed — no need to pull the image again.
            if (
                !$force
                && $existing !== null
                && $thumb !== ''
                && $existing->thumb === $thumb
                && $this->storage->exists($category, $filename)
            ) {
                $this->reconcileFacts($existing, $item, $filename);
                $result->recordSkipped();

                return;
            }

            $bytes = $this->plex->downloadPoster($item);

            $temp = $this->writeTempFile($bytes);
            try {
                if ($existing !== null) {
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
                title: $this->mergedTitle($existing, $item),
                filename: $filename,
                updatedAt: time(),
                sectionKey: $item->sectionKey,
                thumb: $thumb,
                addedAt: $item->addedAt ?? 0,
                year: $item->year ?? $existing?->year,
                seasonNumber: $item->seasonNumber,
                tmdbId: $item->tmdbId ?? $existing?->tmdbId,
            ));

            $result->recordImported($category);
        } catch (Throwable) {
            $result->recordFailed();
        }
    }

    /**
     * The filename the stored poster should have, renaming it when the item's
     * title or year has moved since the poster was imported.
     *
     * The name is derived from the item and the file's own extension, never
     * from poster bytes: this runs before the skip check has decided whether to
     * download anything, and the stored extension is the right one anyway — a
     * corrected match changes metadata, not the image already on disk.
     *
     * Deciding whether the name actually changed is left to the storage, which
     * owns sanitisation: the derived name here is raw, and only its sanitised
     * form is comparable to what is on disk. An unchanged name moves nothing.
     *
     * A rename that cannot be completed is not worth failing an item's import
     * over. The poster stays reachable under its existing name and its facts are
     * still corrected, which is strictly better than leaving both stale. That
     * also covers the poster whose file has gone missing: there is nothing to
     * move, the download path below recreates it under the old name, and the
     * next import — which now finds a file — renames it.
     */
    private function renamedToMatch(PlexItemRecord $existing, PlexItem $item, PosterCategory $category): string
    {
        $extension = pathinfo($existing->filename, PATHINFO_EXTENSION);
        $desired = $this->deriveBaseName($item) . '.' . $extension;

        try {
            return $this->storage->rename($category, $existing->filename, $desired);
        } catch (Throwable) {
            return $existing->filename;
        }
    }

    /**
     * Bring a skipped item's recorded facts back in line with Plex without
     * downloading the poster the skip check just decided was unchanged.
     *
     * A mapping records what the item was when it was imported, and a Plex item
     * does not hold still: a corrected match gives it a new title, year and
     * TMDB id under the same rating key. So each fact is taken from Plex
     * wherever Plex reports one, and kept wherever it does not — losing a known
     * fact to a server that has momentarily stopped reporting it is worse than
     * holding a stale one, and the next import that reports a value corrects it.
     *
     * Nothing is written unless something actually differs. That guard is what
     * keeps this affordable: the skip path's whole purpose is to cost almost
     * nothing, and a library whose items have not changed still writes no rows.
     * The comparison itself is free — every value is already in memory.
     *
     * Every fact moves in one upsert rather than one write each, because an item
     * whose match was corrected has changed all of them at once and the skip
     * path should not pay repeatedly for one item.
     */
    private function reconcileFacts(PlexItemRecord $existing, PlexItem $item, string $filename): void
    {
        $title = $this->mergedTitle($existing, $item);
        $year = $item->year ?? $existing->year;
        $tmdbId = $item->tmdbId ?? $existing->tmdbId;

        if (
            $title === $existing->title
            && $year === $existing->year
            && $tmdbId === $existing->tmdbId
            && $filename === $existing->filename
        ) {
            return;
        }

        $this->items->upsert(new PlexItemRecord(
            ratingKey: $existing->ratingKey,
            mediaType: $existing->mediaType,
            category: $existing->category,
            libraryTitle: $existing->libraryTitle,
            title: $title,
            filename: $filename,
            updatedAt: time(),
            sectionKey: $existing->sectionKey,
            thumb: $existing->thumb,
            addedAt: $existing->addedAt,
            year: $year,
            seasonNumber: $existing->seasonNumber,
            tmdbId: $tmdbId,
        ));
    }

    /**
     * The title to record: Plex's, unless it reports none and we already have
     * one. An item with no mapping and no title records the empty string, which
     * is what the gallery already treats as "fall back to the filename".
     */
    private function mergedTitle(?PlexItemRecord $existing, PlexItem $item): string
    {
        $title = $item->displayTitle();
        if ($title !== '') {
            return $title;
        }

        return $existing === null ? '' : $existing->title;
    }

    private function deriveFilename(PlexItem $item, string $bytes): string
    {
        return $this->deriveBaseName($item) . '.' . $this->extensionFor($bytes);
    }

    /**
     * The filename an item would be given today, without its extension. Shared
     * by a first import, which takes its extension from the downloaded bytes,
     * and by a rename, which keeps the stored file's own.
     */
    private function deriveBaseName(PlexItem $item): string
    {
        $title = $item->displayTitle();
        if ($item->mediaType === PlexMediaType::Movie && $item->year !== null) {
            $title .= ' (' . $item->year . ')';
        }

        return $title . ' [' . $item->libraryTitle . ']';
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
