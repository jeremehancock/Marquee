<?php

declare(strict_types=1);

namespace App\Config;

use App\Support\Env;

/**
 * Immutable application configuration, built once from the environment.
 */
final class AppConfig
{
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
            siteTitle: Env::str('SITE_TITLE', 'Marquee'),
            dataDir: rtrim(Env::str('DATA_DIR', '/config/data'), '/'),
            postersDir: rtrim(Env::str('POSTERS_DIR', '/config/posters'), '/'),
            displayErrors: Env::bool('DISPLAY_ERRORS', false),
        );
    }
}
