<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Tests\AppTestCase;
use App\Tests\Support\CountingStatement;
use App\Tests\Support\MakesImages;
use PDO;
use Slim\App;

/**
 * How many times a render reads the poster mapping.
 *
 * This is pinned because it is the one property of the read path that nothing
 * else would notice going wrong. Rendering the All view used to cost twenty
 * scans of the same four categories, and a filtered one twenty-four, because
 * every fact a card shows was fetched by a query of its own — and each new fact
 * added another. Nothing failed; the gallery simply got slower every time it
 * learned to show something.
 *
 * So the requirement is not "few reads" but "a number decided by how many
 * categories the view holds, and by nothing else": not by the sort order, not by
 * whether a query or a set is active, not by how many facts the cards show. A
 * test that counted a magic number would pass just as happily at nineteen, which
 * is why each case below is compared against the unfiltered view rather than
 * against a literal.
 */
final class GalleryReadCostTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        foreach (['movies', 'tv-shows', 'tv-seasons', 'collections'] as $category) {
            mkdir($this->postersDir . '/' . $category, 0o775, true);
        }
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
        $app = $this->makeSignedInApp(['POSTERS_DIR' => $this->postersDir]);
        $container = $app->getContainer();
        self::assertNotNull($container);

        /** @var PlexItemRepository $items */
        $items = $container->get(PlexItemRepository::class);

        // A poster in each category, one of them in a set, so every case below
        // has something to read and the set case has something to show.
        $this->writePng($this->postersDir . '/movies/Godzilla.png');
        $items->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Godzilla',
            'Godzilla.png',
            time(),
            addedAt: 1_700_000_000,
            year: 2014,
            setKeys: ['900'],
        ));

        $this->writePng($this->postersDir . '/collections/MonsterVerse.png');
        $items->upsert(new PlexItemRecord(
            '900',
            'collection',
            'collections',
            'Movies',
            'MonsterVerse',
            'MonsterVerse.png',
            time(),
            setKeys: ['900'],
        ));

        $this->writePng($this->postersDir . '/tv-shows/Show.png');
        $this->writePng($this->postersDir . '/tv-seasons/Show_-_Season_1.png');

        return $app;
    }

    /**
     * Count the statements one request runs against the mapping database.
     *
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    private function readsFor(App $app, string $path): int
    {
        $container = $app->getContainer();
        self::assertNotNull($container);
        /** @var Database $database */
        $database = $container->get(Database::class);

        CountingStatement::$count = 0;
        $database->pdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [CountingStatement::class, []]);

        $this->get($app, $path);

        $counted = CountingStatement::$count;
        $database->pdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class, []]);

        return $counted;
    }

    /**
     * The absolute count includes reads a request makes for its own reasons —
     * the session, the settings store, the update check — which are no business
     * of this test and would make it fail for unrelated changes. The DIFFERENCE
     * between a four-category view and a one-category view is the property being
     * pinned, and it isolates exactly the reads that scale.
     */
    public function testTheAggregateViewScansOncePerCategory(): void
    {
        $app = $this->app();

        $all = $this->readsFor($app, '/library/all');
        $one = $this->readsFor($app, '/library/movies');

        self::assertSame(3, $all - $one, 'All holds four categories; Movies holds one');
    }

    public function testFilteringAddsNoReads(): void
    {
        $app = $this->app();
        $unfiltered = $this->readsFor($app, '/library/all');

        self::assertSame($unfiltered, $this->readsFor($app, '/library/all?q=godzilla'));
    }

    /**
     * A set view scans no more than the unfiltered one. It does make one further
     * read — the keyed lookup that gives the set its name — which is once per
     * render rather than once per category, and so is not one of the reads this
     * requirement is about. Asserted exactly rather than loosely, so that a
     * second scan sneaking in here would still fail.
     */
    public function testShowingASetAddsOnlyTheLookupThatNamesIt(): void
    {
        $app = $this->app();
        $unfiltered = $this->readsFor($app, '/library/all');

        self::assertSame($unfiltered + 1, $this->readsFor($app, '/library/all?set=900'));
    }

    public function testSortingByDateAddedAddsNoReads(): void
    {
        $app = $this->app();
        $unfiltered = $this->readsFor($app, '/library/all');

        // The timestamp used to be fetched by a scan of its own, and only under
        // this sort. It is a column of the read that now always runs.
        self::assertSame($unfiltered, $this->readsFor($app, '/library/all?sort=date_added'));
    }
}
