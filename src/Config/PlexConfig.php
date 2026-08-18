<?php

declare(strict_types=1);

namespace App\Config;

use App\Plex\Connection\PlexConnectionStore;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;

/**
 * Immutable Plex connection configuration, built once at bootstrap.
 *
 * Every setting comes from the settings store except the token, which comes
 * from the connection store written by signing in to Plex. There is no second
 * source for either: supporting both was tried and removed, because precedence
 * had to be explained wherever the connection was described, produced a state
 * where a stored token existed but was inert, and made every Plex error message
 * branch on which source was live.
 *
 * The address and the credential stay separately sourced on purpose. Signing in
 * supplies a credential, never an address; the address is configuration, and it
 * is seeded from `PLEX_SERVER_URL` like every other setting.
 *
 * `PLEX_TOKEN` is no longer read here at all. It is still reported to the user
 * as retired — see {@see \App\Settings\SupersededEnvironment}.
 */
final class PlexConfig
{
    /**
     * The shortest timeout this will resolve, in seconds.
     *
     * A timeout of zero means "no timeout" to Guzzle, which is the opposite of
     * what someone typing it intends. The settings screen applies this same
     * constant, so a value it accepts is never one bootstrap corrects.
     */
    public const MINIMUM_TIMEOUT = 1;

    public function __construct(
        public readonly string $serverUrl,
        public readonly string $token,
        public readonly int $connectTimeout,
        public readonly int $requestTimeout,
        public readonly bool $removeOverlayLabel = false,
    ) {
    }

    /**
     * Resolve the configuration from both stores.
     *
     * They are read here rather than deeper in the application so that the
     * "read configuration once at bootstrap" rule still holds.
     */
    public static function resolve(PlexConnectionStore $connection, SettingsStore $settings): self
    {
        return new self(
            serverUrl: self::serverUrl($settings->string(SettingKey::PlexServerUrl)),
            token: $connection->token() ?? '',
            connectTimeout: max(self::MINIMUM_TIMEOUT, $settings->int(SettingKey::PlexConnectTimeout)),
            requestTimeout: max(self::MINIMUM_TIMEOUT, $settings->int(SettingKey::PlexRequestTimeout)),
            removeOverlayLabel: $settings->bool(SettingKey::PlexRemoveOverlayLabel),
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
     * connection screen already knows how to explain — and asking for the
     * address again is the right instruction for a value that cannot be used.
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
