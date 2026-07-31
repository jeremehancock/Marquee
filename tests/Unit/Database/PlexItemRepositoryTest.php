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
            ['Lucky_2026_-_Season_1_TV.jpg' => 'Lucky (2026) - Season 1'],
            $repo->titlesForCategory('tv-seasons'),
        );
    }

    /**
     * An empty title must be absent rather than mapped to "", so the caller falls
     * back to the filename instead of rendering a blank caption.
     */
    public function testTitlesForCategoryOmitsEmptyTitles(): void
    {
        $repo = $this->repository();
        $repo->upsert(new PlexItemRecord('10', 'movie', 'movies', 'Movies', '', 'Solaris.jpg', time()));

        self::assertSame([], $repo->titlesForCategory('movies'));
    }

    public function testTitlesForCategoryIgnoresOtherCategories(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertSame(['Solaris.jpg' => 'Solaris'], $repo->titlesForCategory('movies'));
        self::assertSame([], $repo->titlesForCategory('tv-shows'));
    }

    public function testYearsForCategoryMapsFilenamesToYears(): void
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

        self::assertSame(['Solaris.jpg' => 1972], $repo->yearsForCategory('movies'));
    }

    /**
     * A poster with no year must be absent from the map rather than present with
     * a zero: the gallery shows those titles unchanged, and "(0)" would be worse
     * than no year at all.
     */
    public function testYearsForCategoryOmitsRowsWithNoYear(): void
    {
        $repo = $this->repository();
        $repo->upsert($this->record('10', 'Solaris.jpg'));

        self::assertSame([], $repo->yearsForCategory('movies'));
    }

    public function testYearsForCategoryIgnoresOtherCategories(): void
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
        ));

        self::assertSame([], $repo->yearsForCategory('movies'));
        self::assertSame(['bb-s2.jpg' => 2009], $repo->yearsForCategory('tv-seasons'));
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
