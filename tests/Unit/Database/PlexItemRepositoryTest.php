<?php

declare(strict_types=1);

namespace App\Tests\Unit\Database;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Database\PlexLibraryRepository;
use App\Plex\PlexLibrary;
use PHPUnit\Framework\TestCase;

final class PlexItemRepositoryTest extends TestCase
{
    private function repository(): PlexItemRepository
    {
        return new PlexItemRepository(new Database(':memory:'));
    }

    private function record(string $ratingKey, string $filename): PlexItemRecord
    {
        return new PlexItemRecord($ratingKey, 'movie', 'movies', 'Movies', 'Solaris', $filename, time());
    }

    public function testUpsertAndFind(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        $found = $repo->findByRatingKey('10');

        self::assertNotNull($found);
        self::assertSame('Solaris.jpg', $found->filename);
    }

    public function testUpsertUpdatesInsteadOfDuplicating(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));
        $repo->upsert($this->record('10', 'Solaris-new.jpg'));

        self::assertCount(1, $repo->all());
        self::assertSame('Solaris-new.jpg', $repo->findByRatingKey('10')?->filename);
    }

    public function testMissingKeyReturnsNull(): void
    {
        self::assertNull($this->repository()->findByRatingKey('999'));
    }

    public function testRoundTripsYearAndSeasonNumber(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord(
            '20',
            'season',
            'tv-seasons',
            'TV',
            'Breaking Bad - Season 2',
            'bb-s2.jpg',
            time(),
            year: 2009,
            seasonNumber: 2,
        ));

        $found = $repo->findByRatingKey('20');

        self::assertNotNull($found);
        self::assertSame(2009, $found->year);
        self::assertSame(2, $found->seasonNumber);
    }

    /**
     * Season 0 is Specials. It must survive the round trip as 0 and not come back
     * as null, which is what a defaulted-integer column would have produced.
     */
    public function testSeasonZeroRoundTripsAsZeroNotNull(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord(
            '21',
            'season',
            'tv-seasons',
            'TV',
            'Breaking Bad - Specials',
            'bb-sp.jpg',
            time(),
            seasonNumber: 0,
        ));

        $found = $repo->findByRatingKey('21');

        self::assertNotNull($found);
        self::assertNotNull($found->seasonNumber);
        self::assertSame(0, $found->seasonNumber);
    }

    public function testAbsentYearAndSeasonNumberStayNull(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        $found = $repo->findByRatingKey('10');

        self::assertNotNull($found);
        self::assertNull($found->year);
        self::assertNull($found->seasonNumber);
    }

    /**
     * A row written before these columns existed gains them on the next import,
     * so no re-import-from-scratch is needed.
     */
    public function testReUpsertUpdatesYearAndSeasonNumberOnAnExistingRow(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));
        self::assertNull($repo->findByRatingKey('10')?->year);

        $repo->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Solaris.jpg',
            time(),
            year: 1972,
        ));

        self::assertCount(1, $repo->all());
        self::assertSame(1972, $repo->findByRatingKey('10')?->year);
    }

    public function testRoundTripsTmdbId(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Spider-Noir B&W',
            'spider-noir.jpg',
            time(),
            tmdbId: '385128',
        ));

        $found = $repo->findByRatingKey('10');

        self::assertNotNull($found);
        self::assertSame('385128', $found->tmdbId);
    }

    public function testAbsentTmdbIdStaysNull(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertNull($repo->findByRatingKey('10')?->tmdbId);
    }

    /**
     * A row written before this column existed gains the id on the next import,
     * so an install upgrading into this change needs no rebuild.
     */
    public function testReUpsertUpdatesTmdbIdOnAnExistingRow(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));
        self::assertNull($repo->findByRatingKey('10')?->tmdbId);

        $repo->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Solaris.jpg',
            time(),
            tmdbId: '1726',
        ));

        self::assertCount(1, $repo->all());
        self::assertSame('1726', $repo->findByRatingKey('10')?->tmdbId);
    }

    public function testTitlesForCategoryMapsFilenamesToRecordedTitles(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord(
            '20',
            'season',
            'tv-seasons',
            'TV',
            'Lucky (2026) - Season 1',
            'Lucky_2026_-_Season_1_TV.jpg',
            time(),
        ));

        // The parentheses the filename lost are intact in the record — the whole
        // reason the caption reads from here.
        self::assertSame(
            'Lucky (2026) - Season 1',
            $repo->factsForCategory('tv-seasons')['Lucky_2026_-_Season_1_TV.jpg']->title,
        );
    }

    /**
     * An empty title must read as null rather than "", so the caller falls back
     * to the filename instead of rendering a blank caption.
     *
     * This used to be expressed by omitting the row. A combined read cannot omit
     * it — the same row still carries a year, a set and a timestamp — so the
     * fallback moved onto the value object, and both directions are asserted
     * here: a recorded title arrives, an empty one reads as absent.
     */
    public function testFactsReportAnEmptyTitleAsAbsent(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord('10', 'movie', 'movies', 'Movies', '', 'Solaris.jpg', time()));

        $facts = $repo->factsForCategory('movies')['Solaris.jpg'];
        self::assertTrue($facts->mapped, 'the row exists even though its title says nothing');
        self::assertNull($facts->title);
    }

    public function testFactsReportARecordedTitle(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertSame('Solaris', $repo->factsForCategory('movies')['Solaris.jpg']->title);
    }

    public function testFactsIgnoreOtherCategories(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertArrayHasKey('Solaris.jpg', $repo->factsForCategory('movies'));
        self::assertSame([], $repo->factsForCategory('tv-shows'));
    }

    public function testFactsCarryTheRecordedYear(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Solaris.jpg',
            time(),
            year: 1972,
        ));

        self::assertSame(1972, $repo->factsForCategory('movies')['Solaris.jpg']->year);
    }

    /**
     * The other direction: no year reads as null rather than zero. The gallery
     * shows those titles unchanged, and "(0)" would be worse than no year.
     */
    public function testFactsReportAMissingYearAsAbsent(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertNull($repo->factsForCategory('movies')['Solaris.jpg']->year);
    }

    public function testFactsCarryTheRecordedTimestamp(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Solaris.jpg',
            time(),
            addedAt: 1700000000,
        ));

        self::assertSame(1700000000, $repo->factsForCategory('movies')['Solaris.jpg']->addedAt);
    }

    /**
     * Zero means "Plex told us nothing", not "the epoch" — the date sort falls
     * back to the file's own modification time for these.
     */
    public function testFactsReportAZeroTimestampAsAbsent(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertNull($repo->factsForCategory('movies')['Solaris.jpg']->addedAt);
    }

    public function testFactsCarryRecordedSets(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Godzilla vs. Kong',
            'gvk.jpg',
            time(),
            setKeys: ['500', '600'],
        ));

        self::assertSame(['500', '600'], $repo->factsForCategory('movies')['gvk.jpg']->setKeys);
    }

    public function testFactsReportNoSetsAsAnEmptyList(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertSame([], $repo->factsForCategory('movies')['Solaris.jpg']->setKeys);
    }

    /**
     * A season answers with its show's title so the action gathers the show and
     * its sibling seasons; everything else answers with its own.
     */
    public function testFactsResolveASeasonsRelatedTitle(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord(
            '20',
            'season',
            'tv-seasons',
            'TV',
            'Breaking Bad - Season 2',
            'bb-s2.jpg',
            time(),
            seasonNumber: 2,
            parentTitle: 'Breaking Bad',
        ));

        $facts = $repo->factsForCategory('tv-seasons')['bb-s2.jpg'];
        self::assertSame('Breaking Bad', $facts->relatedTitle);
        self::assertSame(2, $facts->seasonNumber);
    }

    public function testFactsResolveAMoviesRelatedTitleAsItsOwn(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertSame('Solaris', $repo->factsForCategory('movies')['Solaris.jpg']->relatedTitle);
    }

    public function testLibrarySyncIsIdempotent(): void
    {
        $repo = new PlexLibraryRepository(new Database(':memory:'));
        $repo->sync(new PlexLibrary('1', 'Movies', 'movie'));
        $repo->sync(new PlexLibrary('1', 'Movies Renamed', 'movie'));

        $all = $repo->all();
        self::assertCount(1, $all);
        self::assertSame('Movies Renamed', $all[0]->title);
    }
}
