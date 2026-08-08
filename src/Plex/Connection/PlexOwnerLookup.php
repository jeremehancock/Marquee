<?php

declare(strict_types=1);

namespace App\Plex\Connection;

/**
 * The result of asking a Plex server who owns it.
 *
 * Three answers, and the difference between the last two is the whole point of
 * this class. A server that answered and named nobody has refused the account; a
 * server that never answered has refused nothing, and saying otherwise tells the
 * owner their own account is not theirs while the actual fault sits in the
 * server address.
 *
 * Only `named()` can carry an owner, and the constructor is private, so
 * "unreachable, and here is the owner" cannot be built. A caller that reads the
 * owner without checking reachability still gets null and still refuses: the
 * fail-closed rule survives being used carelessly.
 */
final class PlexOwnerLookup
{
    private function __construct(
        private readonly bool $reachable,
        private readonly ?string $owner,
        private readonly ?string $serverName = null,
    ) {
    }

    /**
     * The server answered and named its owner.
     *
     * It also names itself in the same response, so the friendly name comes back
     * with the verdict rather than costing a second round trip. Only this path
     * carries it: it is the only one that ends in anything being stored.
     */
    public static function named(string $owner, ?string $serverName = null): self
    {
        return new self(true, $owner, $serverName);
    }

    /**
     * The server answered, but named no owner Marquee can compare against —
     * either it declined the token, or it reported no owner at all.
     */
    public static function anonymous(): self
    {
        return new self(true, null);
    }

    /**
     * The server did not usefully answer: nothing came back, an error status
     * came back, or what came back was not a Plex response.
     */
    public static function unreachable(): self
    {
        return new self(false, null);
    }

    public function isUnreachable(): bool
    {
        return !$this->reachable;
    }

    /**
     * The named owner, or null when there is not one to compare against.
     *
     * Never non-null for an unreachable lookup.
     */
    public function owner(): ?string
    {
        return $this->owner;
    }

    /**
     * What the server calls itself, when it said so.
     */
    public function serverName(): ?string
    {
        return $this->serverName;
    }
}
