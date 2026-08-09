<?php

declare(strict_types=1);

namespace App\Plex;

/**
 * Signs a Plex image path into an opaque token, and resolves a token back to
 * the path it stands for.
 *
 * Plex image URLs carry the Plex token, so no Plex image address can be put in
 * a page. Every such image is instead served by Marquee, which needs a way to
 * name the image in a URL without either disclosing the path's origin or
 * accepting whatever path a caller supplies. An HMAC over the path solves both:
 * the token is opaque, and a proxy that refuses any token it did not sign
 * cannot be turned into an open relay to the Plex server.
 *
 * The signature is the whole of the security. Without it the proxy would fetch
 * any path handed to it, using the server's own Plex token to do so.
 *
 * Kept deliberately free of any one caller's concerns — the poster wall's Live
 * TV sentinel lives in {@see \App\Poster\Wall\StreamToken}, which composes this.
 */
final class SignedImagePath
{
    public function __construct(private readonly string $secret)
    {
    }

    /**
     * An opaque, signed token standing for the given Plex image path.
     */
    public function sign(string $path): string
    {
        $payload = $this->encode($path);

        return $payload . '.' . $this->signature($payload);
    }

    /**
     * The Plex image path a token stands for, or null when the token has a bad
     * signature, does not decode, or does not name an absolute Plex path.
     *
     * The absolute-path check is not redundant with the signature: it bounds
     * what a *correctly signed* token may resolve to, so a path that reached
     * the signer by mistake still cannot send the proxy at another host.
     */
    public function pathFor(string $token): ?string
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        if (!hash_equals($this->signature($payload), $signature)) {
            return null;
        }

        $path = $this->decode($payload);
        if ($path === null || !str_starts_with($path, '/')) {
            return null;
        }

        return $path;
    }

    private function signature(string $payload): string
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
