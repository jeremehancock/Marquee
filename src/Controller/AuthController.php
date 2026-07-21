<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\SessionAuthenticator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * Login, logout, and the login form.
 */
final class AuthController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly SessionAuthenticator $authenticator,
    ) {
    }

    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->authenticator->isAuthenticated()) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        return $this->twig->render($response, 'login.html.twig');
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $username = isset($body['username']) && is_string($body['username']) ? $body['username'] : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

        if ($this->authenticator->attempt($username, $password)) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        return $this->twig->render(
            $response->withStatus(401),
            'login.html.twig',
            ['error' => 'Invalid username or password.'],
        );
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticator->logout();

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
