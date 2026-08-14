<?php

declare(strict_types=1);

namespace App\Config;

use App\Support\Env;

/**
 * Immutable application configuration, built once from the environment.
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

    public static function fromEnv(): self
    {
        return new self(
            siteTitle: Env::str('SITE_TITLE', self::APP_NAME),
            dataDir: rtrim(Env::str('DATA_DIR', '/config/data'), '/'),
            postersDir: rtrim(Env::str('POSTERS_DIR', '/config/posters'), '/'),
            sessionDir: rtrim(Env::str('SESSION_DIR', '/config/sessions'), '/'),
            displayErrors: Env::bool('DISPLAY_ERRORS', false),
        );
    }
}
