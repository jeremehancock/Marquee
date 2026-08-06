<?php

declare(strict_types=1);

namespace App\Config;

use App\Plex\Connection\PlexConnectionStore;
use App\Support\Env;

/**
 * Immutable Plex connection configuration, built once at bootstrap.
 *
 * Every setting comes from the environment. The token alone may also come from
 * the connection store, written by signing in to Plex from the application —
 * and when both supply one, `PLEX_TOKEN` wins.
 *
 * That precedence is deliberate. It keeps an existing deployment behaving
 * exactly as it did, leaves automated and GitOps deployments a non-interactive
 * path, and means an action taken inside the application can never quietly
 * override configuration the operator declared. The cost is that a stored token
 * can exist while an environment token is serving requests, which the
 * connection panel has to state plainly rather than hide.
 */
final class PlexConfig
{
    /**
     * `$tokenSource` describes where `$token` came from and is only meaningful
     * when a token is present; it is not consulted unless `isConfigured()`.
     */
    public function __construct(
        public readonly string $serverUrl,
        public readonly string $token,
        public readonly int $connectTimeout,
        public readonly int $requestTimeout,
        public readonly bool $removeOverlayLabel = false,
        public readonly PlexTokenSource $tokenSource = PlexTokenSource::Environment,
    ) {
    }

    /**
     * Resolve the configuration, preferring `PLEX_TOKEN` over a stored token.
     *
     * The store is read here rather than deeper in the application so that the
     * "read configuration once at bootstrap" rule still holds with two sources.
     */
    public static function resolve(PlexConnectionStore $store): self
    {
        $envToken = Env::str('PLEX_TOKEN', '');
        $storedToken = $envToken === '' ? ($store->token() ?? '') : '';

        $token = $envToken !== '' ? $envToken : $storedToken;
        $source = match (true) {
            $envToken !== '' => PlexTokenSource::Environment,
            $storedToken !== '' => PlexTokenSource::Stored,
            default => PlexTokenSource::None,
        };

        return new self(
            serverUrl: rtrim(Env::str('PLEX_SERVER_URL', ''), '/'),
            token: $token,
            connectTimeout: max(1, Env::int('PLEX_CONNECT_TIMEOUT', 10)),
            requestTimeout: max(1, Env::int('PLEX_REQUEST_TIMEOUT', 60)),
            removeOverlayLabel: Env::bool('PLEX_REMOVE_OVERLAY_LABEL', false),
            tokenSource: $source,
        );
    }

    public function isConfigured(): bool
    {
        return $this->serverUrl !== '' && $this->token !== '';
    }

    /**
     * Where the token in use came from, or `None` when there is no token.
     */
    public function source(): PlexTokenSource
    {
        return $this->token === '' ? PlexTokenSource::None : $this->tokenSource;
    }

    /**
     * Whether a token obtained by signing in is the one serving requests.
     */
    public function isSignedIn(): bool
    {
        return $this->source() === PlexTokenSource::Stored;
    }
}
