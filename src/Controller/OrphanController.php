<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plex\Orphan\OrphanService;
use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Poster\PosterCategory;
use App\Support\Flash;
use App\Support\LastCategory;
use App\Support\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

/**
 * The orphans page: list orphaned posters and delete them.
 */
final class OrphanController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly PlexClient $plex,
        private readonly OrphanService $orphans,
        private readonly Flash $flash,
        private readonly SessionInterface $session,
    ) {
    }

    /**
     * The page shell: renders immediately so the client can show a loading
     * state. The slow scan runs separately via {@see results()}.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'orphans.html.twig', [
            'configured' => $this->plex->isConfigured(),
            'flash' => $this->flash->pull(),
            'back_url' => LastCategory::backUrl($this->session),
        ]);
    }

    /**
     * The orphan scan, rendered as a fragment the shell fetches after it paints.
     */
    public function results(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $orphans = [];
        $error = null;

        try {
            $orphans = $this->orphans->findOrphans();
        } catch (PlexException $e) {
            $error = $e->getMessage();
        }

        return $this->twig->render($response, 'orphans/_results.html.twig', [
            'orphans' => $orphans,
            'error' => $error,
        ]);
    }

    public function deleteAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $count = $this->orphans->deleteAll();
            $this->flash->add('success', sprintf('Removed %d orphaned poster%s.', $count, $count === 1 ? '' : 's'));
        } catch (PlexException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $response->withHeader('Location', '/orphans')->withStatus(302);
    }

    /**
     * Delete a single orphan, named by its category and filename.
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $categorySlug = isset($body['category']) && is_string($body['category']) ? $body['category'] : '';
        $filename = isset($body['filename']) && is_string($body['filename']) ? $body['filename'] : '';

        $category = PosterCategory::fromSlug($categorySlug);
        if ($category === null) {
            throw new HttpNotFoundException($request);
        }

        try {
            if ($this->orphans->delete($category, $filename)) {
                $this->flash->add('success', 'Orphan deleted.');
            } else {
                $this->flash->add('error', 'That orphan could not be deleted.');
            }
        } catch (PlexException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $response->withHeader('Location', '/orphans')->withStatus(302);
    }
}
