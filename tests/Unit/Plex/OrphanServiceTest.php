<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\Orphan\OrphanService;
use App\Plex\PlexException;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;

final class OrphanServiceTest extends TestCase
{
    use MakesImages;

    private string $dir;
    private PlexItemRepository $items;
    private FilesystemPosterStorage $storage;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
        mkdir($this->dir . '/movies');
        $this->items = new PlexItemRepository(new Database(':memory:'));
        $this->storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function seed(string $filename, string $ratingKey): void
    {
        $this->writePng($this->dir . '/movies/' . $filename);
        $this->items->upsert(
            new PlexItemRecord($ratingKey, 'movie', 'movies', 'Movies', $filename, $filename, time(), '1'),
        );
    }

    /**
     * Plex still has the movie with rating key "10"; "99" is gone.
     */
    private function service(bool $configured = true): OrphanService
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $stillThere = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $plex = new FakePlexClient([$library], ['1' => [$stillThere]], configured: $configured);

        return new OrphanService($plex, $this->items, $this->storage);
    }

    public function testMappedButMissingItemIsOrphan(): void
    {
        $this->seed('Solaris.jpg', '10');
        $this->seed('Gone.jpg', '99');

        $orphans = $this->service()->findOrphans();

        self::assertCount(1, $orphans);
        self::assertSame('Gone.jpg', $orphans[0]->filename);
    }

    public function testManualUploadIsNotAnOrphan(): void
    {
        // A file with no mapping in the database.
        $this->writePng($this->dir . '/movies/Manual.jpg');

        self::assertSame([], $this->service()->findOrphans());
    }

    public function testMissingFileMappingIsPrunedNotOrphaned(): void
    {
        // A live poster and a stale mapping whose file was already deleted.
        $this->seed('Solaris.jpg', '10');
        $this->items->upsert(
            new PlexItemRecord('99', 'movie', 'movies', 'Movies', 'Gone.jpg', 'Gone.jpg', time(), '1'),
        );

        $orphans = $this->service()->findOrphans();

        // The file-less mapping is pruned during detection, not listed.
        self::assertSame([], $orphans);
        self::assertNull($this->items->findByRatingKey('99'));
        // The live poster's mapping is left untouched.
        self::assertNotNull($this->items->findByRatingKey('10'));
    }

    public function testRecreatedItemDoesNotYieldDuplicateOrphans(): void
    {
        // The stale mapping from the original item (rating key 99), file already
        // deleted, alongside the re-imported item (rating key 100) sharing the
        // same filename. Only the live-but-missing-from-Plex one is an orphan.
        $this->items->upsert(
            new PlexItemRecord('99', 'movie', 'movies', 'Movies', 'Test Collection.jpg', 'Test Collection.jpg', time(), '1'),
        );
        $this->seed('Test Collection.jpg', '100');

        $orphans = $this->service()->findOrphans();

        // Listed exactly once for the shared file; the redundant mapping is
        // pruned, leaving exactly one of the two behind (which one is immaterial
        // — both point at the same poster).
        self::assertCount(1, $orphans);
        self::assertSame('Test Collection.jpg', $orphans[0]->filename);
        $remaining = array_filter(
            ['99', '100'],
            fn (string $key): bool => $this->items->findByRatingKey($key) !== null,
        );
        self::assertCount(1, $remaining);
    }

    public function testDeleteAllRemovesOrphansOnly(): void
    {
        $this->seed('Solaris.jpg', '10');
        $this->seed('Gone.jpg', '99');

        $removed = $this->service()->deleteAll();

        self::assertSame(1, $removed);
        self::assertFileDoesNotExist($this->dir . '/movies/Gone.jpg');
        self::assertFileExists($this->dir . '/movies/Solaris.jpg');
        self::assertNull($this->items->findByRatingKey('99'));
    }

    public function testDeleteRemovesASingleOrphan(): void
    {
        $this->seed('Solaris.jpg', '10');
        $this->seed('Gone.jpg', '99');

        $deleted = $this->service()->delete(PosterCategory::Movies, 'Gone.jpg');

        self::assertTrue($deleted);
        self::assertFileDoesNotExist($this->dir . '/movies/Gone.jpg');
        self::assertNull($this->items->findByRatingKey('99'));
        self::assertFileExists($this->dir . '/movies/Solaris.jpg');
        self::assertNotNull($this->items->findByRatingKey('10'));
    }

    public function testDeleteLeavesNonOrphansUntouched(): void
    {
        $this->seed('Solaris.jpg', '10');

        // "Solaris" still exists in Plex (rating key 10), so it is not an orphan
        // and must never be removed through the orphan-delete path.
        $deleted = $this->service()->delete(PosterCategory::Movies, 'Solaris.jpg');

        self::assertFalse($deleted);
        self::assertFileExists($this->dir . '/movies/Solaris.jpg');
        self::assertNotNull($this->items->findByRatingKey('10'));
    }

    public function testDeleteUnknownFilenameIsANoOp(): void
    {
        $this->seed('Gone.jpg', '99');

        self::assertFalse($this->service()->delete(PosterCategory::Movies, 'Missing.jpg'));
        self::assertFileExists($this->dir . '/movies/Gone.jpg');
    }

    public function testDeleteThrowsWhenPlexUnconfigured(): void
    {
        $this->seed('Gone.jpg', '99');

        $this->expectException(PlexException::class);
        $this->service(configured: false)->delete(PosterCategory::Movies, 'Gone.jpg');
    }

    public function testUnconfiguredPlexThrows(): void
    {
        $this->expectException(PlexException::class);
        $this->service(configured: false)->findOrphans();
    }
}
