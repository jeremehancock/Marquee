<?php

declare(strict_types=1);

namespace App\Config;

use App\Support\Env;

/**
 * Immutable authentication configuration, built once from the environment.
 */
final class AuthConfig
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly bool $bypass,
        public readonly int $sessionDuration,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            username: Env::str('AUTH_USERNAME', 'admin'),
            password: Env::str('AUTH_PASSWORD', 'changeme'),
            bypass: Env::bool('AUTH_BYPASS', false),
            sessionDuration: Env::int('SESSION_DURATION', 3600),
        );
    }
}
