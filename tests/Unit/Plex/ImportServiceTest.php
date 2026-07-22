<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Database\Database;
use App\Database\PlexItemRepository;
use App\Database\PlexLibraryRepository;
use App\Plex\Import\ImportService;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Poster\FilesystemPosterStorage;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;

final class ImportServiceTest extends TestCase
{
    use MakesImages;

    private string $dir;
    private PlexItemRepository $items;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function service(FakePlexClient $plex): ImportService
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $this->items = new PlexItemRepository($database);

        return new ImportService($plex, $storage, $this->items, new PlexLibraryRepository($database));
    }

    private function countFiles(string $sub): int
    {
        $dir = $this->dir . '/' . $sub;
        if (!is_dir($dir)) {
            return 0;
        }

        return count(array_filter(scandir($dir) ?: [], static fn (string $f): bool => is_file($dir . '/' . $f)));
    }

    public function testImportsMoviePosters(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $service = $this->service(new FakePlexClient([$library], ['1' => [$movie]]));

        $result = $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->imported());
        self::assertSame(1, $this->countFiles('movies'));
        self::assertNotNull($this->items->findByRatingKey('10'));
    }

    public function testReimportOverwritesWithoutDuplicating(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $service = $this->service(new FakePlexClient([$library], ['1' => [$movie]]));

        $service->import(['1'], [PlexMediaType::Movie]);
        $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testFailedDownloadIsCountedNotFatal(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $ok = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $bad = new PlexItem('11', PlexMediaType::Movie, 'Dune', 2021, '/t/11', 'Movies');
        $service = $this->service(new FakePlexClient([$library], ['1' => [$ok, $bad]], failingKeys: ['11']));

        $result = $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->imported());
        self::assertSame(1, $result->failed());
        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testSkipsUnchangedPostersAndReimportsChanged(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');

        // First import: downloads and stores.
        $v1 = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/1', 'Movies');
        $plex1 = new FakePlexClient([$library], ['1' => [$v1]]);
        $first = (new ImportService($plex1, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $first->imported());
        self::assertSame(['10'], $plex1->downloads);

        // Second import, same thumb version: skipped, no download.
        $again = (new ImportService($plex1, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(0, $again->imported());
        self::assertSame(1, $again->skipped());
        self::assertSame(['10'], $plex1->downloads);

        // Thumb version changed in Plex: re-downloaded.
        $v2 = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/2', 'Movies');
        $plex2 = new FakePlexClient([$library], ['1' => [$v2]]);
        $changed = (new ImportService($plex2, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $changed->imported());
        self::assertSame(0, $changed->skipped());
        self::assertSame(['10'], $plex2->downloads);
    }

    public function testForceReimportsUnchangedPosters(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/1', 'Movies');
        $plex = new FakePlexClient([$library], ['1' => [$movie]]);
        $service = new ImportService($plex, $storage, $items, $libraryRepo);

        $service->import(['1'], [PlexMediaType::Movie]);
        $forced = $service->import(['1'], [PlexMediaType::Movie], force: true);

        self::assertSame(1, $forced->imported());
        self::assertSame(0, $forced->skipped());
        self::assertSame(['10', '10'], $plex->downloads);
    }

    public function testReimportsWhenLocalFileIsMissing(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/1', 'Movies');
        $plex = new FakePlexClient([$library], ['1' => [$movie]]);
        $service = new ImportService($plex, $storage, $items, $libraryRepo);

        $service->import(['1'], [PlexMediaType::Movie]);
        array_map('unlink', glob($this->dir . '/movies/*') ?: []);

        // Same thumb, but the file is gone: it must be pulled again.
        $result = $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->imported());
        self::assertSame(0, $result->skipped());
        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testOnlySelectedMediaTypesAreImported(): void
    {
        $library = new PlexLibrary('2', 'TV', 'show');
        $show = new PlexItem('20', PlexMediaType::Show, 'Severance', null, '/t/20', 'TV');
        $season = new PlexItem('200', PlexMediaType::Season, 'Season 1', null, '/t/200', 'TV', 'Severance');
        $service = $this->service(new FakePlexClient([$library], ['2' => [$show]], ['20' => [$season]]));

        $service->import(['2'], [PlexMediaType::Show]);

        self::assertSame(1, $this->countFiles('tv-shows'));
        self::assertSame(0, $this->countFiles('tv-seasons'));
    }
}
