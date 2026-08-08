<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Tests\AppTestCase;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\App;

final class GalleryTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
    }

    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function app(): App
    {
        return $this->makeSignedInApp(['POSTERS_DIR' => $this->postersDir]);
    }

    private function writePoster(string $filename): void
    {
        $this->writePng($this->postersDir . '/movies/' . $filename);
    }

    private function writePosterIn(string $category, string $filename): void
    {
        $dir = $this->postersDir . '/' . $category;
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        $this->writePng($dir . '/' . $filename);
    }

    public function testHomeRedirectsToAll(): void
    {
        $response = $this->get($this->app(), '/');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/library/all', $response->getHeaderLine('Location'));
    }

    public function testGalleryListsPosters(): void
    {
        $this->writePoster('Solaris.png');

        $response = $this->get($this->app(), '/library/movies');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Solaris', $body);
        self::assertStringContainsString('Movies', $body);
    }

    public function testUnknownCategoryReturns404(): void
    {
        self::assertSame(404, $this->get($this->app(), '/library/books')->getStatusCode());
    }

    /**
     * A-Z as the user actually sees it: the seasons must appear in the rendered
     * page in numeric order, not 1, 10, 11, 2.
     */
    public function testAlphabeticalGalleryRendersSeasonsInNumericOrder(): void
    {
        foreach (['11', '2', '10', '1'] as $season) {
            $this->writePosterIn('tv-seasons', 'Breaking Bad - Season ' . $season . '.png');
        }

        $body = (string) $this->get($this->app(), '/library/tv-seasons?sort=alphabetical')->getBody();

        $positions = [];
        foreach (['1', '2', '10', '11'] as $season) {
            $position = strpos($body, 'Breaking Bad - Season ' . $season . '.png');
            self::assertIsInt($position, 'Season ' . $season . ' must be rendered');
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions, 'Seasons must render in numeric order');
    }

    public function testSearchFiltersAViewAndSummarisesTheQuery(): void
    {
        $this->writePoster('Solaris.png');
        $this->writePoster('Stalker.png');

        $body = (string) $this->get($this->app(), '/library/movies?q=solaris')->getBody();

        // The grid is filtered to the matching poster only.
        self::assertStringContainsString('Solaris', $body);
        self::assertStringNotContainsString('Stalker', $body);
        // The filtered-state summary names the query, count, and view.
        self::assertStringContainsString('1 match for', $body);
        self::assertStringContainsString('solaris', $body);
        self::assertStringContainsString('in Movies', $body);
        // And offers a clear control back to the unfiltered view.
        self::assertStringContainsString('href="/library/movies"', $body);
        self::assertStringContainsString('Clear search', $body);
    }

    public function testFilteredViewOffersExactlyOneClearControl(): void
    {
        $this->writePoster('Solaris.png');

        // A second clear control beside the search box would go stale: the
        // toolbar sits outside #results and is never swapped by a no-reload
        // update, so the only clear control belongs with the filtered summary.
        $filtered = (string) $this->get($this->app(), '/library/movies?q=solaris')->getBody();
        self::assertSame(1, substr_count($filtered, 'class="search__clear"'));

        // With no query there is no filtered state, and so nothing to clear.
        $unfiltered = (string) $this->get($this->app(), '/library/movies')->getBody();
        self::assertStringNotContainsString('search__clear', $unfiltered);
    }

    public function testTabsCarryTheActiveQuery(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies?q=solaris')->getBody();

        // Switching to another view keeps the search and the sort, even without
        // JavaScript.
        self::assertStringContainsString('href="/library/all?q=solaris&amp;sort=alphabetical"', $body);
        self::assertStringContainsString('href="/library/tv-shows?q=solaris&amp;sort=alphabetical"', $body);
    }

    public function testFilteredEmptyStateIsDistinctFromEmptyLibrary(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies?q=nomatch')->getBody();

        // A filtered view with no matches names the query, not "import from Plex".
        self::assertStringContainsString('No matches for', $body);
        self::assertStringContainsString('nomatch', $body);
        self::assertStringNotContainsString('import from Plex to get started', $body);
        self::assertStringContainsString('Clear search', $body);
    }

    public function testAllViewMergesEveryCategory(): void
    {
        $this->writePosterIn('movies', 'Solaris.png');
        $this->writePosterIn('tv-shows', 'Severance.png');
        $this->writePosterIn('collections', 'Alien Anthology.png');

        $body = (string) $this->get($this->app(), '/library/all')->getBody();

        self::assertStringContainsString('Solaris', $body);
        self::assertStringContainsString('Severance', $body);
        self::assertStringContainsString('Alien Anthology', $body);
        // The All tab renders and is the active one.
        self::assertStringContainsString('href="/library/all?sort=alphabetical"', $body);
        self::assertStringContainsString('tab--active', $body);
    }

    public function testAllViewShowsTypeBadges(): void
    {
        $this->writePosterIn('tv-shows', 'Severance.png');

        $body = (string) $this->get($this->app(), '/library/all')->getBody();

        // Badge with the singular type label, only in the All view.
        self::assertStringContainsString('card__badge--tv-shows', $body);
        self::assertStringContainsString('>TV Show<', $body);
    }

    public function testSingleCategoryViewHasNoBadges(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        self::assertStringNotContainsString('card__badge', $body);
    }

    public function testAllViewActionsTargetOwnCategory(): void
    {
        // The same filename in two categories: each card must post to its own
        // category, since a filename is unique only within a category.
        $this->writePosterIn('movies', 'Poster.png');
        $this->writePosterIn('tv-shows', 'Poster.png');

        $body = (string) $this->get($this->app(), '/library/all')->getBody();

        self::assertStringContainsString('action="/library/movies/delete"', $body);
        self::assertStringContainsString('action="/library/tv-shows/delete"', $body);
        self::assertStringContainsString('data-category="tv-shows"', $body);
        // The combined slug is never an action target.
        self::assertStringNotContainsString('action="/library/all/delete"', $body);
    }

    public function testRemembersAllViewForOrphansBackLink(): void
    {
        $app = $this->makeSignedInApp(['POSTERS_DIR' => $this->postersDir]);

        $this->get($app, '/library/all');
        $body = (string) $this->get($app, '/orphans')->getBody();

        self::assertStringContainsString('href="/library/all"', $body);
    }

    public function testGalleryRendersOverlayActionsAndCaption(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        self::assertStringContainsString('id="results"', $body);
        self::assertStringContainsString('card__caption', $body);
        self::assertStringContainsString('data-action="change"', $body);
        self::assertStringContainsString('data-action="view"', $body);
        // The title moved to a caption; the old overlay title class is gone.
        self::assertStringNotContainsString('card__title', $body);
        // The mobile action sheet is wired up.
        self::assertStringContainsString('x-html="sheet.actions"', $body);
    }

    /**
     * The secondary actions moved to the shared header. This is asserted against
     * .gallery-head rather than the page, because the header renders on the same
     * page and would satisfy any whole-body check — the failure being guarded
     * against is them creeping back into the toolbar, not disappearing.
     */
    public function testGalleryControlsCarryOnlySearchAndSort(): void
    {
        $head = $this->galleryHead((string) $this->get($this->app(), '/library/movies')->getBody());

        self::assertStringContainsString('role="search"', $head);
        // The active title button carries its reverse, because activating it
        // toggles; the inactive date button carries itself.
        self::assertStringContainsString('data-sort="alphabetical_desc"', $head);
        self::assertStringContainsString('data-sort="date_added"', $head);
        self::assertStringContainsString('class="tab ', $head);

        foreach (['/wall', '/plex', '/orphans', 'getmarquee.now/#support'] as $href) {
            self::assertStringNotContainsString(
                'href="' . $href . '"',
                $head,
                'The secondary actions belong to the page header, not the gallery.',
            );
        }
    }

    /**
     * The whole toggle in one document: the active button reads as the order the
     * gallery is in but links to its reverse, while the inactive button reads and
     * links to the same order. Getting that backwards is the likeliest way to
     * break this control, so it is asserted on both buttons at once.
     */
    public function testActiveSortButtonReadsTheCurrentOrderAndLinksToItsReverse(): void
    {
        $this->writePoster('Solaris.png');

        $head = $this->galleryHead((string) $this->get($this->app(), '/library/movies?sort=alphabetical')->getBody());

        // The active button states what it is and names what it does, because
        // those differ on it. Naming only the order it shows would read as an
        // instruction to sort the way it is already sorted.
        self::assertStringContainsString(
            'aria-label="Sorted by title, A to Z — activate for Z to A"',
            $head,
        );
        // The tooltip is the same sentence with the verb a hover implies.
        self::assertStringContainsString(
            'data-tooltip="Sorted by title, A to Z — click for Z to A"',
            $head,
        );
        self::assertStringContainsString('data-sort="alphabetical_desc"', $head);
        self::assertStringContainsString('<span class="sort__text">A–Z</span>', $head);
        self::assertStringNotContainsString('data-sort="alphabetical"', $head);

        self::assertStringContainsString('aria-label="Sort by date added, newest first"', $head);
        self::assertStringContainsString('data-sort="date_added"', $head);
    }

    public function testReversingTheTitleOrderSwapsTheLabelAndTheArrow(): void
    {
        $this->writePoster('Solaris.png');

        $head = $this->galleryHead(
            (string) $this->get($this->app(), '/library/movies?sort=alphabetical_desc')->getBody(),
        );

        self::assertStringContainsString('<span class="sort__text">Z–A</span>', $head);
        self::assertStringContainsString('sort__dir sort__dir--reversed', $head);
        // Reading Z–A, it now offers A–Z.
        self::assertStringContainsString('data-sort="alphabetical"', $head);
        self::assertStringNotContainsString('data-sort="alphabetical_desc"', $head);
    }

    /**
     * The date button keeps one label, so its arrow is the only thing that can
     * report the direction — and the aria-label is the only thing that can say it
     * in words.
     */
    public function testDateButtonReportsDirectionByArrowAndText(): void
    {
        $this->writePoster('Solaris.png');

        $head = $this->galleryHead(
            (string) $this->get($this->app(), '/library/movies?sort=date_added_asc')->getBody(),
        );

        self::assertStringContainsString('<span class="sort__text">Date added</span>', $head);
        self::assertStringContainsString(
            'aria-label="Sorted by date added, oldest first — activate for newest first"',
            $head,
        );
        self::assertStringContainsString('sort__dir sort__dir--reversed', $head);
    }

    /**
     * The arrow reports reversal, not direction. A–Z is ascending and newest
     * first is descending, yet both are the ordinary way to read their own field,
     * so at rest both buttons point the same way — and an arrow that has turned
     * over always means the one thing.
     */
    public function testBothFieldsRestPointingTheSameWay(): void
    {
        $this->writePoster('Solaris.png');

        // A–Z active, date added offered at newest first: neither is reversed.
        $head = $this->galleryHead(
            (string) $this->get($this->app(), '/library/movies?sort=alphabetical')->getBody(),
        );

        self::assertStringContainsString('<span class="sort__text">A–Z</span>', $head);
        self::assertStringContainsString('aria-label="Sort by date added, newest first"', $head);
        self::assertStringNotContainsString('sort__dir--reversed', $head);
    }

    /**
     * One app instance, so the requests share a session: leaving a field and
     * coming back to it must offer the direction it was left in, not its default.
     */
    public function testInactiveButtonOffersTheDirectionItsFieldWasLeftIn(): void
    {
        $this->writePoster('Solaris.png');
        $app = $this->app();

        $this->get($app, '/library/movies?sort=alphabetical_desc');
        $head = $this->galleryHead((string) $this->get($app, '/library/movies?sort=date_added')->getBody());

        // The title button is inactive and still reads Z–A, which is also where
        // it goes.
        self::assertStringContainsString('<span class="sort__text">Z–A</span>', $head);
        self::assertStringContainsString('data-sort="alphabetical_desc"', $head);
    }

    /**
     * The toolbar and the phone tray render from one macro, so every button
     * appears exactly twice — the pair that would otherwise drift apart.
     */
    public function testPhoneTrayCarriesTheSameSortButtonsAsTheToolbar(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        self::assertSame(2, substr_count($body, 'data-sort="alphabetical_desc"'));
        self::assertSame(2, substr_count($body, 'data-sort="date_added"'));
    }

    public function testPaginationCarriesAReversedSort(): void
    {
        $this->writePoster('Alpha.png');
        $this->writePoster('Beta.png');
        $app = $this->makeSignedInApp([
            'POSTERS_DIR' => $this->postersDir,
            'IMAGES_PER_PAGE' => '1',
        ]);

        $body = (string) $this->get($app, '/library/movies?sort=alphabetical_desc')->getBody();

        self::assertStringContainsString('page=2&amp;sort=alphabetical_desc', $body);
    }

    public function testTabsCarryAReversedSort(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies?sort=date_added_asc')->getBody();

        self::assertStringContainsString('href="/library/all?sort=date_added_asc"', $body);
    }

    /**
     * The tabs and the toolbar are pinned as one block, so they share a wrapper.
     * #results stays outside it: a no-reload update swaps that element wholesale,
     * and anything inside it would be discarded on every search, sort and page.
     */
    public function testPinnableControlsWrapTheTabsAndToolbarButNotTheResults(): void
    {
        $body = (string) $this->get($this->app(), '/library/movies')->getBody();
        $head = $this->galleryHead($body);

        self::assertStringContainsString('<nav class="tabs">', $head);
        self::assertStringContainsString('<div class="toolbar">', $head);
        self::assertStringNotContainsString('id="results"', $head);
    }

    /**
     * The wrapper holding the tabs and the toolbar, up to the toolbar's close.
     */
    private function galleryHead(string $body): string
    {
        $matched = preg_match('#<div class="gallery-head">.*?\n    </div>#s', $body, $m);
        self::assertSame(1, $matched, 'The gallery must render its pinnable control block.');

        return $m[0];
    }

    /**
     * A poster change updates its own card in place rather than re-rendering the
     * grid, which is what lets the gallery hold the user's position. The script
     * does that entirely from the rendered markup: it finds the card by the
     * data-category/data-filename pair on its change button (a filename is unique
     * only within a category), and rewrites every place the poster's URL is
     * carried. Nothing fails loudly if one of those disappears — the card would
     * just quietly stop updating — so the contract is pinned here.
     */
    public function testCardCarriesWhatAnInPlaceUpdateNeeds(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        // The card's identity, on the button the lookup scans for.
        self::assertMatchesRegularExpression(
            '/data-action="change"[^>]*data-filename="Solaris\.png"/',
            $body,
        );
        self::assertStringContainsString('data-category="movies"', $body);

        // Every carrier of the poster URL that the update has to rewrite.
        self::assertStringContainsString('class="card__image" src="/posters/movies/Solaris.png?v=', $body);
        self::assertMatchesRegularExpression('/href="\/posters\/movies\/Solaris\.png\?v=\d+" download/', $body);
        self::assertMatchesRegularExpression(
            '/data-action="copy" data-url="\/posters\/movies\/Solaris\.png\?v=\d+"/',
            $body,
        );
        self::assertMatchesRegularExpression(
            '/data-action="view" data-url="\/posters\/movies\/Solaris\.png\?v=\d+"/',
            $body,
        );
    }

    /**
     * The glyph is an aid to finding an action, never the thing that names it:
     * no control carries an aria-label, so the visible label is the accessible
     * name and the icon is hidden from assistive technology. An icon that leaked
     * into the accessible name, or a label replaced by one, would be silent.
     */
    public function testEveryActionControlPairsAHiddenIconWithItsLabel(): void
    {
        $this->writePoster('Solaris.png');
        $actions = $this->cardActions((string) $this->get($this->app(), '/library/movies')->getBody());

        foreach (['Change poster', 'Download', 'Copy URL', 'Full screen', 'Delete'] as $label) {
            self::assertStringContainsString(
                '<span class="card__action-label">' . $label . '</span>',
                $actions,
                sprintf('%s must keep its label.', $label),
            );
        }

        // One icon per control, each hidden from assistive technology.
        self::assertSame(
            5,
            preg_match_all('/<span class="card__action-ico" aria-hidden="true">/', $actions),
            'Each of the five unlinked actions carries exactly one hidden icon.',
        );
        self::assertStringNotContainsString(
            'aria-label',
            $actions,
            'The label names the control; an aria-label would compete with it.',
        );
    }

    /**
     * Fetch from Plex is Import from Plex narrowed to one item, so it draws the
     * same mark; Send to Plex is that mark with the arrow reversed. Direction is
     * the entire distinction between two irreversible actions that move the same
     * image opposite ways, so a copy-paste slip here would invert the meaning of
     * one of them while still looking plausible.
     */
    public function testSendAndFetchGlyphsDifferOnlyInDirection(): void
    {
        $dataDir = $this->makeTempDir();
        $this->writePoster('Solaris.png');

        $repo = new PlexItemRepository(new Database($dataDir . '/marquee.sqlite'));
        $repo->upsert(new PlexItemRecord(
            '1',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Solaris.png',
            time(),
        ));

        $app = $this->makeSignedInApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
        ]);
        $body = (string) $this->get($app, '/library/movies')->getBody();
        $actions = $this->cardActions($body);

        $send = $this->glyphPaths($actions, 'Send to Plex');
        $fetch = $this->glyphPaths($actions, 'Fetch from Plex');
        $import = $this->glyphPaths($body, 'Import from Plex');

        self::assertSame($import, $fetch, 'Fetch and Import are the same operation and take the same glyph.');

        // The shaft and the boundary are shared; only the arrowhead moves.
        self::assertCount(3, $send);
        self::assertSame($fetch[0], $send[0], 'The shaft must not move.');
        self::assertSame($fetch[2], $send[2], 'The boundary must not move.');
        self::assertNotSame($fetch[1], $send[1], 'The arrowhead is what distinguishes them.');
    }

    /**
     * The direction on a sort button is one half of the glyph that opens the
     * phone sort tray — that glyph being a down arrow beside an up arrow. Drawing
     * a separate mark instead would let the tray's trigger and the rows inside it
     * drift apart, so the halves are asserted against the whole rather than
     * against a copy of their own coordinates.
     */
    public function testSortDirectionIsOneHalfOfTheSortGlyph(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        // The phone tray's trigger carries the whole glyph: a shaft and two
        // arrowhead arms, then the same three again for the opposite arrow.
        $trigger = $this->firstPath(
            $body,
            '#class="icon-btn sort-trigger".*?<svg.*? d="([^"]+)"#s',
            'The sort tray trigger must render a glyph.',
        );

        // A sort button's direction mark.
        $direction = $this->firstPath(
            $body,
            '#class="sort__dir[^"]*">\s*<svg.*? d="([^"]+)"#s',
            'A sort button must render a direction glyph.',
        );

        // Both halves of the trigger are the same shape at a different x, so the
        // direction mark — that shape centred — matches either of them once the
        // x coordinates are normalised away.
        $normalise = static fn (string $d): string => preg_replace('/(?<=[MLmlHhVv])\s*\d+(\.\d+)?/', 'x', $d) ?? $d;

        self::assertStringContainsString(
            $normalise($direction),
            $normalise($trigger),
            'The direction arrow must be one half of the sort glyph, not a mark of its own.',
        );
    }

    /** The first captured `d` attribute matched by the pattern. */
    private function firstPath(string $html, string $pattern, string $message): string
    {
        preg_match($pattern, $html, $m);
        if (!isset($m[1])) {
            self::fail($message);
        }

        return $m[1];
    }

    /** The action control block of the first card. */
    private function cardActions(string $body): string
    {
        $matched = preg_match('#<div class="card__actions">.*?\n {20}</div>#s', $body, $m);
        self::assertSame(1, $matched, 'The card must render its action controls.');

        return $m[0];
    }

    /**
     * The `d` attributes of the glyph belonging to the control with this label,
     * in document order.
     *
     * @return list<string>
     */
    private function glyphPaths(string $html, string $label): array
    {
        $quoted = preg_quote($label, '#');
        $matched = preg_match('#<svg[^>]*>(?:(?!</svg>).)*</svg>(?:(?!</svg>).)*?' . $quoted . '</span>#s', $html, $m);
        self::assertSame(1, $matched, sprintf('Expected a glyph paired with "%s".', $label));
        preg_match_all('/ d="([^"]+)"/', $m[0], $paths);

        return $paths[1];
    }

    /**
     * How much of the grid a mutation invalidates is declared by the form, not
     * inferred from its action. Replacing one poster's image touches one card;
     * re-sending stores nothing and touches nothing; deleting changes which
     * posters exist, so it takes the full re-render that the absence of a marker
     * selects. Getting these wrong is invisible until someone loses their scroll
     * position, so each is asserted.
     */
    public function testPosterFormsDeclareHowMuchOfTheGridTheyInvalidate(): void
    {
        $dataDir = $this->makeTempDir();
        $this->writePoster('Solaris.png');

        $repo = new PlexItemRepository(new Database($dataDir . '/marquee.sqlite'));
        $repo->upsert(new PlexItemRecord(
            '1',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Solaris.png',
            time(),
        ));

        $app = $this->makeSignedInApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
        ]);
        $body = (string) $this->get($app, '/library/movies')->getBody();

        // Fetch replaces the local file: one card, and the category that finds it.
        self::assertMatchesRegularExpression(
            '/action="\/library\/movies\/fetch-from-plex"\s+data-refresh="card" data-category="movies"/',
            $body,
        );
        // Send writes only to Plex, so there is nothing on screen to re-render.
        self::assertMatchesRegularExpression(
            '/action="\/library\/movies\/send-to-plex"\s+data-refresh="none"/',
            $body,
        );
        // Delete declares nothing and so keeps the full-grid refresh.
        self::assertMatchesRegularExpression(
            '/action="\/library\/movies\/delete"(?![^>]*data-refresh)/',
            $body,
        );

        // The change tray's two tabs declare nothing, because they post nothing:
        // they hand their image to the preview, and applyPreview() re-renders the
        // one card itself rather than through this delegation.
        self::assertStringNotContainsString(':data-category="change.category"', $body);

        $this->removeDir($dataDir);
    }

    /**
     * Send and Fetch are one-click overwrites sitting side by side, moving the
     * same image in opposite directions — which is exactly what makes a mis-tap
     * plausible and an unrecoverable loss when it lands. Each therefore declares
     * its own confirmation naming the poster and which copy it overwrites; a
     * shared "are you sure?" would not tell a user which button they hit.
     *
     * The script supplies Delete's wording as its fallback, so the Delete form
     * deliberately carries no title of its own. Nothing fails if that drifts —
     * the dialog would just start calling a send a delete — so it is pinned here.
     */
    public function testPlexActionsConfirmBeforeTheyOverwrite(): void
    {
        $dataDir = $this->makeTempDir();
        $this->writePoster('Solaris.png');
        $this->mapPoster($dataDir, 'Solaris.png', 'Solaris', 1972);

        $app = $this->makeSignedInApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
        ]);
        $body = (string) $this->get($app, '/library/movies')->getBody();

        // Send names the Plex item as what it overwrites, and confirms under its
        // own label — never Delete's, and never Delete's red.
        self::assertMatchesRegularExpression(
            '/action="\/library\/movies\/send-to-plex"[^>]*'
            . 'data-confirm="Replace the artwork on Plex for “Solaris \(1972\)”[^"]*"\s+'
            . 'data-confirm-title="Send to Plex\?"\s+'
            . 'data-confirm-label="Send to Plex"\s+'
            . 'data-confirm-tone="accent"/',
            $body,
        );

        // Fetch names the stored poster as what it overwrites — the opposite
        // direction, stated in the message rather than left to be inferred.
        self::assertMatchesRegularExpression(
            '/action="\/library\/movies\/fetch-from-plex"[^>]*'
            . 'data-confirm="Replace Marquee[^"]*poster for “Solaris \(1972\)”[^"]*"\s+'
            . 'data-confirm-title="Fetch from Plex\?"\s+'
            . 'data-confirm-label="Fetch from Plex"\s+'
            . 'data-confirm-tone="accent"/',
            $body,
        );

        // Three confirmed actions on the card, and no two of them worded alike:
        // that is the whole point of confirming a pair of adjacent, opposite
        // actions next to a destructive one.
        $messages = $this->confirmMessages($body);
        self::assertCount(3, $messages, 'send, fetch and delete each confirm');
        self::assertSame($messages, array_values(array_unique($messages)));

        // Escape unwinds one layer. The action tray and the confirmation raised
        // above it both listen on the window, so without the guard declining a
        // confirmation would take the tray with it and leave the user back at
        // the grid, having to reopen the poster to do anything else.
        self::assertStringContainsString(
            '@keydown.escape.window="if (!confirm.open) closeSheet()"',
            $body,
        );

        // Delete keeps its message and keeps relying on the script's fallback
        // wording, so it must not have acquired a title of its own.
        self::assertMatchesRegularExpression(
            '/action="\/library\/movies\/delete"[^>]*data-confirm="Permanently delete /',
            $body,
        );
        self::assertMatchesRegularExpression(
            '/action="\/library\/movies\/delete"(?![^>]*data-confirm-title)/',
            $body,
        );

        $this->removeDir($dataDir);
    }

    /**
     * Changing a poster from a file or from a URL overwrites the stored image and,
     * for a linked poster, uploads it to Plex locked — the same unrecoverable move
     * Send, Fetch and Delete each stop to confirm. These two tabs commit through
     * the full-screen preview instead of a text dialog: the user sees the image
     * before it goes anywhere, exactly as a found candidate is seen, so all three
     * ways into the one operation feel like the one operation.
     *
     * What that costs is the shared confirm dialog for these two forms, and this
     * test is mostly about that removal being complete — a form left carrying
     * `js-mutate` would post on submit and never reach the preview at all.
     */
    public function testChangePosterTabsPreviewBeforeTheyReplace(): void
    {
        $dataDir = $this->makeTempDir();
        $this->writePoster('Solaris.png');

        $app = $this->makeSignedInApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
        ]);
        $body = (string) $this->get($app, '/library/movies')->getBody();

        // Each tab hands its image to the preview rather than posting it. No
        // action, no js-mutate, no confirm attributes: the delegated submit
        // handler must not recognise these forms at all.
        self::assertMatchesRegularExpression(
            '/<form class="form" x-show="change\.tab === \'upload\'" @submit\.prevent="openUploadPreview\(\)">/',
            $body,
        );
        self::assertMatchesRegularExpression(
            '/<form class="form" x-show="change\.tab === \'url\'" @submit\.prevent="openUrlPreview\(\)">/',
            $body,
        );
        self::assertStringNotContainsString('/change/upload', $body);
        self::assertStringNotContainsString('/change/url', $body);
        self::assertStringNotContainsString('data-confirm-title="Change poster?"', $body);
        self::assertStringNotContainsString('with the selected image?', $body);
        self::assertStringNotContainsString('with the image at that URL?', $body);

        // Overwriting is not deleting: the app reserves red for removing a poster,
        // and this operation confirms in accent wherever it is confirmed.
        self::assertStringNotContainsString('data-confirm-tone="danger"', $body);

        // The tabs supply an image; the preview changes the poster. Each tab names
        // how its image gets here — one sends a file up, the other has the server
        // go and get one — and neither claims to change anything. The act that
        // does is still called what the dialog is headed, from all three tabs.
        self::assertSame(1, preg_match_all('/>Upload poster<\/button>/', $body));
        self::assertSame(1, preg_match_all('/>Fetch poster<\/button>/', $body));
        self::assertSame(1, preg_match_all('/>Change poster<\/button>/', $body));
        self::assertStringNotContainsString('Update poster', $body);

        // The preview's question is deliberately short and static. Binding the
        // poster's title into it wrapped the line on a phone, which grew the
        // bottom-anchored bar and shifted the image being inspected — so the ask
        // is one line for every poster, however long its name.
        self::assertStringContainsString(
            '<span class="viewer__ask">Change the poster to this one?</span>',
            $body,
        );
        self::assertStringNotContainsString('Change the poster for', $body);

        // Mediux URLs still work; the label just stops saying so, since the field
        // takes an image URL and nothing about that is Mediux-specific.
        self::assertStringContainsString('<label for="change-url">Image URL</label>', $body);
        self::assertStringNotContainsString('Mediux', $body);

        // Escape has to unwind one layer at a time: this dialog, the confirm
        // dialog and the preview all listen on the window, so without the guard an
        // Escape meant for the preview would close the dialog underneath it and
        // discard the file just picked.
        self::assertStringContainsString(
            '@keydown.escape.window="if (!confirm.open && !preview.open) change.open = false"',
            $body,
        );

        // openChange() empties both fields by ref, because the dialog is one
        // instance reused for every poster and nothing else owns their state —
        // and the two open handlers read the image back out through the same refs.
        // Renaming a ref here would break both, silently.
        self::assertMatchesRegularExpression('/id="change-file" x-ref="changeFile"/', $body);
        self::assertMatchesRegularExpression('/id="change-url" x-ref="changeUrl"/', $body);

        $this->removeDir($dataDir);
    }

    /**
     * The preview is not Find Posters' property any more. Its markup must sit
     * outside the modal panel (it has to cover it) and be bound to state no tab
     * owns, or the two new callers get an overlay that only the finder can drive.
     */
    public function testThePreviewIsSharedByEveryChangePosterTab(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        self::assertStringContainsString('class="viewer viewer--preview" x-show="preview.open"', $body);
        self::assertStringNotContainsString('viewer--finder', $body);

        // Every control in the preview drives the shared state, and the candidate
        // grid is one of three callers that opens it rather than the only one.
        self::assertStringContainsString('@click="openPreview(poster.url, \'find\')"', $body);
        self::assertStringContainsString('@click="applyPreview()"', $body);
        // Backdrop, stage, Escape and Close: every way out closes only the
        // preview, leaving the dialog that opened it standing.
        self::assertSame(4, preg_match_all('/closePreview\(\)/', $body));

        // The preview must not be nested in the modal panel it covers, or it
        // would be clipped by the panel and sit under the progress overlay.
        $modal = strpos($body, 'class="modal" x-show="change.open"');
        $preview = strpos($body, 'class="viewer viewer--preview"');
        self::assertIsInt($modal);
        self::assertIsInt($preview);
        self::assertGreaterThan($modal, $preview);
    }

    /**
     * The confirmation message of every form that renders one literally, in render
     * order. The change dialog's two tabs bind theirs from Alpine state instead,
     * so this sees the card's actions and not them.
     *
     * @return list<string>
     */
    private function confirmMessages(string $body): array
    {
        preg_match_all('/\sdata-confirm="([^"]*)"/', $body, $matches);

        return $matches[1];
    }

    /**
     * Every poster the user waits for is held by a placeholder until it resolves:
     * the candidate cell by a frame it fills, the two full-screen views by a
     * standalone element, since a full-screen image is sized by its own
     * dimensions and has no frame to fill. The stylesheet draws all of that, but
     * only for markup that is actually there — and both viewers' images are
     * revealed by Alpine state rather than by the DOM scan that reveals cards, so
     * the bindings are load-bearing too.
     */
    public function testWaitingPostersRenderTheirPlaceholderAndFadeBindings(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        // Find Posters candidate cells: a frame that reserves the cell, an image
        // deferred until it is near the visible results, and per-cell state so
        // each shimmer stops as its own thumbnail arrives.
        self::assertStringContainsString('class="find-item__frame"', $body);
        self::assertStringContainsString('loading="lazy"', $body);
        self::assertStringContainsString('@load="loaded = true" @error="loaded = true"', $body);

        // Both full-screen views: the shared viewer and the change-poster preview.
        self::assertSame(2, substr_count($body, 'class="viewer__stage"'), 'Both full-screen views need a stage.');
        self::assertSame(2, substr_count($body, 'class="viewer__placeholder"'), 'Both full-screen views need a placeholder.');
        self::assertStringContainsString('x-show="!viewerLoaded"', $body);
        self::assertStringContainsString('x-show="!preview.loaded"', $body);
        // A failed image resolves the placeholder too, or it shimmers forever —
        // and for a pasted URL it is also what lets the user confirm anyway.
        self::assertStringContainsString('@error="viewerLoaded = true"', $body);
        self::assertStringContainsString('@error="preview.loaded = true"', $body);
    }

    /**
     * On a phone the Change Poster modal is presented as a tray, and a tray is
     * dismissed by dragging its grab handle or tapping its backdrop — there is no
     * close button at that size. That only works if the handle is a real element
     * touch can target, and if it is a *different* element from the one that
     * scrolls, so the drag region can opt out of browser gestures without also
     * disabling the scroll.
     */
    public function testChangePosterTrayHasAGrabbableHandleSeparateFromItsScroller(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        self::assertStringContainsString('class="modal__body"', $body);
        // Every tray panel on the page carries a grab handle: the menu, the
        // poster action sheet, sort, import and orphans, plus the Change Poster
        // and confirmation modals now presented as trays.
        self::assertSame(
            substr_count($body, 'class="modal__panel') + substr_count($body, 'class="sheet__panel'),
            substr_count($body, 'class="sheet__grip"'),
            'Every tray panel needs a grab handle; it is one of only two ways out.'
        );
        // The handle used to be drawn as a ::before on the panel, which no touch
        // can ever land on. Nothing may go back to relying on that.
        self::assertStringNotContainsString('modal__panel::before', $body);
        // Backdrop dismissal is the other way out and must stay wired.
        self::assertStringContainsString('class="modal__backdrop" @click="change.open = false"', $body);
    }

    /**
     * The caption comes from the record, so the library token the filename
     * carries never reaches the page at all.
     */
    public function testCaptionUsesTheRecordedTitleAndAppendsTheYear(): void
    {
        $body = $this->renderMapped('Louis_and_the_Nazis_2003_Movies.png', 'Louis and the Nazis', 2003);

        self::assertStringContainsString('>Louis and the Nazis (2003)</figcaption>', $body);
        self::assertStringNotContainsString('Louis and the Nazis 2003', $body);
        self::assertStringNotContainsString('(Movies)', $body);
    }

    /**
     * A title that names its own year — as Plex does for a show called
     * "Lucky (2026)" — is shown as-is rather than gaining a second copy.
     */
    public function testCaptionDoesNotRepeatAYearTheRecordedTitleAlreadyNames(): void
    {
        $body = $this->renderMapped('Lucky_2026_Movies.png', 'Lucky (2026)', 2026);

        self::assertStringContainsString('>Lucky (2026)</figcaption>', $body);
        self::assertStringNotContainsString('(2026) (2026)', $body);
    }

    /**
     * Seasons get no year: the stored value is the show's, so "Season 5 (2008)"
     * would date a 2012 season to the year the show began.
     */
    public function testSeasonCaptionsShowNoYear(): void
    {
        $dataDir = $this->makeTempDir();
        mkdir($this->postersDir . '/tv-seasons');
        $this->writePng($this->postersDir . '/tv-seasons/Breaking_Bad_-_Season_5_TV_Shows.png');

        $repo = new PlexItemRepository(new Database($dataDir . '/marquee.sqlite'));
        $repo->upsert(new PlexItemRecord(
            '20',
            'season',
            'tv-seasons',
            'TV Shows',
            'Breaking Bad - Season 5',
            'Breaking_Bad_-_Season_5_TV_Shows.png',
            time(),
            year: 2008,
        ));

        $body = (string) $this->get($this->appWithData($dataDir), '/library/tv-seasons')->getBody();

        self::assertStringContainsString('>Breaking Bad - Season 5</figcaption>', $body);
        self::assertStringNotContainsString('(2008)', $body);

        $this->removeDir($dataDir);
    }

    public function testCaptionRestoresPunctuationTheFilenameLost(): void
    {
        $body = $this->renderMapped('Spider-Noir_B_W_Movies.png', 'Spider-Noir B&W', 2025);

        // Twig escapes the ampersand; the point is that it survives at all — the
        // filename-derived title reads "Spider-Noir B W".
        self::assertStringContainsString('>Spider-Noir B&amp;W (2025)</figcaption>', $body);
    }

    public function testCaptionShowsNoYearWhenNoneIsStored(): void
    {
        $body = $this->renderMapped('Ace_Ventura_Movies.png', 'Ace Ventura', null);

        self::assertStringContainsString('>Ace Ventura</figcaption>', $body);
        self::assertStringNotContainsString('Ace Ventura (', $body);
    }

    /**
     * A poster with no mapping still renders: it falls back to the filename, i.e.
     * to the behaviour that predates the caption reading from the database.
     */
    public function testUnmappedPosterFallsBackToItsFilename(): void
    {
        $dataDir = $this->makeTempDir();
        $this->writePoster('Hand_Placed.png');

        $body = (string) $this->get($this->appWithData($dataDir), '/library/movies')->getBody();

        self::assertStringContainsString('>Hand Placed</figcaption>', $body);

        $this->removeDir($dataDir);
    }

    /**
     * Every surface that names the poster takes the caption's text — including the
     * alt attribute and the delete confirmation, which used to show the raw
     * filename-derived title with its library token.
     */
    public function testOneTitleFeedsEveryPlaceThePosterIsNamed(): void
    {
        $body = $this->renderMapped('Louis_and_the_Nazis_2003_Movies.png', 'Louis and the Nazis', 2003);

        self::assertStringContainsString('data-title="Louis and the Nazis (2003)"', $body);
        self::assertStringContainsString('data-tooltip="Louis and the Nazis (2003)"', $body);
        self::assertStringContainsString('alt="Louis and the Nazis (2003)"', $body);
        self::assertStringContainsString('Permanently delete “Louis and the Nazis (2003)”', $body);
        self::assertStringNotContainsString('data-sheet-title', $body);
    }

    /**
     * The caption's tooltip only restates the caption, so it is marked conditional
     * and appears only while the title is cut off. Tooltips that carry a genuine
     * hint — the pagination steps — must not pick the marker up.
     */
    public function testOnlyTheCaptionTooltipIsConditionalOnTruncation(): void
    {
        // Two posters, one per page, so the pagination steps render alongside a
        // caption and the two kinds of tooltip can be compared in one document.
        $this->writePoster('Alpha.png');
        $this->writePoster('Beta.png');
        $app = $this->makeSignedInApp([
            'POSTERS_DIR' => $this->postersDir,
            'IMAGES_PER_PAGE' => '1',
        ]);

        $body = (string) $this->get($app, '/library/movies')->getBody();

        self::assertStringContainsString('data-tooltip="Alpha" data-tooltip-truncated', $body);
        self::assertStringContainsString('data-tooltip="Next page"', $body);
        self::assertStringNotContainsString('data-tooltip="Next page" data-tooltip-truncated', $body);
    }

    /**
     * The title is a rendering concern only. Showing one the filename does not
     * match must not tempt anything into "fixing" the file or the row.
     */
    public function testRenderingCaptionsWritesNothing(): void
    {
        $dataDir = $this->makeTempDir();
        $this->writePoster('Solaris_Movies.png');
        $this->mapPoster($dataDir, 'Solaris_Movies.png', 'Solaris', 1972);

        $dbBefore = (string) file_get_contents($dataDir . '/marquee.sqlite');
        $postersBefore = scandir($this->postersDir . '/movies');

        $body = (string) $this->get($this->appWithData($dataDir), '/library/movies')->getBody();
        self::assertStringContainsString('>Solaris (1972)</figcaption>', $body);

        self::assertSame($postersBefore, scandir($this->postersDir . '/movies'), 'a render renamed a poster');
        self::assertSame($dbBefore, (string) file_get_contents($dataDir . '/marquee.sqlite'), 'a render wrote to the database');

        $this->removeDir($dataDir);
    }

    /**
     * Write a poster, map it to a Plex item recorded under $title and carrying
     * $year, and render the movies view. Uses its own data dir so no other test's
     * mappings can leak in.
     */
    private function renderMapped(string $filename, string $title, ?int $year): string
    {
        $dataDir = $this->makeTempDir();
        $this->writePoster($filename);
        $this->mapPoster($dataDir, $filename, $title, $year);

        $body = (string) $this->get($this->appWithData($dataDir), '/library/movies')->getBody();

        $this->removeDir($dataDir);

        return $body;
    }

    private function mapPoster(string $dataDir, string $filename, string $title, ?int $year): void
    {
        $repo = new PlexItemRepository(new Database($dataDir . '/marquee.sqlite'));
        $repo->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            $title,
            $filename,
            time(),
            year: $year,
        ));
    }

    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function appWithData(string $dataDir): App
    {
        return $this->makeSignedInApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
        ]);
    }

    public function testLogoutShownWhenSignedIn(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        self::assertStringContainsString('/logout', $body);
    }

    /**
     * The control is two words. It carried a sentence about the Plex connection
     * surviving, which the tray renders as visible text, making the most-reached
     * control the largest thing in the menu. Where the two exits are weighed
     * against each other is the connection screen — see PlexConnectionTest.
     */
    public function testLogoutIsJustTheAction(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        self::assertStringContainsString('>Log out</span>', $body);
        self::assertStringNotContainsString('scheduled imports keep running', $body);
    }

    public function testRemembersSectionForOrphansBackLink(): void
    {
        // One app instance so the in-memory session persists across requests.
        $app = $this->makeSignedInApp(['POSTERS_DIR' => $this->postersDir]);

        $this->get($app, '/library/tv-shows');
        $body = (string) $this->get($app, '/orphans')->getBody();

        self::assertStringContainsString('href="/library/tv-shows"', $body);
    }

    public function testImageIsServed(): void
    {
        $this->writePoster('Solaris.png');

        $response = $this->get($this->app(), '/posters/movies/Solaris.png');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertNotSame('', (string) $response->getBody());
    }

    /**
     * The version marker exists only to move the browser's cache key. The
     * server must route on the path alone, so a stale, absent, or malformed
     * marker still yields the poster currently on disk.
     */
    #[DataProvider('versionMarkers')]
    public function testImageIsServedRegardlessOfVersionMarker(string $query): void
    {
        $this->writePoster('Solaris.png');

        $response = $this->get($this->app(), '/posters/movies/Solaris.png' . $query);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertNotSame('', (string) $response->getBody());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function versionMarkers(): array
    {
        return [
            'absent' => [''],
            'stale' => ['?v=1'],
            'non-numeric' => ['?v=not-a-time'],
            'empty' => ['?v='],
        ];
    }

    public function testChangedPosterGetsANewUrl(): void
    {
        $this->writePoster('Solaris.png');
        $app = $this->app();

        $before = $this->posterUrl((string) $this->get($app, '/library/movies')->getBody());

        // Replace the file with different bytes and a later mtime, exactly as
        // changing a poster does. This is the reported bug: the flash message
        // was always correct, the image was the stale part.
        $path = $this->postersDir . '/movies/Solaris.png';
        file_put_contents($path, $this->pngBytes(4, 5));
        touch($path, time() + 10);
        clearstatcache(true, $path);

        $after = $this->posterUrl((string) $this->get($app, '/library/movies')->getBody());

        self::assertNotSame('', $before);
        self::assertNotSame($before, $after, 'a replaced poster must not reuse its previous URL');
    }

    public function testUnchangedPosterKeepsItsUrlAcrossRenders(): void
    {
        $this->writePoster('Solaris.png');
        $app = $this->app();

        $first = $this->posterUrl((string) $this->get($app, '/library/movies')->getBody());
        $second = $this->posterUrl((string) $this->get($app, '/library/movies')->getBody());

        self::assertNotSame('', $first);
        self::assertSame($first, $second, 'an untouched poster must stay cacheable');
    }

    private function posterUrl(string $body): string
    {
        preg_match('#/posters/movies/Solaris\.png(\?v=\d+)?#', $body, $m);

        return $m[0] ?? '';
    }

    public function testImageTraversalReturns404(): void
    {
        self::assertSame(404, $this->get($this->app(), '/posters/movies/..')->getStatusCode());
    }

    public function testImageTraversalWithVersionMarkerReturns404(): void
    {
        // The marker must not offer a way past filename validation.
        self::assertSame(404, $this->get($this->app(), '/posters/movies/..?v=1')->getStatusCode());
    }

    public function testMissingImageReturns404(): void
    {
        self::assertSame(404, $this->get($this->app(), '/posters/movies/nope.png')->getStatusCode());
    }

    public function testDeleteRemovesPoster(): void
    {
        $this->writePoster('Gone.png');

        $response = $this->postForm($this->app(), '/library/movies/delete', ['filename' => 'Gone.png']);

        self::assertSame(302, $response->getStatusCode());
        self::assertFileDoesNotExist($this->postersDir . '/movies/Gone.png');
    }
}
