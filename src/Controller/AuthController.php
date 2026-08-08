<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthMiddleware;
use App\Auth\SessionAuthenticator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Logging out.
 *
 * There is no login action here. Marquee is entered by signing in to Plex, which
 * {@see PlexConnectionController} owns, so what is left of this controller is the
 * way back out.
 */
final class AuthController
{
    public function __construct(private readonly SessionAuthenticator $authenticator)
    {
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticator->logout();

        return $response->withHeader('Location', AuthMiddleware::SIGN_IN_PATH)->withStatus(302);
    }
}
