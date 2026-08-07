<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use App\Config\PlexConfig;
use GuzzleHttp\ClientInterface;
use SimpleXMLElement;
use Throwable;

/**
 * Asks a Plex server who owns it, using a token supplied by the caller.
 *
 * This exists because the question is asked at the one moment no token is
 * stored yet: a sign-in is being decided, and the candidate token is the only
 * one there is. Going through the ordinary client would consult the *stored*
 * configuration, find it empty on a first connection, and report "cannot tell"
 * — which, under a fail-closed rule, refuses the very account being checked.
 *
 * Reading the server rather than plex.tv is deliberate. A token that cannot
 * reach the server is not usable for anything Marquee does, so a failure here
 * is a refusal either way, and it keeps the comparison to a single question:
 * does the account behind this token match the account this server names?
 */
final class PlexServerOwner
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly PlexConfig $config,
    ) {
    }

    /**
     * The account the server names as its owner when asked with this token, or
     * null when it cannot be obtained.
     *
     * Null always means "could not tell" and never "no owner": callers refuse.
     */
    public function forToken(string $token): ?string
    {
        if ($this->config->serverUrl === '' || $token === '') {
            return null;
        }

        try {
            $response = $this->http->request('GET', $this->config->serverUrl . '/', [
                'headers' => [
                    'X-Plex-Token' => $token,
                    'Accept' => 'application/xml',
                ],
                'connect_timeout' => $this->config->connectTimeout,
                'timeout' => $this->config->requestTimeout,
                'http_errors' => true,
            ]);
            $body = (string) $response->getBody();
        } catch (Throwable) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if (!$xml instanceof SimpleXMLElement || !isset($xml['myPlexUsername'])) {
            return null;
        }

        $owner = trim((string) $xml['myPlexUsername']);

        return $owner !== '' ? $owner : null;
    }
}
