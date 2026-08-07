<?php

declare(strict_types=1);

namespace App\Config;

use App\Plex\Connection\PlexConnectionStore;
use App\Support\Env;

/**
 * Immutable Plex connection configuration, built once at bootstrap.
 *
 * Every setting comes from the environment except the token, which comes from
 * the connection store written by signing in to Plex. There is no second
 * source: supporting both was tried and removed, because precedence had to be
 * explained wherever the connection was described, produced a state where a
 * stored token existed but was inert, and made every Plex error message branch
 * on which source was live.
 *
 * `PLEX_TOKEN` is still read, for one purpose only — telling the user it is no
 * longer used. It never authenticates a request. An upgrade that silently
 * disconnects an install and offers no explanation is the worst version of this
 * change; one sentence turns it into an instruction.
 */
final class PlexConfig
{
    public function __construct(
        public readonly string $serverUrl,
        public readonly string $token,
        public readonly int $connectTimeout,
        public readonly int $requestTimeout,
        public readonly bool $removeOverlayLabel = false,
        public readonly bool $obsoleteEnvToken = false,
    ) {
    }

    /**
     * Resolve the configuration, taking the token from the store.
     *
     * The store is read here rather than deeper in the application so that the
     * "read configuration once at bootstrap" rule still holds.
     */
    public static function resolve(PlexConnectionStore $store): self
    {
        return new self(
            serverUrl: rtrim(Env::str('PLEX_SERVER_URL', ''), '/'),
            token: $store->token() ?? '',
            connectTimeout: max(1, Env::int('PLEX_CONNECT_TIMEOUT', 10)),
            requestTimeout: max(1, Env::int('PLEX_REQUEST_TIMEOUT', 60)),
            removeOverlayLabel: Env::bool('PLEX_REMOVE_OVERLAY_LABEL', false),
            // Read, never used as a credential. Drives the notice that tells an
            // upgrading user why their install is suddenly disconnected.
            obsoleteEnvToken: Env::str('PLEX_TOKEN', '') !== '',
        );
    }

    public function isConfigured(): bool
    {
        return $this->serverUrl !== '' && $this->token !== '';
    }
}
