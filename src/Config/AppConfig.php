<?php

declare(strict_types=1);

namespace App\Config;

use App\Settings\SettingKey;
use App\Settings\SettingsStore;
use App\Support\Env;

/**
 * Immutable application configuration, built once at bootstrap.
 *
 * This class draws from two sources, which is a seam rather than an oversight.
 * The three directories and the error-display switch come from the environment
 * because they cannot come from anywhere else: the settings store lives inside
 * `dataDir`, so resolving that from the store would be circular, and
 * `displayErrors` is the switch that makes a broken install diagnosable — it
 * must not depend on reading a file that may be what broke.
 *
 * Everything else comes from the settings store, so that changing it does not
 * mean editing a compose file and recreating the container.
 */
final class AppConfig
{
    /**
     * The product's name. Deliberately a constant rather than an environment
     * lookup: it names the software, not the install, so renaming a site must
     * not rename the app a user installs to their home screen.
     */
    public const APP_NAME = 'Marquee';

    public function __construct(
        public readonly string $siteTitle,
        public readonly string $dataDir,
        public readonly string $postersDir,
        /**
         * Where server-side sessions are written.
         *
         * Defaults onto the persistent volume, because a session kept where the
         * container discards it is not a thirty-day session — it lasts until the
         * next image update, and getting back in needs plex.tv.
         *
         * A path rather than a switch. The reason to move it is a `/config` on a
         * network mount whose file locking misbehaves, and PHP's session handler
         * holds an exclusive lock across every request that reads a session.
         * `SESSION_DIR=/tmp` restores exactly the older behaviour; a tmpfs or a
         * different volume works too, without this having to anticipate them.
         */
        public readonly string $sessionDir,
        public readonly bool $displayErrors,
    ) {
    }

    /**
     * Resolve the configuration, taking the site title from the store.
     */
    public static function resolve(SettingsStore $store): self
    {
        return new self(
            siteTitle: $store->string(SettingKey::SiteTitle),
            dataDir: self::dataDir(),
            postersDir: rtrim(Env::str('POSTERS_DIR', '/config/posters'), '/'),
            sessionDir: rtrim(Env::str('SESSION_DIR', '/config/sessions'), '/'),
            displayErrors: Env::bool('DISPLAY_ERRORS', false),
        );
    }

    /**
     * Where the data directory is, resolved without the store.
     *
     * The settings store is constructed from this, so this one value has to be
     * answerable before any store exists. It is the base of the circularity the
     * class comment describes, isolated here so that nothing else has to know
     * about it.
     */
    public static function dataDir(): string
    {
        return rtrim(Env::str('DATA_DIR', '/config/data'), '/');
    }
}
