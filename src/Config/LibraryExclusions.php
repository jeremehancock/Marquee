<?php

declare(strict_types=1);

namespace App\Config;

use App\Settings\SettingKey;
use App\Settings\SettingsStore;

/**
 * The libraries Marquee is told to ignore, built once at bootstrap.
 *
 * An excluded library is invisible to the whole application: the Plex client
 * never reports it, so no screen, import, or scheduled run can observe one.
 * Entries are library *names* — the title as it appears in Plex — never section
 * keys, so an entry that is not a library name excludes nothing.
 */
final class LibraryExclusions
{
    /**
     * @param list<string> $names library names to exclude (case-insensitive)
     */
    public function __construct(
        public readonly array $names,
    ) {
    }

    public static function resolve(SettingsStore $store): self
    {
        return new self($store->list(SettingKey::ExcludedLibraries));
    }

    public function hasAny(): bool
    {
        return $this->names !== [];
    }

    public function isExcluded(string $libraryTitle): bool
    {
        $needle = mb_strtolower(trim($libraryTitle));
        foreach ($this->names as $excluded) {
            if (mb_strtolower(trim($excluded)) === $needle) {
                return true;
            }
        }

        return false;
    }
}
