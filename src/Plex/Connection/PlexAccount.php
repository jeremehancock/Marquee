<?php

declare(strict_types=1);

namespace App\Plex\Connection;

/**
 * The Plex account behind a token, as plex.tv reports it.
 *
 * Both identifiers are kept because a Plex server names its owner in one field
 * that may hold either: `myPlexUsername` is an email address on some accounts
 * and a username on others.
 */
final class PlexAccount
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
    ) {
    }

    /**
     * Whether this account is the one a server names as its owner.
     *
     * Compared case-insensitively, and against both identifiers, because the
     * server reports only one of them and does not say which kind it is.
     */
    public function matches(string $owner): bool
    {
        $owner = strtolower(trim($owner));
        if ($owner === '') {
            return false;
        }

        foreach ([$this->username, $this->email] as $candidate) {
            if ($candidate !== '' && strtolower($candidate) === $owner) {
                return true;
            }
        }

        return false;
    }
}
