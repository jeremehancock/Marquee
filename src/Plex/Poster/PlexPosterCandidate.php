<?php

declare(strict_types=1);

namespace App\Plex\Poster;

/**
 * One poster the Plex server holds for an item.
 *
 * Both paths are server-relative: a candidate addressed by an absolute URL is
 * not on the server and never becomes one of these (see
 * {@see PlexPosterList::fromXml()}). That invariant is what lets every candidate
 * be served through the image proxy, which refuses anything not rooted at `/`.
 *
 * `$path` is the full-resolution image, fetched when the user commits.
 * `$thumbPath` is what the grid loads. Plex reports them separately and they are
 * usually identical for a server-held poster, but they are kept apart rather
 * than collapsed so a future Plex that does supply a smaller preview needs no
 * change here.
 *
 * `$posterKey` is Plex's own name for the poster (`upload://posters/<hash>`,
 * `metadata://…`, `media://…`) and is what selects it. It is read from the
 * response rather than derived from `$path`, even though the two are related
 * today: a path is an address that Plex is free to restyle, while the key is
 * the identity, and inferring one from the other would break silently.
 */
final class PlexPosterCandidate
{
    public function __construct(
        public readonly string $path,
        public readonly string $thumbPath,
        public readonly string $posterKey,
        public readonly PlexPosterOrigin $origin,
        public readonly bool $selected,
    ) {
    }
}
