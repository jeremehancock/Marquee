<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Tests\AppTestCase;
use App\Tests\Support\MakesImages;
use Slim\App;

/**
 * The order a set opens in, and what that order does and does not outlive.
 *
 * A set DEFAULTS the sort rather than overriding it. The distinction is the
 * whole design: an override would leave the sort control reading "A–Z" over a
 * grid that is not in A–Z, which is a lie on screen and leaves the control
 * nothing honest to do. A default keeps every button live and every label true.
 *
 * The other half is that the default does not leak. Sorting a set is a question
 * about one work; answering it must not re-sort the library the user goes back
 * to. Both directions of that are asserted here.
 */
final class SetOrderTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        foreach (['movies', 'collections'] as $category) {
            mkdir($this->postersDir . '/' . $category, 0o775, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
    }

    /**
     * A collection whose films sort one way by title and another by release:
     * "Aaa Last" came out in 2020 and "Zzz First" in 1990, so A–Z and release
     * order disagree about every position. Anything less would let a passing
     * test mean nothing.
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function app(): App
    {
        $app = $this->makeSignedInApp(['POSTERS_DIR' => $this->postersDir]);
        $container = $app->getContainer();
        self::assertNotNull($container);
        /** @var PlexItemRepository $items */
        $items = $container->get(PlexItemRepository::class);

        foreach ([['Aaa Last', 2020], ['Zzz First', 1990]] as [$title, $year]) {
            $filename = str_replace(' ', '_', $title) . '_Movies.png';
            $this->writePng($this->postersDir . '/movies/' . $filename);
            $items->upsert(new PlexItemRecord(
                (string) crc32($title),
                'movie',
                'movies',
                'Movies',
                $title,
                $filename,
                time(),
                year: $year,
                setKeys: ['700'],
            ));
        }

        return $app;
    }

    /**
     * Which of the two films the grid lists first.
     *
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    private function firstFilm(App $app, string $path): string
    {
        $body = (string) $this->get($app, $path)->getBody();
        $first = strpos($body, 'Aaa Last');
        $second = strpos($body, 'Zzz First');

        self::assertIsInt($first);
        self::assertIsInt($second);

        return $first < $second ? 'Aaa Last' : 'Zzz First';
    }

    public function testASetOpensInReleaseOrder(): void
    {
        $app = $this->app();

        self::assertSame('Zzz First', $this->firstFilm($app, '/library/all?set=700'), '1990 before 2020');
    }

    /**
     * The rung that matters: a set's default outranks a stored preference.
     * Without it, a user who once chose Z–A would never see a set in the order
     * it was released — which is the only reason to open one.
     */
    public function testASetOpensInReleaseOrderDespiteAStoredChoice(): void
    {
        $app = $this->app();
        $this->get($app, '/library/all?sort=alphabetical_desc');

        self::assertSame('Zzz First', $this->firstFilm($app, '/library/all?set=700'));
    }

    public function testTheSortControlShowsReleaseAsActiveInASet(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/all?set=700')->getBody();

        self::assertMatchesRegularExpression(
            '/data-sort="release[^"]*"[^>]*aria-current="true"|aria-current="true"[^>]*data-sort="release/',
            $body,
            'the active button must be the release one, not a field the grid is not in',
        );
    }

    /**
     * An address naming a sort still wins, which is what keeps the control live
     * rather than decorative.
     */
    public function testAnExplicitSortWinsInsideASet(): void
    {
        $app = $this->app();

        self::assertSame(
            'Aaa Last',
            $this->firstFilm($app, '/library/all?set=700&sort=alphabetical'),
        );
    }

    public function testSortingInsideASetKeepsTheSet(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/all?set=700&sort=alphabetical')->getBody();

        // Still the set's own summary and clear control, not a full listing.
        self::assertStringContainsString('in this set', $body);
    }

    /**
     * The other direction, and the one a user would notice: an order chosen
     * inside a set does not become the library's order once the set is cleared.
     */
    public function testAnOrderChosenInsideASetDoesNotOutliveIt(): void
    {
        $app = $this->app();

        // Establish a library preference, then contradict it inside a set.
        $this->get($app, '/library/all?sort=alphabetical');
        $this->get($app, '/library/all?set=700&sort=alphabetical_desc');

        self::assertSame(
            'Aaa Last',
            $this->firstFilm($app, '/library/all'),
            'the library keeps the A–Z the user chose for it',
        );
    }

    /**
     * And the direction that proves the suppression is not simply "never
     * remember": outside a set, a chosen order is recorded exactly as before.
     */
    public function testAnOrderChosenOutsideASetIsStillRemembered(): void
    {
        $app = $this->app();
        $this->get($app, '/library/all?sort=alphabetical_desc');

        self::assertSame('Zzz First', $this->firstFilm($app, '/library/all'), 'Z–A survives');
    }

    /**
     * With no scripting, a tab is a plain link — so the set has to be in its
     * address or a tab tap drops it. This is the half of "a set persists like a
     * query" that the browser cannot be asked to do for us.
     */
    public function testTabLinksCarryTheSet(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/all?set=700')->getBody();

        self::assertStringContainsString('/library/movies?set=700', $body);
    }

    /**
     * A set and a query are alternatives the server never applies together, so
     * a tab link carries exactly one of them.
     */
    public function testTabLinksCarryTheSetInsteadOfAQueryNotBoth(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/all?set=700&q=zzz')->getBody();

        self::assertStringContainsString('/library/movies?set=700', $body);
        self::assertStringNotContainsString('set=700&amp;q=', $body);
    }

    public function testSortLinksCarryTheSet(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/all?set=700')->getBody();

        self::assertStringContainsString('set=700', $body);
        self::assertMatchesRegularExpression('/\?sort=[a-z_]+&amp;set=700/', $body);
    }

    /**
     * The other direction for the tab link: with no set active it still carries
     * the query, exactly as it did before any of this.
     */
    public function testTabLinksStillCarryAQueryWhenNoSetIsActive(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/all?q=zzz')->getBody();

        self::assertStringContainsString('/library/movies?q=zzz', $body);
    }

    /**
     * Switching to a view holding none of the set's posters is a FILTERED empty
     * state, not an empty library — the same thing an active query that matches
     * nothing produces there, which is the point of making the two behave alike.
     */
    public function testAViewHoldingNoneOfTheSetSaysItIsFiltered(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/collections?set=700')->getBody();

        self::assertStringContainsString('Nothing else in this set', $body);
        self::assertStringNotContainsString('No posters yet', $body);
    }

    public function testPagingKeepsTheSet(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/all?set=700')->getBody();

        // Two films and a default page size, so there is no second page to link
        // to; what matters is that the set survives the round trip at all.
        self::assertStringContainsString('in this set', $body);
        self::assertSame('Zzz First', $this->firstFilm($app, '/library/all?set=700&page=1'));
    }

    /**
     * The library and a set lead with opposite ends of the same field, and both
     * are deliberate.
     *
     * Browsing by release leads with the newest, agreeing with Date added beside
     * it so a down arrow means one thing across the toolbar. Opening a SET leads
     * with the earliest, because reading a trilogy in the order it came out is
     * the whole reason to open one. Asserted together so neither can be changed
     * on the assumption that the other follows from it.
     */
    public function testTheLibraryLeadsWithTheNewestWhileASetLeadsWithTheEarliest(): void
    {
        $app = $this->app();

        self::assertSame(
            'Aaa Last',
            $this->firstFilm($app, '/library/all?sort=release'),
            'browsing by release leads with 2020, like date added beside it',
        );
        self::assertSame(
            'Zzz First',
            $this->firstFilm($app, '/library/all?set=700'),
            'a set leads with 1990 — the order it was released in',
        );
    }
}
