<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\PlexConfig;
use App\Database\PlexItemRepository;
use App\Poster\GalleryView;
use App\Poster\PosterCategory;
use App\Poster\PosterLibrary;
use App\Support\Flash;
use App\Support\LastCategory;
use App\Support\Session\SessionInterface;
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
        private readonly SessionInterface $session,
    ) {
    }

    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Location', '/library/' . GalleryView::ALL_SLUG)
            ->withStatus(302);
    }

    /**
     * @param array<string, string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $view = GalleryView::fromSlug($args['category'] ?? '');
        if ($view === null) {
            throw new HttpNotFoundException($request);
        }

        $params = $request->getQueryParams();
        $query = isset($params['q']) && is_string($params['q']) ? $params['q'] : '';
        $page = isset($params['page']) && is_string($params['page']) ? max(1, (int) $params['page']) : 1;

        $category = $view->category;
        $result = $category === null
            ? $this->library->browseAll($query, $page)
            : $this->library->browse($category, $query, $page);

        // Remember the section so Orphans/Import can send the user back to it.
        LastCategory::remember($this->session, $view);

        // Linked filenames are keyed by category: in the All view a filename is
        // only unique within its own category, so gather one list per category.
        $plexConfigured = $this->plexConfig->isConfigured();
        $linked = [];
        if ($plexConfigured) {
            foreach ($view->categories() as $cat) {
                $linked[$cat->value] = $this->plexItems->filenamesForCategory($cat->value);
            }
        }

        return $this->twig->render($response, 'gallery.html.twig', [
            'view' => $view,
            'tabs' => $this->tabs($view),
            'is_all_view' => $view->isAll(),
            'query' => $query,
            'result' => $result,
            'flash' => $this->flash->pull(),
            'plex_configured' => $plexConfigured,
            'linked' => $linked,
        ]);
    }

    /**
     * The category tab strip: All first, then the four categories.
     *
     * @return list<array{value: string, label: string, active: bool}>
     */
    private function tabs(GalleryView $active): array
    {
        $tabs = [[
            'value' => GalleryView::ALL_SLUG,
            'label' => 'All',
            'active' => $active->isAll(),
        ]];

        foreach (PosterCategory::all() as $category) {
            $tabs[] = [
                'value' => $category->value,
                'label' => $category->label(),
                'active' => !$active->isAll() && $active->value === $category->value,
            ];
        }

        return $tabs;
    }
}
