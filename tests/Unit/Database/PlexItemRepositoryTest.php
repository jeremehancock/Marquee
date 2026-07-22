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
