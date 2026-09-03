<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Config\PosterConfig;
use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Poster\PosterFacts;
use App\Poster\PosterFactsIndex;
use App\Poster\PosterLibrary;
use App\Poster\Search\PosterSearch;
use App\Poster\SortComparator;
use App\Poster\SortOrder;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;

final class PosterLibraryTest extends TestCase
{
    use MakesImages;

    private string $dir;
    private PlexItemRepository $items;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
        mkdir($this->dir . '/movies');
        $this->items = new PlexItemRepository(new Database(':memory:'));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    /**
     * @param list<string> $filenames
     */
    private function library(array $filenames, int $perPage = 24, bool $ignoreArticles = true): PosterLibrary
    {
        foreach ($filenames as $name) {
            $this->writePng($this->dir . '/movies/' . $name);
        }

        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $config = new PosterConfig($perPage, 5_000_000, ['jpg', 'jpeg', 'png', 'webp'], $ignoreArticles, SortOrder::Alphabetical);
        $comparator = new SortComparator($config);

        return new PosterLibrary($storage, new PosterSearch(), $config, $this->items, $comparator);
    }

    /**
     * The recorded facts for some categories, read the way the gallery reads
     * them: one query each, through the repository these tests already write to.
     * The library no longer reads them itself, so a test that records something
     * has to hand it over the same way the controller does.
     */
    private function recordedFacts(string ...$categories): PosterFactsIndex
    {
        $facts = [];
        foreach ($categories as $category) {
            $facts[$category] = $this->items->factsForCategory($category);
        }

        return new PosterFactsIndex($facts);
    }

    /**
     * A facts index carrying nothing but Plex timestamps for the movies
     * category — what the date-added sort reads.
     *
     * @param array<string, int> $timestamps keyed by filename
     */
    private function addedAt(array $timestamps): PosterFactsIndex
    {
        $facts = [];
        foreach ($timestamps as $filename => $addedAt) {
            $facts[$filename] = PosterFacts::fromRecorded('', null, null, '', [], $addedAt);
        }

        return new PosterFactsIndex(['movies' => $facts]);
    }

    /**
     * Search decides WHICH posters match; the sort order decides how they are
     * listed. Matching on the recorded Plex title rather than the filename moved
     * the first of those and must not have touched the second — sortKey() still
     * derives from the filename, so a poster whose recorded title sorts somewhere
     * else entirely keeps its place in the listing.
     */
    public function testSearchingOnRecordedTitlesDoesNotReorderTheResults(): void
    {
        $library = $this->library(['Alien.png', 'Solaris.png', 'Zodiac.png']);
        foreach ([['1', 'Alien.png'], ['2', 'Solaris.png'], ['3', 'Zodiac.png']] as [$key, $file]) {
            // Every recorded title shares the query term, and each sorts in the
            // opposite direction to its filename, so a haystack that leaked into
            // the ordering would be plainly visible here.
            $this->items->upsert(new PlexItemRecord(
                $key,
                'movie',
                'movies',
                'Movies',
                'Voyage ' . (4 - (int) $key),
                $file,
                time(),
                '1',
            ));
        }

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(
                PosterCategory::Movies,
                'voyage',
                1,
                SortOrder::Alphabetical,
                $this->recordedFacts('movies'),
            )->items,
        );

        self::assertSame(['Alien', 'Solaris', 'Zodiac'], $titles);
    }

    /**
     * The map reaches the filter for the All view too, where four categories are
     * merged and a filename is unique only within one of them.
     *
     * The accented title is the point: `Am_lie_Movies.png` is what import stores
     * for "Amélie", and before the recorded title became the haystack this query
     * matched nothing at all.
     *
     * A title in a script the filename sanitiser discards entirely — Cyrillic,
     * CJK — is a different matter and remains out of reach: normalize() keeps
     * only a-z0-9, so such a QUERY reduces to no terms and the filter returns the
     * unfiltered list. That is pre-existing and deliberately not addressed here.
     */
    public function testTheAggregateViewSearchesOnRecordedTitles(): void
    {
        mkdir($this->dir . '/collections');
        $library = $this->library(['Am_lie_Movies.png']);
        $this->writePng($this->dir . '/collections/Am_lie_Collection.png');

        $this->items->upsert(new PlexItemRecord(
            '1',
            'movie',
            'movies',
            'Movies',
            'Amélie',
            'Am_lie_Movies.png',
            time(),
            '1',
        ));

        $found = $library->browseAll(
            'Amélie',
            1,
            SortOrder::Alphabetical,
            $this->recordedFacts('movies', 'tv-shows', 'tv-seasons', 'collections'),
        )->items;

        self::assertCount(1, $found);
        self::assertSame('Am_lie_Movies.png', $found[0]->filename);
        self::assertSame(PosterCategory::Movies, $found[0]->category);
    }

    public function testArticleAwareSort(): void
    {
        $library = $this->library(['The Matrix.png', 'Alien.png', 'Zodiac.png']);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1)->items,
        );

        self::assertSame(['Alien', 'The Matrix', 'Zodiac'], $titles);
    }

    /**
     * The reported defect, end to end through the library rather than on the
     * sort key alone: A-Z must list a show's seasons 1, 2, 3 ... 10, 11.
     */
    public function testAlphabeticalSortOrdersSeasonsByNumber(): void
    {
        $library = $this->library([
            'Breaking Bad - Season 11.png',
            'Breaking Bad - Season 2.png',
            'Breaking Bad - Season 10.png',
            'Breaking Bad - Season 1.png',
            'Breaking Bad - Season 9.png',
        ]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1)->items,
        );

        self::assertSame([
            'Breaking Bad - Season 1',
            'Breaking Bad - Season 2',
            'Breaking Bad - Season 9',
            'Breaking Bad - Season 10',
            'Breaking Bad - Season 11',
        ], $titles);
    }

    /**
     * Digit-aware keys make these two genuinely equal — both pad to the same
     * number — so without a further tiebreak usort is free to return them in
     * either order. Sorting the same posters twice must not disagree.
     */
    public function testALeadingZeroTieIsDeterministic(): void
    {
        $library = $this->library(['Season 01.png', 'Season 1.png']);

        $first = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1)->items,
        );
        $second = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1)->items,
        );

        self::assertSame($first, $second);
        self::assertCount(2, $first);
    }

    public function testDateAddedSortOrdersNewestFirst(): void
    {
        $library = $this->library(['Alien.png', 'Matrix.png', 'Zodiac.png']);

        $facts = $this->addedAt([
            'Alien.png' => 100,
            'Matrix.png' => 300,
            'Zodiac.png' => 200,
        ]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1, SortOrder::DateAdded, $facts)->items,
        );

        self::assertSame(['Matrix', 'Zodiac', 'Alien'], $titles);
    }

    public function testDateAddedSortFallsBackToFileMtime(): void
    {
        $library = $this->library(['Old.png', 'Mid.png', 'New.png']);

        // No Plex timestamps: ordering must come from file modification time.
        touch($this->dir . '/movies/Old.png', 1_000);
        touch($this->dir . '/movies/Mid.png', 2_000);
        touch($this->dir . '/movies/New.png', 3_000);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1, SortOrder::DateAdded, new PosterFactsIndex())->items,
        );

        self::assertSame(['New', 'Mid', 'Old'], $titles);
    }

    public function testDateAddedSortMixesStoredTimestampWithMtimeFallback(): void
    {
        $library = $this->library(['Stored.png', 'Fallback.png']);

        // Stored has an explicit (older) Plex timestamp; Fallback has none and
        // uses its newer file mtime — so Fallback should sort first.
        touch($this->dir . '/movies/Fallback.png', 5_000);
        $facts = $this->addedAt(['Stored.png' => 1_000]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1, SortOrder::DateAdded, $facts)->items,
        );

        self::assertSame(['Fallback', 'Stored'], $titles);
    }

    public function testDescendingTitleSortReversesTheOrder(): void
    {
        $library = $this->library(['The Matrix.png', 'Alien.png', 'Zodiac.png']);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1, SortOrder::AlphabeticalDesc)->items,
        );

        // Still article-aware: "The Matrix" files under M, so it stays in the middle.
        self::assertSame(['Zodiac', 'The Matrix', 'Alien'], $titles);
    }

    /**
     * Direction reverses the field the user picked and nothing beneath it. The
     * seasons all compare equal on nothing — they differ by number — so Z–A puts
     * the highest first, but the numbers stay ordered by value rather than
     * degrading to 9, 11, 10, 1.
     */
    public function testDescendingTitleSortStillOrdersNumbersByValue(): void
    {
        $library = $this->library([
            'Show - Season 1.png',
            'Show - Season 10.png',
            'Show - Season 2.png',
            'Show - Season 11.png',
        ]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1, SortOrder::AlphabeticalDesc)->items,
        );

        self::assertSame([
            'Show - Season 11',
            'Show - Season 10',
            'Show - Season 2',
            'Show - Season 1',
        ], $titles);
    }

    /**
     * A tie on the chosen field keeps its ascending category order in either
     * direction — otherwise reversing would scramble posters the user never
     * asked to reorder.
     */
    public function testReversingDoesNotReverseTheCategoryTieBreak(): void
    {
        $library = $this->libraryAcross([
            'collections' => ['Alien.png'],
            'tv-seasons' => ['Alien.png'],
            'movies' => ['Alien.png'],
        ]);

        $categories = array_map(
            static fn ($p): string => $p->category->value,
            $library->browseAll(null, 1, SortOrder::AlphabeticalDesc)->items,
        );

        self::assertSame(['movies', 'tv-seasons', 'collections'], $categories);
    }

    public function testDateAddedAscendingOrdersOldestFirst(): void
    {
        $library = $this->library(['Alien.png', 'Matrix.png', 'Zodiac.png']);

        $facts = $this->addedAt([
            'Alien.png' => 100,
            'Matrix.png' => 300,
            'Zodiac.png' => 200,
        ]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1, SortOrder::DateAddedAsc, $facts)->items,
        );

        self::assertSame(['Alien', 'Zodiac', 'Matrix'], $titles);
    }

    public function testDateAddedAscendingAlsoFallsBackToFileMtime(): void
    {
        $library = $this->library(['Old.png', 'Mid.png', 'New.png']);

        touch($this->dir . '/movies/Old.png', 1_000);
        touch($this->dir . '/movies/Mid.png', 2_000);
        touch($this->dir . '/movies/New.png', 3_000);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1, SortOrder::DateAddedAsc, new PosterFactsIndex())->items,
        );

        self::assertSame(['Old', 'Mid', 'New'], $titles);
    }

    /**
     * The reported defect: sorting by date added while a search was active only
     * appeared to work. Match position used to lead, so a poster whose title
     * merely contained the query was held below every title beginning with it —
     * and the newest poster could land last under newest-first. The sort now
     * orders whatever the search leaves, whatever the query matched.
     */
    public function testDateSortOrdersSearchResultsOutright(): void
    {
        $library = $this->library([
            'Alien.png',
            'Aliens.png',
            'Alien Covenant.png',
            'The Alien Movie.png',
        ]);

        // The one poster that does not begin with the query is by far the newest.
        $facts = $this->addedAt([
            'Alien.png' => 100,
            'Aliens.png' => 200,
            'Alien Covenant.png' => 300,
            'The Alien Movie.png' => 9_999,
        ]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, 'alien', 1, SortOrder::DateAdded, $facts)->items,
        );

        self::assertSame([
            'The Alien Movie',
            'Alien Covenant',
            'Aliens',
            'Alien',
        ], $titles);
    }

    public function testSearchResultsReverseWithTheSortOrder(): void
    {
        $library = $this->library(['Alien.png', 'Aliens.png', 'Alien Covenant.png']);

        $ascending = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, 'alien', 1, SortOrder::Alphabetical)->items,
        );
        $descending = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, 'alien', 1, SortOrder::AlphabeticalDesc)->items,
        );

        self::assertSame(['Alien', 'Alien Covenant', 'Aliens'], $ascending);
        self::assertSame(array_reverse($ascending), $descending);
    }

    /**
     * Searching narrows the listing and changes nothing else, so a filtered view
     * is ordered exactly as the same posters would be unfiltered.
     */
    public function testSearchingDoesNotReorderWhatItLeaves(): void
    {
        $library = $this->library(['Alien.png', 'Aliens.png', 'Dune.png', 'Alien Covenant.png']);

        $unfiltered = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1)->items,
        );
        $filtered = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, 'alien', 1)->items,
        );

        self::assertSame(
            array_values(array_filter($unfiltered, static fn (string $t): bool => str_contains($t, 'Alien'))),
            $filtered,
        );
    }

    public function testAggregateViewAppliesEveryDirection(): void
    {
        $library = $this->libraryAcross([
            'movies' => ['Zodiac.png'],
            'tv-shows' => ['Alien.png'],
            'collections' => ['Matrix.png'],
        ]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browseAll(null, 1, SortOrder::AlphabeticalDesc)->items,
        );

        self::assertSame(['Zodiac', 'Matrix', 'Alien'], $titles);
    }

    public function testPagination(): void
    {
        $library = $this->library(['A.png', 'B.png', 'C.png', 'D.png', 'E.png'], perPage: 2);

        $page1 = $library->browse(PosterCategory::Movies, null, 1);
        self::assertSame(5, $page1->total);
        self::assertSame(3, $page1->totalPages());
        self::assertCount(2, $page1->items);
        self::assertTrue($page1->hasNext());
        self::assertFalse($page1->hasPrevious());

        self::assertCount(1, $library->browse(PosterCategory::Movies, null, 3)->items);
    }

    public function testOutOfRangePageIsClamped(): void
    {
        $library = $this->library(['A.png', 'B.png', 'C.png'], perPage: 2);

        $page = $library->browse(PosterCategory::Movies, null, 99);

        self::assertSame(2, $page->page);
        self::assertCount(1, $page->items);
    }

    public function testSearchFiltersWithinCategory(): void
    {
        $library = $this->library(['Star Wars.png', 'Star Trek.png']);

        $result = $library->browse(PosterCategory::Movies, 'wars', 1);

        self::assertSame(1, $result->total);
        self::assertSame('Star Wars', $result->items[0]->title());
    }

    public function testDeleteRemovesPoster(): void
    {
        $library = $this->library(['Gone.png']);

        self::assertTrue($library->delete(PosterCategory::Movies, 'Gone.png'));
        self::assertSame(0, $library->browse(PosterCategory::Movies, null, 1)->total);
    }

    public function testDeleteClearsThePlexMapping(): void
    {
        $library = $this->library(['Gone.png']);
        $this->items->upsert(
            new PlexItemRecord('42', 'movie', 'movies', 'Movies', 'Gone.png', 'Gone.png', time(), '1'),
        );

        self::assertTrue($library->delete(PosterCategory::Movies, 'Gone.png'));
        self::assertNull($this->items->findByRatingKey('42'));
    }

    public function testDeleteClearsEveryMappingSharingTheFilename(): void
    {
        // A stale mapping (from a since-recreated Plex item) and the live one
        // point at the same file; deleting the poster must forget both so the
        // stale row can never resurface as a duplicate orphan.
        $library = $this->library(['Test Collection.png']);
        $this->items->upsert(
            new PlexItemRecord('99', 'movie', 'movies', 'Movies', 'Test Collection.png', 'Test Collection.png', time(), '1'),
        );
        $this->items->upsert(
            new PlexItemRecord('100', 'movie', 'movies', 'Movies', 'Test Collection.png', 'Test Collection.png', time(), '1'),
        );

        self::assertTrue($library->delete(PosterCategory::Movies, 'Test Collection.png'));
        self::assertNull($this->items->findByRatingKey('99'));
        self::assertNull($this->items->findByRatingKey('100'));
    }

    public function testDeleteOfMissingFileDoesNotTouchMappings(): void
    {
        // No file on disk: storage delete fails, so the mapping is left intact
        // rather than being cleared on a failed delete.
        $library = $this->library([]);
        $this->items->upsert(
            new PlexItemRecord('7', 'movie', 'movies', 'Movies', 'Absent.png', 'Absent.png', time(), '1'),
        );

        self::assertFalse($library->delete(PosterCategory::Movies, 'Absent.png'));
        self::assertNotNull($this->items->findByRatingKey('7'));
    }

    /**
     * @param array<string, list<string>> $byCategory
     */
    private function libraryAcross(array $byCategory, int $perPage = 24): PosterLibrary
    {
        foreach ($byCategory as $category => $filenames) {
            $dir = $this->dir . '/' . $category;
            if (!is_dir($dir)) {
                mkdir($dir, 0o775, true);
            }
            foreach ($filenames as $name) {
                $this->writePng($dir . '/' . $name);
            }
        }

        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $config = new PosterConfig($perPage, 5_000_000, ['jpg', 'jpeg', 'png', 'webp'], true, SortOrder::Alphabetical);
        $comparator = new SortComparator($config);

        return new PosterLibrary($storage, new PosterSearch(), $config, $this->items, $comparator);
    }

    public function testBrowseAllMergesCategoriesInMixedTitleOrder(): void
    {
        $library = $this->libraryAcross([
            'movies' => ['Zodiac.png'],
            'tv-shows' => ['Alien.png'],
            'collections' => ['Matrix.png'],
        ]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browseAll(null, 1)->items,
        );

        // Mixed across types, not grouped by category.
        self::assertSame(['Alien', 'Matrix', 'Zodiac'], $titles);
    }

    public function testBrowseAllBreaksTitleTiesByCategoryOrder(): void
    {
        $library = $this->libraryAcross([
            'collections' => ['Alien.png'],
            'tv-seasons' => ['Alien.png'],
            'movies' => ['Alien.png'],
        ]);

        $categories = array_map(
            static fn ($p): string => $p->category->value,
            $library->browseAll(null, 1)->items,
        );

        // Equal titles ordered Movies -> TV Seasons -> Collections.
        self::assertSame(['movies', 'tv-seasons', 'collections'], $categories);
    }

    public function testBrowseAllOrdersNumbersByValueAcrossCategories(): void
    {
        $library = $this->libraryAcross([
            'tv-seasons' => ['Show - Season 10.png', 'Show - Season 2.png'],
            'movies' => ['Show - Season 1.png'],
        ]);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browseAll(null, 1)->items,
        );

        self::assertSame(['Show - Season 1', 'Show - Season 2', 'Show - Season 10'], $titles);
    }

    public function testBrowseAllPaginatesCombinedTotal(): void
    {
        $library = $this->libraryAcross([
            'movies' => ['A.png', 'B.png'],
            'tv-shows' => ['C.png', 'D.png'],
            'collections' => ['E.png'],
        ], perPage: 2);

        $page1 = $library->browseAll(null, 1);

        self::assertSame(5, $page1->total);
        self::assertSame(3, $page1->totalPages());
        self::assertCount(2, $page1->items);
    }
}
