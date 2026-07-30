<?php

declare(strict_types=1);

namespace App\Config;

use App\Plex\PlexMediaType;
use App\Support\Env;

/**
 * Immutable auto-import configuration, built once from the environment.
 * The schedule interval is handled by the container's crontab, not here.
 *
 * Library exclusions are not part of this config: they apply app-wide and are
 * carried by {@see LibraryExclusions}.
 */
final class AutoImportConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly bool $importMovies,
        public readonly bool $importShows,
        public readonly bool $importSeasons,
        public readonly bool $importCollections,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            enabled: Env::bool('AUTO_IMPORT_ENABLED', false),
            importMovies: Env::bool('AUTO_IMPORT_MOVIES', false),
            importShows: Env::bool('AUTO_IMPORT_SHOWS', false),
            importSeasons: Env::bool('AUTO_IMPORT_SEASONS', false),
            importCollections: Env::bool('AUTO_IMPORT_COLLECTIONS', false),
        );
    }

    /**
     * @return list<PlexMediaType>
     */
    public function mediaTypes(): array
    {
        $types = [];
        if ($this->importMovies) {
            $types[] = PlexMediaType::Movie;
        }
        if ($this->importShows) {
            $types[] = PlexMediaType::Show;
        }
        if ($this->importSeasons) {
            $types[] = PlexMediaType::Season;
        }
        if ($this->importCollections) {
            $types[] = PlexMediaType::Collection;
        }

        return $types;
    }
}
