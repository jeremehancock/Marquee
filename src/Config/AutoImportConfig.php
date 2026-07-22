<?php

declare(strict_types=1);

namespace App\Config;

use App\Plex\PlexMediaType;
use App\Support\Env;

/**
 * Immutable auto-import configuration, built once from the environment.
 * The schedule interval is handled by the container's crontab, not here.
 */
final class AutoImportConfig
{
    /**
     * @param list<string> $excludedLibraries library names to skip (case-insensitive)
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly bool $importMovies,
        public readonly bool $importShows,
        public readonly bool $importSeasons,
        public readonly bool $importCollections,
        public readonly array $excludedLibraries,
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
            excludedLibraries: Env::list('EXCLUDED_LIBRARIES', []),
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

    public function isExcluded(string $libraryTitle): bool
    {
        $needle = mb_strtolower(trim($libraryTitle));
        foreach ($this->excludedLibraries as $excluded) {
            if (mb_strtolower(trim($excluded)) === $needle) {
                return true;
            }
        }

        return false;
    }
}
