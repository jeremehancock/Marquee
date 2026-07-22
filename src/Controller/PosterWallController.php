<?php

declare(strict_types=1);

namespace App\Controller;

use App\Poster\Poster;
use App\Poster\Wall\PosterWallService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * The Poster Wall: a full-screen page and a random-batch endpoint that feeds it.
 */
final class PosterWallController
{
    private const BATCH_SIZE = 30;

    public function __construct(
        private readonly Twig $twig,
        private readonly PosterWallService $wall,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'wall.html.twig');
    }

    public function posters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $urls = array_map(
            static fn (Poster $poster): string => $poster->url(),
            $this->wall->randomPosters(self::BATCH_SIZE),
        );

        $response->getBody()->write(
            json_encode(['posters' => $urls], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );

        return $response->withHeader('Content-Type', 'application/json');
    }
}
