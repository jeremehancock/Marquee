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
