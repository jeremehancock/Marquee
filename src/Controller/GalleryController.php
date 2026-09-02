<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Database\PlexItemRepository;
use App\Poster\GalleryView;
use App\Poster\PosterCategory;
use App\Poster\PosterLibrary;
use App\Poster\SortField;
use App\Support\Flash;
use App\Support\LastCategory;
use App\Support\Session\SessionInterface;
use App\Support\SortPreference;
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
        private readonly PosterConfig $posterConfig,
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

        // Effective sort: a valid ?sort= wins and is remembered, else the
        // session's stored choice, else the DEFAULT_SORT config default. The
        // state carries what the toolbar's buttons need besides the order in
        // force — each field's remembered direction.
        $sortState = SortPreference::resolve($this->session, $params, $this->posterConfig->defaultSort);
        $sort = $sortState->current;

        // The date field needs each poster's Plex "added at" timestamp, keyed by
        // category then filename. Only fetched when it will be used — which is
        // either direction of that field, not just the newest-first one.
        $addedAt = [];
        if ($sort->field() === SortField::DateAdded) {
            foreach ($view->categories() as $cat) {
                $addedAt[$cat->value] = $this->plexItems->addedAtForCategory($cat->value);
            }
        }

        $category = $view->category;
        $result = $category === null
            ? $this->library->browseAll($query, $page, $sort, $addedAt)
            : $this->library->browse($category, $query, $page, $sort, $addedAt);

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

        // What each poster's title needs, keyed by category then filename: the
        // title Plex reported (the filename is a lossy copy of it) and the release
        // year (shown in parentheses). Both are kept even when Plex is not
        // currently configured — the posters and their mappings outlive the
        // connection. Reads only; rendering a title never writes.
        //
        // The third map is what Related posters searches for: a season answers
        // with its show's title so the search gathers the show and its sibling
        // seasons, everything else with its own. Carries no year, unlike the
        // caption — a query naming one would narrow the search back to the single
        // poster the action started from.
        $plexTitles = [];
        $plexYears = [];
        $relatedTitles = [];
        foreach ($view->categories() as $cat) {
            $plexTitles[$cat->value] = $this->plexItems->titlesForCategory($cat->value);
            $plexYears[$cat->value] = $this->plexItems->yearsForCategory($cat->value);
            $relatedTitles[$cat->value] = $this->plexItems->relatedTitlesForCategory($cat->value);
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
            'plex_titles' => $plexTitles,
            'plex_years' => $plexYears,
            'related_titles' => $relatedTitles,
            'sort' => $sort->value,
            'sort_state' => $sortState,
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
