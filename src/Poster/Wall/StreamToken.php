<?php

declare(strict_types=1);

namespace App\Poster\Wall;

use App\Plex\SignedImagePath;

/**
 * Mints and resolves the opaque token that stands in for a now-playing poster
 * in the `/wall/stream-poster/{id}` URL.
 *
 * The token carries a Plex image path (a session's `thumb`) signed with an
 * HMAC, so the poster proxy can recover the path without a server-side session
 * store while refusing any path it did not itself sign. This keeps the proxy
 * from becoming an open relay for arbitrary Plex URLs. Live TV has no library
 * art, so it uses a fixed sentinel that resolves to the bundled placeholder.
 *
 * The signing itself is {@see SignedImagePath}, shared with the change dialog's
 * Plex poster proxy. What stays here is the wall's own concern: the Live TV
 * sentinel, which is a tile state rather than an image path.
 */
final class StreamToken
{
    /** Sentinel token for a Live TV tile; resolves to the placeholder poster. */
    public const LIVE = 'live';

    private readonly SignedImagePath $signer;

    public function __construct(string $secret)
    {
        $this->signer = new SignedImagePath($secret);
    }

    /**
     * A signed token for the poster at the given Plex image path.
     */
    public function forThumb(string $thumb): string
    {
        return $this->signer->sign($thumb);
    }

    public function isLive(string $token): bool
    {
        return $token === self::LIVE;
    }

    /**
     * The Plex image path a token stands for, or null when the token is the
     * Live TV sentinel, has a bad signature, or does not decode to an absolute
     * Plex path.
     */
    public function thumbFor(string $token): ?string
    {
        return $this->signer->pathFor($token);
    }
}
