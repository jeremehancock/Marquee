<?php

declare(strict_types=1);

namespace App\Config;

use App\Settings\SettingKey;
use App\Settings\SettingsStore;

/**
 * Immutable authentication configuration, built once at bootstrap.
 *
 * There is no credential here. Marquee is entered by signing in to Plex as the
 * account that owns the configured server, so the only setting left is how long
 * a session lasts.
 *
 * `AUTH_USERNAME`, `AUTH_PASSWORD`, and `AUTH_BYPASS` are no longer read here.
 * They are still reported to the user as retired — see
 * {@see \App\Settings\SupersededEnvironment}, which now owns that job for every
 * superseded variable rather than each configuration object carrying its own
 * flag for the ones it happened to know about.
 */
final class AuthConfig
{
    public function __construct(
        public readonly int $sessionDuration,
    ) {
    }

    public static function resolve(SettingsStore $store): self
    {
        return new self(
            // Floored rather than taken as given: a zero or negative duration
            // would expire every session the instant it was created, locking the
            // install out of a login that is now the only way in.
            //
            // The floor lives here rather than in the store because it is a
            // judgement about what the value means, and the settings screen has
            // to apply the same one — a value it accepted that bootstrap then
            // corrected would be a setting that silently does not stick.
            sessionDuration: max(60, $store->int(SettingKey::SessionDuration)),
        );
    }
}
