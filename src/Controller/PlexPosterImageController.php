<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Plex\SignedImagePath;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * Streams one of an item's Plex-held poster candidates to the change dialog.
 *
 * Plex image URLs carry the Plex token, so the grid cannot address them
 * directly. Each candidate is named instead by an opaque token that carries its
 * Plex image path signed by {@see SignedImagePath}; this proxy verifies the
 * signature, fetches the bytes with the server's own credentials, and returns
 * them. A token the application did not sign is refused, so the route cannot be
 * used to pull arbitrary paths off the Plex server.
 *
 * Unlike the wall's equivalent this route is **not** public. The wall is meant
 * for an unattended display and falls back to a placeholder so a screen never
 * renders blank; this serves a dialog behind the login, where a candidate that
 * cannot be fetched should simply fail to load and let the card's placeholder
 * animation stop.
 */
final class PlexPosterImageController
{
    public function __construct(
        private readonly PlexClient $plex,
        private readonly SignedImagePath $paths,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $path = $this->paths->pathFor($args['token'] ?? '');
        if ($path === null) {
            throw new HttpNotFoundException($request);
        }

        try {
            $bytes = $this->plex->imageAt($path);
        } catch (PlexException) {
            throw new HttpNotFoundException($request);
        }

        $response->getBody()->write($bytes);

        // Plex serves these as JPEG in practice, and the type only affects
        // display: applying a candidate re-fetches the bytes server-side and
        // reads the real type from the image itself.
        return $response
            ->withHeader('Content-Type', 'image/jpeg')
            ->withHeader('Cache-Control', 'private, max-age=300');
    }
}
