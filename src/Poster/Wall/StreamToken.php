<?php

declare(strict_types=1);

namespace App\Poster\Wall;

/**
 * Mints and resolves the opaque token that stands in for a now-playing poster
 * in the `/wall/stream-poster/{id}` URL.
 *
 * The token carries a Plex image path (a session's `thumb`) signed with an
 * HMAC, so the poster proxy can recover the path without a server-side session
 * store while refusing any path it did not itself sign. This keeps the proxy
 * from becoming an open relay for arbitrary Plex URLs. Live TV has no library
 * art, so it uses a fixed sentinel that resolves to the bundled placeholder.
 */
final class StreamToken
{
    /** Sentinel token for a Live TV tile; resolves to the placeholder poster. */
    public const LIVE = 'live';

    public function __construct(private readonly string $secret)
    {
    }

    /**
     * A signed token for the poster at the given Plex image path.
     */
    public function forThumb(string $thumb): string
    {
        $payload = $this->encode($thumb);

        return $payload . '.' . $this->sign($payload);
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
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        if (!hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $thumb = $this->decode($payload);
        if ($thumb === null || !str_starts_with($thumb, '/')) {
            return null;
        }

        return $thumb;
    }

    private function sign(string $payload): string
    {
        return $this->base64Url(hash_hmac('sha256', $payload, $this->secret, true));
    }

    private function encode(string $value): string
    {
        return $this->base64Url($value);
    }

    private function decode(string $payload): ?string
    {
        $decoded = base64_decode(strtr($payload, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
