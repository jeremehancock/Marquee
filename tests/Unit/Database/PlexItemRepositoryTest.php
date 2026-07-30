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
