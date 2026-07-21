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
