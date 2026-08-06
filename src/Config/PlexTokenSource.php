<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Where the Plex token in use came from.
 *
 * The two sources are equivalent in capability — both end up as an
 * `X-Plex-Token` header — but they differ in who manages the credential, which
 * is what the connection panel reports and what decides the remedy offered when
 * Plex rejects it.
 */
enum PlexTokenSource
{
    /** Supplied as `PLEX_TOKEN`. Wins whenever it is set. */
    case Environment;

    /** Obtained by signing in to Plex and held in the connection store. */
    case Stored;

    /** Neither source supplied one; Plex is not connected. */
    case None;
}
