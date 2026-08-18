<?php

declare(strict_types=1);

namespace App\Auth\Claim;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use SimpleXMLElement;
use Throwable;

/**
 * Asks an address whether a Plex server is listening there, before the claim
 * commits to it.
 *
 * **This is a usability feature and not a security control.** It catches a typo,
 * a wrong port, and a host that is not running Plex — the three ways a first-run
 * address goes wrong. It cannot do more than that, and must never be described as
 * if it does: the person entering the address chose which server it names, so a
 * server they control, or a stub returning a plausible response, satisfies this
 * check completely. What establishes that an install belongs to someone is the
 * claim code and the Plex sign-in that follows, not this.
 *
 * `/identity` rather than `/`. It is the one endpoint a Plex server answers
 * without a token, which is the situation here: nothing has been stored, nobody
 * has signed in, and there is no credential to send. It reports a machine
 * identifier and a version and no friendly name, so what can be echoed back is
 * "something that looks like Plex answered, and here is its version" — enough to
 * tell a right address from a wrong one, which is all this is for.
 *
 * Timeouts are short and fixed rather than drawn from the Plex configuration:
 * this runs before any configuration exists, and a first-run screen that hangs
 * for the configured request timeout on a mistyped address would read as broken.
 */
final class ClaimServerProbe
{
    private const CONNECT_TIMEOUT = 5;
    private const REQUEST_TIMEOUT = 5;

    public function __construct(private readonly ClientInterface $http)
    {
    }

    /**
     * A short description of what answered, or null when nothing usable did.
     */
    public function describe(string $serverUrl): ?string
    {
        $url = rtrim(trim($serverUrl), '/');
        if ($url === '') {
            return null;
        }

        try {
            new Uri($url);
        } catch (InvalidArgumentException) {
            return null;
        }

        try {
            $response = $this->http->request('GET', $url . '/identity', [
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::REQUEST_TIMEOUT,
                'http_errors' => false,
                'headers' => ['Accept' => 'application/xml'],
            ]);
        } catch (Throwable) {
            // Every failure means the same thing here — nothing usable answered —
            // and this runs on the one screen a brand-new install has. An
            // exception escaping would replace the first thing a user ever sees
            // with a stack trace, over an address they can simply retype. Broader
            // than GuzzleException on purpose: a malformed address can raise from
            // inside the client before a request is ever attempted.
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return self::describeBody((string) $response->getBody());
    }

    /**
     * Whether an address is worth committing to.
     */
    public function isPlexServer(string $serverUrl): bool
    {
        return $this->describe($serverUrl) !== null;
    }

    private static function describeBody(string $body): ?string
    {
        if (trim($body) === '') {
            return null;
        }

        try {
            $previous = libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($body);
            libxml_use_internal_errors($previous);
        } catch (Throwable) {
            libxml_use_internal_errors(false);

            return null;
        }

        // A machine identifier is what makes this a Plex server rather than any
        // web server that happens to answer 200 with XML.
        $identifier = isset($xml['machineIdentifier']) ? trim((string) $xml['machineIdentifier']) : '';
        if ($identifier === '') {
            return null;
        }

        $version = isset($xml['version']) ? trim((string) $xml['version']) : '';

        return $version !== ''
            ? sprintf('Plex Media Server %s', $version)
            : 'Plex Media Server';
    }
}
