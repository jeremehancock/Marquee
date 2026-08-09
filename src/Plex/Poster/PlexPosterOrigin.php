<?php

declare(strict_types=1);

namespace App\Plex\Poster;

/**
 * Where a poster Plex reports for an item came from, and whether Plex has it.
 *
 * Three cases, ordered by how close the poster already is to being the item's:
 *
 * {@see Uploaded} — the user's own history. Every poster ever applied to the
 * item, by Marquee or anything else that uploaded one. Plex marks these
 * `upload://`.
 *
 * {@see Server} — Plex's own copies: artwork a metadata agent downloaded, a
 * poster file taken from alongside the media, an image extracted from the media
 * itself. All of it lives in Plex's storage, which is the only thing true of all
 * three — so this is named for *where it is*, not where it came from.
 *
 * {@see Offered} — artwork Plex knows about but has **not** downloaded, given
 * as an absolute URL to the provider. Mostly the same providers Find Posters
 * aggregates, but not entirely: Plex also offers IMDb and Gracenote artwork,
 * which the poster search does not carry. These need no token, cannot go
 * through the image proxy, and are applied the same way a pasted URL is.
 *
 * Note that {@see Server} and {@see Offered} are **not** distinguished by
 * source. Most of a Server group came from TMDB, which is also where much of an
 * Offered group would come from; the difference is only whether Plex has the
 * bytes. Any label implying separate origins misdescribes both.
 */
enum PlexPosterOrigin
{
    /** Uploaded to the item — Plex marks these `upload://`. */
    case Uploaded;

    /** Held on the server, but not uploaded. */
    case Server;

    /** Known to Plex but not downloaded; a remote provider URL. */
    case Offered;

    /**
     * Whether the Plex server has the image itself.
     *
     * The dividing line for nearly everything downstream: a held poster is
     * fetched with the server's credentials, shown through the image proxy, and
     * applied by *selecting* it. An offered one is a plain URL that behaves
     * exactly like one a user pasted.
     */
    public function isHeldOnServer(): bool
    {
        return $this !== self::Offered;
    }
}
