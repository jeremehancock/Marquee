<?php

declare(strict_types=1);

namespace App\Config;

use App\Plex\Connection\PlexConnectionStore;
use App\Support\Env;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;

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
            serverUrl: self::serverUrl(Env::str('PLEX_SERVER_URL', '')),
            token: $store->token() ?? '',
            connectTimeout: max(1, Env::int('PLEX_CONNECT_TIMEOUT', 10)),
            requestTimeout: max(1, Env::int('PLEX_REQUEST_TIMEOUT', 60)),
            removeOverlayLabel: Env::bool('PLEX_REMOVE_OVERLAY_LABEL', false),
            // Read, never used as a credential. Drives the notice that tells an
            // upgrading user why their install is suddenly disconnected.
            obsoleteEnvToken: Env::str('PLEX_TOKEN', '') !== '',
        );
    }

    /**
     * The configured server address, or an empty string when it cannot be used.
     *
     * Trimmed first, because a stray space in a compose file survives into the
     * value and is enough to make the address unparseable.
     *
     * Then parsed with the same parser the HTTP client uses, so "configured"
     * here and "usable" there cannot disagree. An address this accepted but
     * Guzzle rejected would raise a `MalformedUriException` from inside a
     * request — which is an `InvalidArgumentException`, not a `GuzzleException`,
     * so nothing on the Plex path caught it. A port of `324000` instead of
     * `32400` answered the connection screen with a stack trace.
     *
     * An unusable address is reported as no address at all, which is a state the
     * connection screen already knows how to explain — and "set
     * `PLEX_SERVER_URL`" is the right instruction for a value that cannot be
     * used.
     */
    private static function serverUrl(string $raw): string
    {
        $url = rtrim(trim($raw), '/');
        if ($url === '') {
            return '';
        }

        try {
            new Uri($url);
        } catch (InvalidArgumentException) {
            return '';
        }

        return $url;
    }

    public function isConfigured(): bool
    {
        return $this->serverUrl !== '' && $this->token !== '';
    }
}
