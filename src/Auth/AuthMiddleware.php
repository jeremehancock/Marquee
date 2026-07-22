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
 * Guards every route except the public ones (health, login/logout, assets),
 * redirecting unauthenticated visitors to the login page.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $publicPaths = ['/health', '/login', '/logout', '/manifest.webmanifest'];

    /** @var list<string> */
    private array $publicPrefixes = ['/assets/'];

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
            ->withHeader('Location', '/login')
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
