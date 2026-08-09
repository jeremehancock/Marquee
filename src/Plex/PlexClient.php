<?php

declare(strict_types=1);

namespace App\Plex;

use App\Plex\Poster\PlexPosterList;

/**
 * Read access to a Plex Media Server. Implementations must not throw for an
 * unconfigured server from isConfigured(); other methods throw PlexException.
 */
interface PlexClient
{
    public function isConfigured(): bool;

    /**
     * Libraries excluded by configuration are never returned, so callers need
     * no exclusion check of their own: an excluded library does not exist as
     * far as the rest of the application is concerned.
     *
     * @return list<PlexLibrary>
     */
    public function libraries(): array;

    /**
     * Movies for a movie library, shows for a show library.
     *
     * @return list<PlexItem>
     */
    public function items(PlexLibrary $library): array;

    /**
     * @return list<PlexItem>
     */
    public function seasons(PlexItem $show): array;

    /**
     * @return list<PlexItem>
     */
    public function collections(PlexLibrary $library): array;

    /**
     * Raw bytes of the item's current Plex poster.
     */
    public function downloadPoster(PlexItem $item): string;

    /**
     * Raw bytes of the current Plex poster for an item, looked up by rating key.
     */
    public function itemPoster(string $ratingKey): string;

    /**
     * The currently active playback sessions, in the order Plex reports them.
     * Returns an empty list when nothing is playing.
     *
     * @return list<PlexSession>
     */
    public function sessions(): array;

    /**
     * Every poster the server holds for an item, in the order Plex reports.
     *
     * Remote provider artwork Plex merely offers for the item is not included;
     * see {@see \App\Plex\Poster\PlexPosterList::fromXml()} for why.
     */
    public function itemPosters(string $ratingKey): PlexPosterList;

    /**
     * Raw bytes of the image at a Plex image path — a session's `thumb` or
     * `grandparentThumb`, or one of an item's own poster paths.
     *
     * The path must originate from this client, either from a session or from
     * an item's poster list; callers never pass an untrusted path directly.
     * Where a path makes a round trip through a URL before coming back here, it
     * is signed on the way out and verified on the way in — see
     * {@see SignedImagePath}.
     */
    public function imageAt(string $path): string;

    /**
     * The server's own friendly name, or null when it cannot be obtained.
     *
     * This names the connection for the user. It is read from the server rather
     * than from plex.tv so that one code path serves both connection sources
     * and nothing has to reach the internet to describe a local connection.
     *
     * Null is an ordinary result — an unreachable or unconfigured server — and
     * callers report the connection without a name rather than failing.
     */
    public function serverName(): ?string;

}
