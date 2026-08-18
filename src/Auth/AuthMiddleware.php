<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Guards every route except the public ones, sending an unauthenticated visitor
 * to the screen where signing in to Plex is offered.
 *
 * That screen is public because signing in to Plex is how a session is
 * obtained. So are the two routes behind it: one starts an authorization
 * request, the other reports whether it has been approved. Disconnecting is
 * deliberately not among them — it destroys the connection, which is something
 * only a signed-in user may do.
 *
 * `/connect` is deliberately not public either, even though it renders the same
 * screen. It is the connection view, and a visitor with no session has no
 * connection to look at; this gate turning them away is what sends them to the
 * URL that names what they actually need.
 *
 * The Poster Wall is intentionally public: it is meant for an unattended
 * display (a spare monitor or TV) that should show posters and now-playing
 * without anyone signing in on the device. Its endpoints expose only poster
 * art and the current now-playing details, never any management action.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    /** Where an unauthenticated visitor is sent: the screen that offers sign-in. */
    public const SIGN_IN_PATH = '/login';

    /** @var list<string> */
    private array $publicPaths = [
        '/health',
        self::SIGN_IN_PATH,
        '/logout',
        '/manifest.webmanifest',
        // Claiming comes before there is anyone to authenticate: it is what
        // decides who may sign in at all. It is not a hole in this gate, because
        // ClaimMiddleware — which runs outside this one — closes every other
        // route while an install is unclaimed, and redirects this one away once
        // it is claimed.
        ClaimMiddleware::CLAIM_PATH,
        '/plex/connection/sign-in',
        '/plex/connection/status',
        '/wall',
    ];

    /** @var list<string> */
    private array $publicPrefixes = ['/assets/', '/wall/'];

    /**
     * Public paths that read no session state, and so are served without
     * starting one.
     *
     * These MUST be a subset of the public paths above. The control flow in
     * {@see process()} enforces that structurally, and a test asserts it
     * again, because this list is the one place in the gate where an edit could
     * plausibly become a security mistake.
     *
     * `/health` is polled by the container runtime every thirty seconds for the
     * life of the container, and the wall polls for now-playing and rotates
     * posters continuously on an unattended display. Between them they are the
     * most requested routes in the product by orders of magnitude, and neither
     * reads a session. Starting one anyway did not merely waste a file: the
     * store collects expired sessions probabilistically *during session
     * startup*, so these two routes became the dominant trigger for the sweep
     * that evicted real signed-in users. A route that needs no session should
     * not be able to sign somebody out.
     *
     * Public rather than private so the invariant above can be asserted
     * directly rather than through reflection or a duplicated list. It is
     * routing policy, not a secret.
     *
     * @var list<string>
     */
    public const SESSIONLESS_PATHS = ['/health', '/manifest.webmanifest', '/wall'];

    /** @var list<string> */
    public const SESSIONLESS_PREFIXES = ['/assets/', '/wall/'];

    public function __construct(
        private readonly SessionAuthenticator $authenticator,
        private readonly SessionInterface $session,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $public = $this->isPublic($path);

        // Nested inside `$public`, never beside it. A path listed as
        // session-less but *not* public does not reach this return: the session
        // starts below and the gate runs, exactly as it did before. So the worst
        // a bad edit to those lists can do is create a session nobody reads —
        // it cannot open a protected route, which is the only way this
        // optimisation could have become a security hole.
        if ($public && !$this->needsSession($path)) {
            return $handler->handle($request);
        }

        $this->session->start();

        if ($public || $this->authenticator->isAuthenticated()) {
            return $handler->handle($request);
        }

        return (new Response())
            ->withHeader('Location', self::SIGN_IN_PATH)
            ->withStatus(302);
    }

    /**
     * Whether serving this path reads or writes session state.
     *
     * Separate from {@see isPublic()} on purpose: whether an anonymous visitor
     * may reach a route and whether serving it needs a session are different
     * questions, and conflating them is what made the health check able to
     * evict a login.
     *
     * The sign-in routes answer yes — `/login` renders the layout, which asks
     * `CsrfGuard` for a token and so writes to the session; `/plex/connection/
     * sign-in` stores the authorization request and `/plex/connection/status`
     * reads it back, which is the whole of "another session has no request
     * recorded"; `/logout` needs a session to destroy. The wall answers no, and
     * that is by construction rather than luck: `wall.html.twig` is standalone
     * rather than extending the layout, so it never asks for a token, and
     * `StreamToken` signs poster paths with an HMAC precisely so the proxy can
     * recover them without a server-side session.
     */
    private function needsSession(string $path): bool
    {
        if (in_array($path, self::SESSIONLESS_PATHS, true)) {
            return false;
        }

        foreach (self::SESSIONLESS_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function isPublic(string $path): bool
    {
        if (in_array($path, $this->publicPaths, true)) {
            return true;
        }

        foreach ($this->publicPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
