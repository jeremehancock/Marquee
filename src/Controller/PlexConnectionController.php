<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plex\Connection\PlexConnectionState;
use App\Plex\Connection\PlexConnectionStatus;
use App\Plex\Connection\PlexSignInException;
use App\Plex\Connection\PlexSignInService;
use App\Plex\Connection\PlexSignInStatus;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Signing in to and out of Plex.
 *
 * These routes sit behind the application's own authentication like every other
 * route; connecting Marquee to Plex is an action an already-signed-in user
 * takes, not a way of signing in to Marquee.
 *
 * No response from here ever carries a Plex token. The connection is described
 * by the server's name and by which source supplied the credential — never by
 * the credential.
 */
final class PlexConnectionController
{
    public function __construct(
        private readonly PlexSignInService $signIn,
        private readonly PlexConnectionStatus $status,
        private readonly Flash $flash,
    ) {
    }

    /**
     * Begin a sign-in and hand back the address for the browser to open.
     */
    public function start(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $url = $this->signIn->start();
        } catch (PlexSignInException $e) {
            return $this->json($response->withStatus(502), ['error' => $e->getMessage()]);
        }

        return $this->json($response, ['authUrl' => $url]);
    }

    /**
     * Report whether the outstanding sign-in has been approved yet.
     *
     * It takes no identifier. The request being polled is the one recorded in
     * this session, which is what stops one browser session from completing a
     * sign-in another one began.
     */
    public function poll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $status = $this->signIn->poll();
        } catch (PlexSignInException $e) {
            return $this->json($response->withStatus(502), ['error' => $e->getMessage()]);
        }

        $payload = ['status' => $status->value];
        if ($status === PlexSignInStatus::Completed) {
            // Naming the server here is what confirms the new credential
            // actually reaches Plex, without disclosing any part of it.
            $payload['connection'] = $this->describe($this->status->refresh());
        }

        return $this->json($response, $payload);
    }

    /**
     * Forget the stored token. The client identifier and the poster wall's
     * signing secret survive, so a later sign-in is the same device and tokens
     * already on a running wall keep working.
     */
    public function signOut(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->signIn->signOut();
        $this->flash->add('success', 'Signed out of Plex.');

        return $response->withHeader('Location', '/plex')->withStatus(302);
    }

    /**
     * The connection as JSON. Contains no token, by construction: only the
     * server's name and which source is in use.
     *
     * @return array<string, mixed>
     */
    private function describe(PlexConnectionState $state): array
    {
        return [
            'connected' => $state->isConnected(),
            'signedIn' => $state->isSignedIn(),
            'overridden' => $state->isOverridden(),
            'serverName' => $state->serverName,
            'summary' => $state->summary(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(ResponseInterface $response, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
