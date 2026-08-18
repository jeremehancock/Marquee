<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * Why a still-present environment variable no longer has any effect.
 *
 * Two kinds rather than one, because the remedy a user needs differs. Both end
 * in "delete the line", but a user told that `AUTH_PASSWORD` is "managed in the
 * application now" would go looking for a password field that does not exist
 * and never will.
 */
enum SupersededKind
{
    /**
     * The capability the variable configured no longer exists. Nothing replaces
     * it, and nothing in the interface corresponds to it.
     */
    case Retired;

    /**
     * The setting still exists, but the application owns it now. The value that
     * was in the environment was carried across when the store was seeded, so
     * nothing was lost by it ceasing to be read.
     */
    case Relocated;
}
