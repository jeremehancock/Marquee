<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\SessionAuthenticator;
use App\Config\AuthConfig;
use App\Plex\Connection\PlexConnectionStatus;
use App\Plex\Connection\PlexSignInException;
use App\Plex\Connection\PlexSignInService;
use App\Plex\Connection\PlexSignInStatus;
use App\Support\Flash;
use App\Support\LastCategory;
use App\Support\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * The one screen a visitor meets before Marquee will do anything: where you sign
 * in, and where the Plex connection is managed.
 *
 * These were two screens. They collapsed because signing in to Plex is now how
 * Marquee is entered, so both rendered the same single action and differed only
 * in which gate had redirected there.
 *
 * Starting and polling a sign-in are reachable without a session — they are how
 * a session is obtained. Disconnecting is not: it destroys the connection, which
 * only a signed-in user may do.
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
        private readonly SessionAuthenticator $authenticator,
        private readonly AuthConfig $auth,
        private readonly Flash $flash,
        private readonly SessionInterface $session,
    ) {
    }

    /**
     * The sign-in and connection screen.
     *
     * A signed-in visitor gets the live connection: this is the page that exists
     * to describe it, and where someone lands when something is wrong. A visitor
     * who is not signed in gets the cached view instead. The screen is public
     * now, and asking Plex its name on every anonymous request would turn a page
     * anyone can load into an outbound request anyone can cause — with the
     * server name being something they are not shown regardless.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $signedIn = $this->authenticator->isAuthenticated();

        return $this->twig->render($response, 'connect.html.twig', [
            'signed_in' => $signedIn,
            'connection' => $signedIn ? $this->status->refresh() : $this->status->current(),
            'flash' => $this->flash->pull(),
            // Only meaningful once connected — while the gate is up there is no
            // gallery to go back to, and the template hides the link.
            'back_url' => LastCategory::backUrl($this->session),
            'obsolete_credentials' => $this->auth->obsoleteEnvCredentials,
            'obsolete_bypass' => $this->auth->obsoleteEnvBypass,
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

        if ($status === PlexSignInStatus::Completed) {
            // The browser leaves for the gallery next, so the confirmation has
            // to travel with it. Deliberately unnamed: configuration is resolved
            // once when the container is built, so this request still holds the
            // pre-sign-in view and could not name the server without lying.
            $this->flash->add('success', 'Signed in to Plex.');
        }

        // Only the status, for the same reason.
        return $this->json($response, ['status' => $status->value]);
    }

    /**
     * Forget the stored token. The client identifier and the poster wall's
     * signing secret survive, so a later sign-in is the same device and tokens
     * already on a running wall keep working.
     *
     * The session is left alone. Disconnecting and logging out are different
     * actions, and collapsing them here would take away the screen that is about
     * to explain what just happened.
     */
    public function signOut(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->signIn->signOut();
        $this->flash->add('success', 'Disconnected from Plex. Scheduled imports have stopped.');

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
