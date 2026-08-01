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
        return $this->makeApp(['POSTERS_DIR' => $this->postersDir, 'AUTH_BYPASS' => 'true']);
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

        // Switching to another view keeps the search, even without JavaScript.
        self::assertStringContainsString('href="/library/all?q=solaris"', $body);
        self::assertStringContainsString('href="/library/tv-shows?q=solaris"', $body);
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
        self::assertStringContainsString('href="/library/all"', $body);
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
        $app = $this->makeApp(['POSTERS_DIR' => $this->postersDir, 'AUTH_BYPASS' => 'true']);

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
        // Poster Wall opens in a new tab.
        self::assertStringContainsString('target="_blank"', $body);
        // The mobile action sheet is wired up.
        self::assertStringContainsString('x-html="sheet.actions"', $body);
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

        $app = $this->makeApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
            'AUTH_BYPASS' => 'true',
            'PLEX_SERVER_URL' => 'http://plex:32400',
            'PLEX_TOKEN' => 'token',
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

        // The change tray's two tabs replace the image of the poster it was
        // opened for, so they are card-local too.
        self::assertSame(
            2,
            preg_match_all('/data-refresh="card" :data-category="change\.category"/', $body),
        );

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

        $app = $this->makeApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
            'AUTH_BYPASS' => 'true',
            'PLEX_SERVER_URL' => 'http://plex:32400',
            'PLEX_TOKEN' => 'token',
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
     * Send, Fetch and Delete each stop to confirm. These two tabs were the last
     * one-click way to do it, so they confirm through the same shared dialog: a
     * modal on a pointer device, a tray on a phone.
     *
     * Their message is *bound* rather than written into the markup, because this
     * dialog is one instance reused for whichever poster was tapped — only Alpine
     * knows the title at submit time. That is also why the count in
     * testPlexActionsConfirmBeforeTheyOverwrite still reads three: a bound
     * attribute is not a rendered one.
     */
    public function testChangePosterTabsConfirmBeforeTheyReplace(): void
    {
        $dataDir = $this->makeTempDir();
        $this->writePoster('Solaris.png');

        $app = $this->makeApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
            'AUTH_BYPASS' => 'true',
        ]);
        $body = (string) $this->get($app, '/library/movies')->getBody();

        // Upload names the selected image as what replaces the poster.
        self::assertMatchesRegularExpression(
            '/:action="[^"]*\/change\/upload[^"]*"[^>]*'
            . ':data-confirm="[^"]*with the selected image\?\'"[^>]*'
            . 'data-confirm-title="Change poster\?"[^>]*'
            . 'data-confirm-label="Change poster"[^>]*'
            . 'data-confirm-tone="accent"/',
            $body,
        );

        // From URL differs only in the source phrase, which is what tells a user
        // which of the two tabs they submitted from.
        self::assertMatchesRegularExpression(
            '/:action="[^"]*\/change\/url[^"]*"[^>]*'
            . ':data-confirm="[^"]*with the image at that URL\?\'"[^>]*'
            . 'data-confirm-title="Change poster\?"[^>]*'
            . 'data-confirm-label="Change poster"[^>]*'
            . 'data-confirm-tone="accent"/',
            $body,
        );

        // Overwriting is not deleting: the app reserves red for removing a poster,
        // and the Find Posters step confirms this same operation in accent.
        self::assertStringNotContainsString('data-confirm-tone="danger"', $body);

        // One action, one name: the two tab submits plus the Find Posters confirm,
        // all reading "Change poster" under a dialog headed the same. The tabs
        // said "Update poster", which named nothing else in the app.
        self::assertSame(3, preg_match_all('/>Change poster<\/button>/', $body));
        self::assertStringNotContainsString('Update poster', $body);

        // Mediux URLs still work; the label just stops saying so, since the field
        // takes an image URL and nothing about that is Mediux-specific.
        self::assertStringContainsString('<label for="change-url">Image URL</label>', $body);
        self::assertStringNotContainsString('Mediux', $body);

        // Escape has to unwind one layer at a time: this dialog and the confirm
        // dialog stacked over it both listen on the window, so without the guard
        // declining a confirmation would also discard the file just picked.
        self::assertStringContainsString(
            '@keydown.escape.window="if (!confirm.open) change.open = false"',
            $body,
        );

        $this->removeDir($dataDir);
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

        // Both full-screen views: the shared viewer and the Find Posters preview.
        self::assertSame(2, substr_count($body, 'class="viewer__stage"'), 'Both full-screen views need a stage.');
        self::assertSame(2, substr_count($body, 'class="viewer__placeholder"'), 'Both full-screen views need a placeholder.');
        self::assertStringContainsString('x-show="!viewerLoaded"', $body);
        self::assertStringContainsString('x-show="!finder.previewLoaded"', $body);
        // A failed image resolves the placeholder too, or it shimmers forever.
        self::assertStringContainsString('@error="viewerLoaded = true"', $body);
        self::assertStringContainsString('@error="finder.previewLoaded = true"', $body);
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
        $app = $this->makeApp([
            'POSTERS_DIR' => $this->postersDir,
            'AUTH_BYPASS' => 'true',
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
        return $this->makeApp([
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $dataDir,
            'AUTH_BYPASS' => 'true',
        ]);
    }

    public function testLogoutHiddenWhenAuthBypassed(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        // AUTH_BYPASS is true in app(); the logout link should not render.
        self::assertStringNotContainsString('/logout', $body);
    }

    public function testLogoutShownWhenAuthEnabled(): void
    {
        // Build a container with auth enabled and render the shared layout: the
        // logout link should be present when auth is not bypassed.
        putenv('AUTH_BYPASS=false');
        putenv('DATA_DIR=' . sys_get_temp_dir() . '/marquee-test-data');
        $twig = \App\buildContainer()->get(\Slim\Views\Twig::class);
        self::assertInstanceOf(\Slim\Views\Twig::class, $twig);

        $html = $twig->fetch('layout.html.twig', ['app_version' => '0.0.0']);

        self::assertStringContainsString('/logout', $html);
        putenv('AUTH_BYPASS');
    }

    public function testRemembersSectionForOrphansBackLink(): void
    {
        // One app instance so the in-memory session persists across requests.
        $app = $this->makeApp(['POSTERS_DIR' => $this->postersDir, 'AUTH_BYPASS' => 'true']);

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
