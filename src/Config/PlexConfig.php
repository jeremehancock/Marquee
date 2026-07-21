<?php

declare(strict_types=1);

namespace App\Config;

use App\Support\Env;

/**
 * Immutable Plex connection configuration, built once from the environment.
 */
final class PlexConfig
{
    public function __construct(
        public readonly string $serverUrl,
        public readonly string $token,
        public readonly int $connectTimeout,
        public readonly int $requestTimeout,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            serverUrl: rtrim(Env::str('PLEX_SERVER_URL', ''), '/'),
            token: Env::str('PLEX_TOKEN', ''),
            connectTimeout: max(1, Env::int('PLEX_CONNECT_TIMEOUT', 10)),
            requestTimeout: max(1, Env::int('PLEX_REQUEST_TIMEOUT', 60)),
        );
    }

    public function isConfigured(): bool
    {
        return $this->serverUrl !== '' && $this->token !== '';
    }
}
