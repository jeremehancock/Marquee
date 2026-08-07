<?php

declare(strict_types=1);

namespace App\Plex\Connection;

/**
 * How an in-progress sign-in stands.
 */
enum PlexSignInStatus: string
{
    /** This session has no authorization request outstanding. */
    case NotStarted = 'not_started';

    /** Waiting for the user to approve the request on Plex's site. */
    case Pending = 'pending';

    /** Approved; a token has been stored. */
    case Completed = 'completed';

    /** The request aged out before it was approved. Nothing was stored. */
    case Expired = 'expired';

    /**
     * Approved, but by an account that does not own the configured server.
     * Nothing was stored.
     */
    case NotOwner = 'not_owner';
}
