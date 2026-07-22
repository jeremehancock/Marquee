<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\PlexConfig;
use App\Database\PlexItemRepository;
use App\Poster\PosterCategory;
use App\Poster\PosterLibrary;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

/**
 * The poster gallery: category tabs, search, and a paginated grid.
 */
final class GalleryController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly PosterLibrary $library,
        private readonly Flash $flash,
        private readonly PlexConfig $plexConfig,
        private readonly PlexItemRepository $plexItems,
    ) {
    }

    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Location', '/library/' . PosterCategory::default()->value)
            ->withStatus(302);
    }

    /**
     * @param array<string, string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = PosterCategory::fromSlug($args['category'] ?? '');
        if ($category === null) {
            throw new HttpNotFoundException($request);
        }

        $params = $request->getQueryParams();
        $query = isset($params['q']) && is_string($params['q']) ? $params['q'] : '';
        $page = isset($params['page']) && is_string($params['page']) ? max(1, (int) $params['page']) : 1;

        $result = $this->library->browse($category, $query, $page);

        $plexConfigured = $this->plexConfig->isConfigured();
        $linked = $plexConfigured ? $this->plexItems->filenamesForCategory($category->value) : [];

        return $this->twig->render($response, 'gallery.html.twig', [
            'category' => $category,
            'categories' => PosterCategory::all(),
            'query' => $query,
            'result' => $result,
            'flash' => $this->flash->pull(),
            'plex_configured' => $plexConfigured,
            'linked' => $linked,
        ]);
    }
}
