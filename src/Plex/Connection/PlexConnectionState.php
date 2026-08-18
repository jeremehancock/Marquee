<?php

declare(strict_types=1);

namespace App\Plex\Connection;

/**
 * How Marquee stands with Plex, as the connection screen needs to describe it.
 *
 * Two states, because there is one way to connect: connected, or not. What the
 * screen still has to distinguish is *why* it is not connected — a missing
 * credential is fixed by signing in, a missing server address is not, and
 * offering the wrong remedy strands the user behind the connection gate.
 */
final class PlexConnectionState
{
    public function __construct(
        public readonly bool $hasToken,
        public readonly ?string $serverName,
        public readonly bool $hasServerUrl,
    ) {
    }

    /**
     * Whether Plex requests can be made at all — a token and a server address.
     */
    public function isConnected(): bool
    {
        return $this->hasToken && $this->hasServerUrl;
    }

    /**
     * Whether the missing piece is the address rather than the credential.
     *
     * Signing in cannot supply an address, so this case must be worded
     * differently or the user is told to do something that cannot help.
     */
    public function needsServerUrl(): bool
    {
        return !$this->hasServerUrl;
    }
}
