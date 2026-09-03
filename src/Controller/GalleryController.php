<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Database\PlexItemRepository;
use App\Poster\GalleryView;
use App\Poster\Poster;
use App\Poster\PosterCategory;
use App\Poster\PosterFactsIndex;
use App\Poster\PosterLibrary;
use App\Poster\SortOrder;
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
        // A set is addressed by the Plex rating key of the item it belongs to.
        // It and a search are alternatives, never combined: Related posters sends
        // one or the other, so a set present wins and the query is ignored.
        $setKey = isset($params['set']) && is_string($params['set']) ? trim($params['set']) : '';
        if ($setKey !== '') {
            $query = '';
        }
        // The poster the set was opened from, as "<category>/<filename>".
        //
        // OPTIONAL AND INERT. A set address without one — bookmarked, shared,
        // typed — renders exactly as it always did, and one naming a poster that
        // has since been deleted is treated as absent. It decides nothing about
        // which posters the set holds; membership is decided by the set alone.
        // All it buys is knowing which of a film's collections the user meant,
        // which is what lets the view name the others and derive a broader
        // search.
        $origin = isset($params['from']) && is_string($params['from']) ? trim($params['from']) : '';
        $page = isset($params['page']) && is_string($params['page']) ? max(1, (int) $params['page']) : 1;

        // Effective sort, in precedence order: a valid ?sort= wins; else a set
        // opens in release order; else the session's stored choice; else the
        // DEFAULT_SORT config default. The state carries what the toolbar's
        // buttons need besides the order in force — each field's remembered
        // direction.
        //
        // A set DEFAULTS the sort rather than overriding it: the control stays
        // live, and choosing another field re-orders the set instead of dropping
        // out of it. Nothing chosen while a set is open is recorded, so leaving
        // the set returns the library to the order the user chose for it.
        $inSet = $setKey !== '';
        $sortState = SortPreference::resolve(
            $this->session,
            $params,
            $this->posterConfig->defaultSort,
            $inSet ? SortOrder::Release : null,
            !$inSet,
        );
        $sort = $sortState->current;

        // Everything recorded about this view's posters, read ONCE per category
        // and then used by everything: the listing, the sort, the search, and the
        // template alike. This was six reads a category — the title, the year,
        // the season number, the related title, the sets, the timestamp — plus
        // two more inside the library while filtering. A filtered All view cost
        // twenty-four scans of the same rows.
        //
        // Read unconditionally, including the timestamp the date sort needs. It
        // was fetched only under that sort when it was a scan of its own; it is a
        // column of a query that now runs regardless, so conditioning it would
        // save nothing and add a branch.
        $facts = new PosterFactsIndex($this->factsFor($view));

        // The title Related posters would have searched had there been no set.
        // It is what a set's broader-search offer is derived from, and it is the
        // only thing the origin poster is used for down there — the library never
        // learns what a poster or an origin is.
        $originPoster = $setKey === '' ? null : $this->originPoster($origin);
        $originTitle = $originPoster === null ? null : $facts->relatedTitleFor($originPoster);

        $category = $view->category;
        $result = $category === null
            ? $this->library->browseAll($query, $page, $sort, $facts, $setKey, $originTitle)
            : $this->library->browse($category, $query, $page, $sort, $facts, $setKey, $originTitle);

        // Remember the section so Orphans/Import can send the user back to it.
        LastCategory::remember($this->session, $view);

        // Whether a poster is linked is a question about the connection as well
        // as the mapping: the mapping outlives a disconnection, but the actions
        // it enables do not, so every poster reads as unlinked while Plex is not
        // configured. The mapping itself comes from the facts above; this is the
        // gate over it.
        $plexConfigured = $this->plexConfig->isConfigured();

        return $this->twig->render($response, 'gallery.html.twig', [
            'view' => $view,
            'tabs' => $this->tabs($view),
            'is_all_view' => $view->isAll(),
            'query' => $query,
            'result' => $result,
            'flash' => $this->flash->pull(),
            'plex_configured' => $plexConfigured,
            'facts' => $facts,
            'set_key' => $setKey,
            // Carried on the links this view renders, so a set survives a tab
            // change and a sort press with its origin intact.
            'set_from' => $origin,
            // The OTHER sets the origin poster belongs to, named where known.
            // The origin poster's own sets, not the union over every member: a
            // film in a large collection commonly belongs to several others, and
            // listing those answers a question nobody asked. This answers the one
            // they did — I clicked this film and got King Kong; where did
            // MonsterVerse go?
            'also_in' => $originPoster === null ? [] : $this->alsoIn($originPoster, $setKey, $facts),
            // What to call the set on screen. The item naming it may have no
            // poster of its own — a collection whose artwork was never imported —
            // and the view then reports the set without a name rather than
            // failing, which is why this is nullable.
            'set_title' => $setKey === '' ? null : $this->plexItems->titleForRatingKey($setKey),
            'sort' => $sort->value,
            'sort_state' => $sortState,
        ]);
    }

    /**
     * The sets the origin poster belongs to other than the one being shown, as
     * [key, title] pairs with a null title where no name is known.
     *
     * Empty whenever there is nothing to say: no origin poster, an origin that no
     * longer resolves, or a poster belonging to exactly this one set — which is
     * the overwhelmingly common case and the reason the link opens a set
     * directly rather than offering a choice first.
     *
     * @return list<array{key: string, title: string|null}>
     */
    private function alsoIn(Poster $poster, string $setKey, PosterFactsIndex $facts): array
    {
        $others = array_values(array_filter(
            $facts->for($poster)->setKeys,
            static fn (string $key): bool => $key !== $setKey,
        ));
        if ($others === []) {
            return [];
        }

        // One read for the whole list: a film in five collections must not cost
        // five queries to name four of them.
        $titles = $this->plexItems->titlesForRatingKeys($others);

        return array_map(
            static fn (string $key): array => ['key' => $key, 'title' => $titles[$key] ?? null],
            $others,
        );
    }

    /**
     * The poster a "<category>/<filename>" origin names, or null.
     *
     * Never touches the filesystem: the value is only ever used to look facts up
     * by category and filename, and to be echoed back into a link. An
     * unrecognised category or a poster with no recorded facts resolves to null,
     * which is how a deleted or renamed poster degrades to the plain set view.
     */
    private function originPoster(string $origin): ?Poster
    {
        if ($origin === '' || !str_contains($origin, '/')) {
            return null;
        }

        [$slug, $filename] = explode('/', $origin, 2);
        $category = PosterCategory::tryFrom($slug);

        return $category === null || $filename === '' ? null : new Poster($category, $filename, 0, 0);
    }

    /**
     * One read per category the view holds — four for All, one otherwise.
     *
     * @return array<string, array<string, \App\Poster\PosterFacts>>
     */
    private function factsFor(GalleryView $view): array
    {
        $facts = [];
        foreach ($view->categories() as $cat) {
            $facts[$cat->value] = $this->plexItems->factsForCategory($cat->value);
        }

        return $facts;
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
