<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use App\Support\Scalar;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use JsonException;
use Throwable;

/**
 * Talks to plex.tv's PIN authorization API — the only part of Marquee that
 * contacts Plex's hosted service rather than the user's own server.
 *
 * The flow is deliberately the polling variant rather than the redirect one.
 * A redirect back into Marquee would require knowing this install's externally
 * reachable URL, which is exactly what a reverse proxy makes unguessable, and
 * would arrive as a cross-site navigation that a strict session cookie would
 * not survive. Polling needs neither: the browser talks to Plex, this class
 * talks to Plex, and nothing has to find its way back in.
 */
final class PlexPinClient
{
    private const PINS_URL = 'https://plex.tv/api/v2/pins';
    private const AUTH_URL = 'https://app.plex.tv/auth';

    /** Fallback lifetime when Plex does not say, in seconds. */
    private const DEFAULT_LIFETIME = 900;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly PlexConnectionStore $store,
        private readonly string $version,
    ) {
    }

    /**
     * Ask Plex for a new authorization request.
     */
    public function create(): PlexPin
    {
        $payload = $this->send('POST', self::PINS_URL . '?strong=true');

        $id = Scalar::intOrNull($payload['id'] ?? null);
        $code = Scalar::stringOrNull($payload['code'] ?? null);
        if ($id === null || $code === null) {
            throw PlexSignInException::unexpectedResponse();
        }

        $lifetime = Scalar::intOrNull($payload['expiresIn'] ?? null) ?? self::DEFAULT_LIFETIME;

        return new PlexPin($id, $code, time() + max(1, $lifetime));
    }

    /**
     * The token for an approved authorization request, or null while the user
     * has not approved it yet.
     *
     * @throws PlexSignInException when the request no longer exists or plex.tv
     *                             cannot be reached
     */
    public function token(PlexPin $pin): ?string
    {
        $payload = $this->send('GET', self::PINS_URL . '/' . $pin->id . '?code=' . rawurlencode($pin->code));

        // Plex reports "approved yet?" by whether this field is populated, not
        // by a status code, so a null here is the ordinary waiting state.
        return Scalar::stringOrNull($payload['authToken'] ?? null);
    }

    /**
     * The address the user's browser opens to approve the request.
     *
     * The fragment is significant: Plex's sign-in page reads these values
     * client-side, so they never reach a server log.
     */
    public function authorizationUrl(PlexPin $pin): string
    {
        return self::AUTH_URL . '#?' . http_build_query([
            'clientID' => $this->store->clientIdentifier(),
            'code' => $pin->code,
            'context[device][product]' => 'Marquee',
            'context[device][version]' => $this->version,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function send(string $method, string $url): array
    {
        try {
            $response = $this->http->request($method, $url, [
                'headers' => $this->headers(),
                'connect_timeout' => 10,
                'timeout' => 30,
                'http_errors' => true,
            ]);
            $body = (string) $response->getBody();
        } catch (BadResponseException $e) {
            // Plex forgets a pin once it is exchanged or ages out, and answers
            // 404 from then on. That is the expiry signal, not a fault.
            throw $e->getResponse()->getStatusCode() === 404
                ? PlexSignInException::expired($e)
                : PlexSignInException::unavailable($e);
        } catch (Throwable $e) {
            throw PlexSignInException::unavailable($e);
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw PlexSignInException::unexpectedResponse();
        }

        if (!is_array($decoded)) {
            throw PlexSignInException::unexpectedResponse();
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Identify this install to Plex.
     *
     * The client identifier is generated once and kept, because Plex records
     * one authorized device per identifier: a value that changed per sign-in
     * would litter the user's account with duplicate Marquee entries. The
     * product and device names are what the user sees listed there.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Plex-Client-Identifier' => $this->store->clientIdentifier(),
            'X-Plex-Product' => 'Marquee',
            'X-Plex-Version' => $this->version,
            'X-Plex-Device' => 'Marquee',
            'X-Plex-Platform' => 'Web',
        ];
    }
}
