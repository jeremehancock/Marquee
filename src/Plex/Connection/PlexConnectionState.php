<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use App\Config\PlexTokenSource;

/**
 * How Marquee is connected to Plex, as the interface needs to describe it.
 *
 * There are four states, and the third is the one that has to be said out loud:
 *
 *   1. connected by signing in
 *   2. connected by `PLEX_TOKEN`
 *   3. connected by `PLEX_TOKEN` while a signed-in token also exists, which is
 *      not in use because the variable wins
 *   4. not connected
 *
 * Reporting the third as a plain "signed in" would be a lie with consequences:
 * a user who then removed `PLEX_TOKEN` and restarted could find the stored
 * token belongs to a different Plex account, or was never completed.
 */
final class PlexConnectionState
{
    public function __construct(
        public readonly PlexTokenSource $source,
        public readonly ?string $serverName,
        public readonly bool $hasStoredToken,
        public readonly bool $hasServerUrl,
    ) {
    }

    /**
     * Whether Plex requests can be made at all — a token and a server URL.
     */
    public function isConnected(): bool
    {
        return $this->hasServerUrl && $this->source !== PlexTokenSource::None;
    }

    /**
     * Whether the token in use came from signing in.
     */
    public function isSignedIn(): bool
    {
        return $this->source === PlexTokenSource::Stored;
    }

    /**
     * Whether the token in use came from the environment.
     */
    public function usesEnvironment(): bool
    {
        return $this->source === PlexTokenSource::Environment;
    }

    /**
     * Whether a stored sign-in exists but is being overridden by `PLEX_TOKEN`.
     */
    public function isOverridden(): bool
    {
        return $this->hasStoredToken && $this->source === PlexTokenSource::Environment;
    }

    /**
     * Whether a token exists but no server URL does, which is connected in
     * credential but not in address — a distinct thing to tell the user, since
     * signing in again would not fix it.
     */
    public function needsServerUrl(): bool
    {
        return !$this->hasServerUrl && $this->source !== PlexTokenSource::None;
    }

    /**
     * A short label for the connection, for the app-wide status line.
     */
    public function summary(): string
    {
        if (!$this->isConnected()) {
            return 'Plex: not connected';
        }

        $where = $this->serverName ?? 'connected';
        $how = $this->isSignedIn() ? 'signed in' : 'PLEX_TOKEN';

        return sprintf('Plex: %s (%s)', $where, $how);
    }
}
