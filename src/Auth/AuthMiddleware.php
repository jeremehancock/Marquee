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
        '/plex/connection/sign-in',
        '/plex/connection/status',
        '/wall',
    ];

    /** @var list<string> */
    private array $publicPrefixes = ['/assets/', '/wall/'];

    public function __construct(
        private readonly SessionAuthenticator $authenticator,
        private readonly SessionInterface $session,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->session->start();

        $path = $request->getUri()->getPath();
        if ($this->isPublic($path) || $this->authenticator->isAuthenticated()) {
            return $handler->handle($request);
        }

        return (new Response())
            ->withHeader('Location', self::SIGN_IN_PATH)
            ->withStatus(302);
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
