<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use App\Config\PlexConfig;
use App\Database\PlexServerRepository;
use App\Plex\PlexClient;

/**
 * Reports how Marquee is connected to Plex.
 *
 * Two ways to ask, and the difference is the point:
 *
 *   - `current()` reads only what is already known and never contacts Plex, so
 *     it is safe on every page render. An unreachable server would otherwise
 *     stall every page for the connect timeout.
 *   - `refresh()` asks the server its name and caches the answer. It belongs on
 *     the connection panel, which exists to describe the connection and is the
 *     page a user opens when something is wrong.
 *
 * Neither reports whether Plex is reachable. That is deliberate: a status that
 * is one page-load stale would assert something this design does not check, and
 * a failed Plex operation already says so accurately at the moment it happens.
 */
final class PlexConnectionStatus
{
    public function __construct(
        private readonly PlexConfig $config,
        private readonly PlexConnectionStore $store,
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
        // the panel: the connection is still configured, it just did not answer.
        return $this->state($this->servers->name());
    }

    private function state(?string $serverName): PlexConnectionState
    {
        return new PlexConnectionState(
            source: $this->config->source(),
            serverName: $serverName,
            hasStoredToken: $this->store->token() !== null,
            hasServerUrl: $this->config->serverUrl !== '',
        );
    }
}
