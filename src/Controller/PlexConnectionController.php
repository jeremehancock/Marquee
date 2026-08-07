<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plex\Connection\PlexConnectionStatus;
use App\Plex\Connection\PlexSignInException;
use App\Plex\Connection\PlexSignInService;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * The Plex connection screen, and signing in to and out of Plex.
 *
 * This is the only place the Plex connection is managed. It is also where the
 * connection gate sends anyone who has not connected yet, so it must stay
 * reachable while the rest of the application is not.
 *
 * These routes sit behind the application's own authentication like every other
 * route; connecting Marquee to Plex is an action an already-signed-in user
 * takes, not a way of signing in to Marquee.
 *
 * No response from here ever carries a Plex token. The connection is described
 * by the server's name — never by the credential.
 */
final class PlexConnectionController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly PlexSignInService $signIn,
        private readonly PlexConnectionStatus $status,
        private readonly Flash $flash,
    ) {
    }

    /**
     * The connection screen. This is the one page that asks Plex its name
     * rather than using the cached one — it exists to describe the connection,
     * and it is where a user lands when something is wrong.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'connect.html.twig', [
            'connection' => $this->status->refresh(),
            'flash' => $this->flash->pull(),
        ]);
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

        // Only the status. Describing the new connection here would be wrong as
        // well as redundant: configuration is resolved once when the container
        // is built, so this request still holds the pre-sign-in view and would
        // report "not connected" moments after connecting. The browser reloads
        // on success, and the fresh request reads the stored token correctly.
        return $this->json($response, ['status' => $status->value]);
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

        return $response->withHeader('Location', '/connect')->withStatus(302);
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
