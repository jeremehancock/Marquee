<?php

declare(strict_types=1);

namespace App\Plex\Connection;

/**
 * An in-progress Plex authorization request.
 *
 * Plex calls these "pins": the application asks for one, the user approves it
 * on Plex's own site, and the application then exchanges it for a token. The
 * code is what the user's browser carries to Plex; the id is what this
 * application polls.
 */
final class PlexPin
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly int $expiresAt,
    ) {
    }

    public function hasExpired(?int $now = null): bool
    {
        return ($now ?? time()) >= $this->expiresAt;
    }
}
