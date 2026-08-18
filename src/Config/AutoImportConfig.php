<?php

declare(strict_types=1);

namespace App\Config;

use App\Plex\Import\AutoImportInterval;
use App\Plex\PlexMediaType;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;

/**
 * Immutable auto-import configuration, built once at bootstrap.
 *
 * The interval is read here rather than by the container. It used to be a `case`
 * statement in an s6 init script that wrote a crontab once at boot, which meant
 * the schedule could not change without recreating the container; now the
 * crontab is a fixed tick and this value decides whether a tick is due.
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
        public readonly AutoImportInterval $interval = AutoImportInterval::Daily,
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
            // Unset, empty, or unrecognised falls back to daily — the same
            // fallback the init script's `*)` branch applied.
            interval: AutoImportInterval::fromSlug($store->string(SettingKey::AutoImportSchedule))
                ?? AutoImportInterval::default(),
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
