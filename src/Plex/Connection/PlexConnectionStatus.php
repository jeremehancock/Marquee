<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use App\Config\PlexConfig;
use App\Database\PlexServerRepository;
use App\Plex\PlexClient;

/**
 * Reports how Marquee stands with Plex.
 *
 * Two ways to ask, and the difference matters:
 *
 *   - `current()` reads only what is already known and never contacts Plex, so
 *     it is safe anywhere — including the connection gate, which runs on every
 *     request. An unreachable server would otherwise stall the whole app for
 *     the connect timeout.
 *   - `refresh()` asks the server its name and caches the answer. It belongs on
 *     the connection screen, which exists to describe the connection and is the
 *     page a user opens when something is wrong.
 *
 * Neither reports whether Plex is reachable. A failed Plex operation says so
 * accurately at the moment it happens; a cached status would only guess.
 */
final class PlexConnectionStatus
{
    public function __construct(
        private readonly PlexConfig $config,
        private readonly PlexServerRepository $servers,
        private readonly PlexClient $plex,
    ) {
    }

    /**
     * The connection as currently configured, from cached information only.
     */
    public function current(): PlexConnectionState
    {
        return $this->state($this->config->isConfigured() ? $this->servers->name() : null);
    }

    /**
     * The connection, having asked the server its name and cached the answer.
     */
    public function refresh(): PlexConnectionState
    {
        if (!$this->config->isConfigured()) {
            // Nothing is connected, so a remembered name would name a server we
            // are no longer talking to.
            $this->servers->forget();

            return $this->state(null);
        }

        $name = $this->plex->serverName();
        if ($name !== null) {
            $this->servers->remember($name);

            return $this->state($name);
        }

        // Unreachable right now. Keep the last known name rather than blanking
        // the screen: the connection is still configured, it just did not answer.
        return $this->state($this->servers->name());
    }

    private function state(?string $serverName): PlexConnectionState
    {
        return new PlexConnectionState(
            hasToken: $this->config->token !== '',
            serverName: $serverName,
            hasServerUrl: $this->config->serverUrl !== '',
        );
    }
}
