<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * Renders the authenticated landing page. Poster-library content arrives in a
 * later phase; for now this confirms the shell and layout work end to end.
 */
final class HomeController
{
    public function __construct(private readonly Twig $twig)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'home.html.twig');
    }
}
