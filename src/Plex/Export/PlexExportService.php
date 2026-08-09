<?php

declare(strict_types=1);

namespace App\Plex\Export;

use App\Config\PlexConfig;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\PlexMediaType;
use App\Plex\PlexPosterWriter;
use App\Poster\PosterCategory;
use App\Poster\PosterStorage;

/**
 * Sends a stored poster back to its linked Plex item, locks it, and optionally
 * removes the Kometa overlay label.
 */
final class PlexExportService
{
    public function __construct(
        private readonly PlexPosterWriter $plex,
        private readonly PosterStorage $storage,
        private readonly PlexItemRepository $items,
        private readonly PlexConfig $config,
    ) {
    }

    public function sendToPlex(PosterCategory $category, string $filename): void
    {
        $record = $this->items->findByFilename($category->value, $filename);
        if ($record === null) {
            throw ExportException::notLinked();
        }

        $path = $this->storage->path($category, $filename);
        $bytes = $path !== null ? file_get_contents($path) : false;
        if ($bytes === false) {
            throw ExportException::missingFile();
        }

        $this->plex->uploadPoster($record->ratingKey, $bytes);
        $this->plex->lockPoster($record->ratingKey);
        $this->afterPosterSet($record);
    }

    /**
     * Point the linked item at a poster the server already holds, then lock it.
     *
     * The same outcome as sendToPlex() for a poster Plex has already: the item
     * ends up showing it, locked against the next metadata refresh, with the
     * Kometa label handled identically. What it avoids is the upload, which
     * would leave a duplicate behind — Plex never prunes an item's posters.
     */
    public function selectInPlex(PosterCategory $category, string $filename, string $posterKey): void
    {
        $record = $this->items->findByFilename($category->value, $filename);
        if ($record === null) {
            throw ExportException::notLinked();
        }

        $this->plex->selectPoster($record->ratingKey, $posterKey);
        $this->plex->lockPoster($record->ratingKey);
        $this->afterPosterSet($record);
    }

    /**
     * The Kometa overlay label is removed however the poster was set: an
     * overlaid poster is stale the moment the artwork under it changes, and
     * which call changed it makes no difference to that.
     */
    private function afterPosterSet(PlexItemRecord $record): void
    {
        if (!$this->config->removeOverlayLabel || $record->sectionKey === '') {
            return;
        }

        $type = PlexMediaType::fromString($record->mediaType);
        if ($type !== null) {
            $this->plex->removeOverlayLabel($record->sectionKey, $type->plexTypeNumber(), $record->ratingKey);
        }
    }
}
