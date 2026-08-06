<?php

declare(strict_types=1);

namespace App\Plex;

/**
 * Why a Plex operation failed.
 *
 * The reason is carried instead of a ready-made instruction because the right
 * instruction depends on how Marquee is connected, which this layer has no
 * business knowing. Telling a user who signed in to "check PLEX_TOKEN" sends
 * them to a variable they deliberately do not have.
 */
enum PlexFailure
{
    /** No credential, or no server address, so nothing could be attempted. */
    case NotConfigured;

    /** The server refused the credential. */
    case AuthRejected;

    /** The server could not be reached at all. */
    case ConnectionFailed;

    /** The item is gone from Plex — usually an orphaned poster. */
    case ItemMissing;

    /** The server answered with something unusable. */
    case UnexpectedResponse;
}
