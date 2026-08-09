<?php

declare(strict_types=1);

namespace App\Plex\Poster;

/**
 * How a poster came to be on the Plex server.
 *
 * Two cases rather than the several Plex can report, because only one boundary
 * matters to a user: posters they put there themselves, and everything else.
 *
 * {@see Uploaded} is the user's own history — every poster ever applied to the
 * item, by Marquee or by anything else that uploaded one. {@see Server} is the
 * rest of what the server holds: artwork a metadata agent downloaded, a poster
 * file found alongside the media, an image embedded in the media itself. That
 * group is deliberately *not* called "from the metadata agent"; it holds all
 * three kinds and naming one would misdescribe the others.
 */
enum PlexPosterOrigin
{
    /** Uploaded to the item — Plex marks these `upload://`. */
    case Uploaded;

    /** Held on the server, but not uploaded. */
    case Server;
}
