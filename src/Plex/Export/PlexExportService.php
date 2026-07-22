<?php

declare(strict_types=1);

namespace App\Plex\Export;

use App\Config\PlexConfig;
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

        if ($this->config->removeOverlayLabel && $record->sectionKey !== '') {
            $type = PlexMediaType::fromString($record->mediaType);
            if ($type !== null) {
                $this->plex->removeOverlayLabel($record->sectionKey, $type->plexTypeNumber(), $record->ratingKey);
            }
        }
    }
}
