<?php

declare(strict_types=1);

namespace App\Config;

use App\Support\Env;

/**
 * Immutable authentication configuration, built once from the environment.
 *
 * There is no credential here. Marquee is entered by signing in to Plex as the
 * account that owns the configured server, so the only setting left is how long
 * a session lasts.
 *
 * `AUTH_USERNAME`, `AUTH_PASSWORD`, and `AUTH_BYPASS` are still read, for one
 * purpose only — telling the user they are no longer used. They never
 * authenticate anything and never grant access. This is the same treatment
 * `PLEX_TOKEN` gets in {@see PlexConfig}, for the same reason: an upgrade that
 * silently changes how an install is entered, and offers no explanation, is the
 * worst version of this change. One sentence turns it into an instruction.
 */
final class AuthConfig
{
    /** Thirty days, matching the sliding window's default. */
    private const DEFAULT_SESSION_DURATION = 2592000;

    public function __construct(
        public readonly int $sessionDuration,
        public readonly bool $obsoleteEnvCredentials = false,
        public readonly bool $obsoleteEnvBypass = false,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            // Floored rather than taken as given: a zero or negative duration
            // would expire every session the instant it was created, locking the
            // install out of a login that is now the only way in.
            sessionDuration: max(60, Env::int('SESSION_DURATION', self::DEFAULT_SESSION_DURATION)),
            obsoleteEnvCredentials: Env::str('AUTH_USERNAME', '') !== '' || Env::str('AUTH_PASSWORD', '') !== '',
            // Presence, not truth. `AUTH_BYPASS=false` is just as obsolete as
            // `AUTH_BYPASS=true`, and the remedy — delete the line — is the same.
            obsoleteEnvBypass: Env::str('AUTH_BYPASS', '') !== '',
        );
    }
}
