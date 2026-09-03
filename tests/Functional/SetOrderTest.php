<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Tests\AppTestCase;
use App\Tests\Support\MakesImages;
use Slim\App;

/**
 * A set is ordered by the active sort, exactly as a search is.
 *
 * This is the second answer to the question. The first was that a set opened in
 * release order whatever the user had chosen — a default rather than an
 * override, careful not to write anything to the session, and wrong anyway. The
 * sort control is a GLOBAL control, and a view that reinterprets it makes the
 * toolbar change on its own; "nothing was stored" is not a distinction anyone
 * can see from the button. It read as the user's setting being overwritten, and
 * was reported as exactly that.
 *
 * So Release is a field to pick and nothing more. What these tests pin is the
 * ABSENCE of special-casing: opening a set changes nothing about the sort, and a
 * sort chosen while a set is open is remembered like any other. The absence is
 * what needs guarding, because re-adding a "sets should really open in release
 * order" default is a small and plausible-looking edit.
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

    /**
     * A stored choice is honoured inside a set. This is the reported defect: the
     * user had ordered by release, newest first, and opening a set turned it
     * around under them.
     */
    public function testASetUsesTheOrderTheUserChose(): void
    {
        $app = $this->app();
        $this->get($app, '/library/all?sort=release');

        self::assertSame(
            'Aaa Last',
            $this->firstFilm($app, '/library/all?set=700'),
            'release, newest first — 2020 before 1990, as chosen',
        );
    }

    /**
     * And the other direction, so this cannot pass by the set happening to agree
     * with one particular order.
     */
    public function testASetFollowsTheOrderInEitherDirection(): void
    {
        $app = $this->app();

        $this->get($app, '/library/all?sort=release_asc');
        self::assertSame('Zzz First', $this->firstFilm($app, '/library/all?set=700'));

        $this->get($app, '/library/all?sort=release');
        self::assertSame('Aaa Last', $this->firstFilm($app, '/library/all?set=700'));
    }

    /**
     * The toolbar must look identical either side of opening a set. A control
     * that changes on its own is the whole of what went wrong before, and it is
     * invisible to a test that only checks the grid.
     */
    public function testOpeningASetDoesNotChangeTheSortControl(): void
    {
        $app = $this->app();
        $this->get($app, '/library/all?sort=release');

        $before = $this->sortControl((string) $this->get($app, '/library/all')->getBody());
        $inSet = $this->sortControl((string) $this->get($app, '/library/all?set=700')->getBody());

        self::assertSame($before, $inSet, 'the sort control must not change because a set is open');
    }

    /**
     * The sort control's displayed STATE, for comparing one render against
     * another: which field is active, what each button reads, and which way each
     * arrow points.
     *
     * The hrefs are stripped, and that is the point of the method rather than a
     * convenience. They are SUPPOSED to differ inside a set — each carries the
     * set forward, so pressing a sort button re-orders the set instead of
     * dropping out of it — while everything a reader actually sees must be
     * identical. Comparing the raw markup would fail on the one difference that
     * is meant to be there and hide the ones that are not.
     */
    private function sortControl(string $body): string
    {
        self::assertSame(1, preg_match('/<div class="sort".*?<\/div>/s', $body, $m));

        $stripped = preg_replace('/\s*href="[^"]*"/', '', $m[0] ?? '');
        self::assertIsString($stripped);

        return $stripped;
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
        self::assertStringContainsString('for this set', $body);
    }

    /**
     * A sort chosen while a set is open is remembered like any other. The
     * earlier design deliberately did NOT record it, so that a set could not
     * re-sort the library behind you — which sounded careful and produced its own
     * surprise: an order you picked, then lost for no stated reason. One rule
     * for the control everywhere is easier to hold in your head than two.
     */
    public function testAnOrderChosenInsideASetIsRememberedLikeAnyOther(): void
    {
        $app = $this->app();

        $this->get($app, '/library/all?sort=alphabetical');
        $this->get($app, '/library/all?set=700&sort=alphabetical_desc');

        self::assertSame(
            'Zzz First',
            $this->firstFilm($app, '/library/all'),
            'Z–A, chosen while the set was open and kept afterwards',
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

        self::assertStringContainsString('No posters for this set in Collections', $body);
        self::assertStringNotContainsString('No posters yet', $body);
    }

    public function testPagingKeepsTheSet(): void
    {
        $app = $this->app();
        $body = (string) $this->get($app, '/library/all?set=700')->getBody();

        // Two films and a default page size, so there is no second page to link
        // to; what matters is that the set survives the round trip at all.
        self::assertStringContainsString('for this set', $body);
        self::assertStringContainsString('Aaa Last', (string) $this->get($app, '/library/all?set=700&page=1')->getBody());
    }

    /**
     * Release still rests the way Date added rests. That rule came from the same
     * round of feedback and survives this change: it is about the two time
     * fields agreeing with each other, not about sets.
     */
    public function testBrowsingByReleaseLeadsWithTheNewest(): void
    {
        $app = $this->app();

        self::assertSame('Aaa Last', $this->firstFilm($app, '/library/all?sort=release'));
    }
}
