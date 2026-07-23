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
        public readonly bool $displayErrors,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            siteTitle: Env::str('SITE_TITLE', self::APP_NAME),
            dataDir: rtrim(Env::str('DATA_DIR', '/config/data'), '/'),
            postersDir: rtrim(Env::str('POSTERS_DIR', '/config/posters'), '/'),
            displayErrors: Env::bool('DISPLAY_ERRORS', false),
        );
    }
}
