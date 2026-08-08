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
 *
 * A failure is a refusal, but not every failure means the same thing to the
 * person reading it, so the answer says which happened. The rule is what the
 * server did, not why we asked: a server that answers and declines the token
 * has made a statement about the account, while a server that never answers has
 * made none, and reporting the second as the first tells the owner their own
 * account is not theirs.
 */
final class PlexServerOwner
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly PlexConfig $config,
    ) {
    }

    /**
     * Who the server names as its owner when asked with this token, and — when
     * it names nobody — whether that is because it declined or because it never
     * answered.
     */
    public function forToken(string $token): PlexOwnerLookup
    {
        if ($this->config->serverUrl === '' || $token === '') {
            // No address to ask, or no credential to ask with. Nothing was
            // reached, and "check the server address" is the useful advice.
            return PlexOwnerLookup::unreachable();
        }

        try {
            // Error statuses are inspected rather than thrown: a 401 is an
            // answer about the account, and the rest are answers about the
            // address. Collapsing them into one exception is what this class
            // used to do.
            $response = $this->http->request('GET', $this->config->serverUrl . '/', [
                'headers' => [
                    'X-Plex-Token' => $token,
                    'Accept' => 'application/xml',
                ],
                'connect_timeout' => $this->config->connectTimeout,
                'timeout' => $this->config->requestTimeout,
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            // Nothing came back at all: refused, timed out, or unresolvable.
            return PlexOwnerLookup::unreachable();
        }

        $status = $response->getStatusCode();
        if ($status === 401 || $status === 403) {
            // Reached, and it declined the token. The account has no access to
            // this server, which is an answer about the account.
            return PlexOwnerLookup::anonymous();
        }

        if ($status < 200 || $status >= 300) {
            // Reached something, but not a Plex server willing to talk. Whether
            // it answered 404 or 502, what the user has to check is the address
            // and the server.
            return PlexOwnerLookup::unreachable();
        }

        return $this->readOwner((string) $response->getBody());
    }

    /**
     * The owner named in a server's root response.
     */
    private function readOwner(string $body): PlexOwnerLookup
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        // Not a Plex response. The address points at some other web
        // application, which is an address problem and not an account one.
        if (!$xml instanceof SimpleXMLElement || $xml->getName() !== 'MediaContainer') {
            return PlexOwnerLookup::unreachable();
        }

        $owner = isset($xml['myPlexUsername']) ? trim((string) $xml['myPlexUsername']) : '';

        // The server names itself in the same response. Taken here so a sign-in
        // knows what to call the connection without a second round trip — the
        // status in the header would otherwise have nothing to show until
        // somebody opened the connection screen.
        $name = isset($xml['friendlyName']) ? trim((string) $xml['friendlyName']) : '';

        // A Plex server that names no owner has answered without saying
        // anything Marquee can compare against. Fails closed, as before.
        return $owner !== ''
            ? PlexOwnerLookup::named($owner, $name !== '' ? $name : null)
            : PlexOwnerLookup::anonymous();
    }
}
