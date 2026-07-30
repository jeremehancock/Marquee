<?php

declare(strict_types=1);

namespace App\Plex;

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
     * Raw bytes of the poster at a Plex image path (a session's `thumb` or
     * `grandparentThumb`). The path must originate from a session this client
     * produced; callers never pass an untrusted path directly.
     */
    public function sessionPoster(string $thumb): string;
}
