<?php

declare(strict_types=1);

namespace App\Config;

use App\Plex\PlexMediaType;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;

/**
 * Immutable auto-import configuration, built once at bootstrap.
 *
 * The schedule interval is still handled by the container's crontab rather than
 * here. It is seeded into the settings store alongside these toggles so that
 * moving scheduling into the application is one change rather than two, but
 * nothing reads it yet.
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

    public static function resolve(SettingsStore $store): self
    {
        return new self(
            enabled: $store->bool(SettingKey::AutoImportEnabled),
            importMovies: $store->bool(SettingKey::AutoImportMovies),
            importShows: $store->bool(SettingKey::AutoImportShows),
            importSeasons: $store->bool(SettingKey::AutoImportSeasons),
            importCollections: $store->bool(SettingKey::AutoImportCollections),
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
