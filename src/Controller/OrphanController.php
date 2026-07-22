<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plex\Orphan\OrphanService;
use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Support\Flash;
use App\Support\LastCategory;
use App\Support\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $configured = $this->plex->isConfigured();
        $orphans = [];
        $error = null;

        if ($configured) {
            try {
                $orphans = $this->orphans->findOrphans();
            } catch (PlexException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->twig->render($response, 'orphans.html.twig', [
            'configured' => $configured,
            'orphans' => $orphans,
            'error' => $error,
            'flash' => $this->flash->pull(),
            'back_url' => LastCategory::backUrl($this->session),
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
}
