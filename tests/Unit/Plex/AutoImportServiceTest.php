<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Config\AutoImportConfig;
use App\Database\Database;
use App\Database\PlexItemRepository;
use App\Database\PlexLibraryRepository;
use App\Plex\Import\AutoImportService;
use App\Plex\Import\ImportService;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Poster\FilesystemPosterStorage;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AutoImportServiceTest extends TestCase
{
    use MakesImages;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function service(FakePlexClient $plex, AutoImportConfig $config): AutoImportService
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $import = new ImportService(
            $plex,
            $storage,
            new PlexItemRepository($database),
            new PlexLibraryRepository($database),
        );

        return new AutoImportService($plex, $import, $config, new NullLogger());
    }

    private function countFiles(string $sub): int
    {
        $dir = $this->dir . '/' . $sub;
        if (!is_dir($dir)) {
            return 0;
        }

        return count(array_filter(scandir($dir) ?: [], static fn (string $f): bool => is_file($dir . '/' . $f)));
    }

    /**
     * @param list<string> $excluded
     */
    private function config(bool $enabled, bool $movies, bool $shows, bool $seasons, array $excluded = []): AutoImportConfig
    {
        return new AutoImportConfig($enabled, $movies, $shows, $seasons, false, $excluded);
    }

    public function testImportsOnlyEnabledMediaTypes(): void
    {
        $movieLib = new PlexLibrary('1', 'Movies', 'movie');
        $showLib = new PlexLibrary('2', 'TV', 'show');
        $plex = new FakePlexClient(
            [$movieLib, $showLib],
            [
                '1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')],
                '2' => [new PlexItem('20', PlexMediaType::Show, 'Severance', null, '/t/20', 'TV')],
            ],
            ['20' => [new PlexItem('200', PlexMediaType::Season, 'Season 1', null, '/t/200', 'TV', 'Severance')]],
        );

        $result = $this->service($plex, $this->config(true, true, true, false))->run();

        self::assertNotNull($result);
        self::assertSame(2, $result->imported());
        self::assertSame(1, $this->countFiles('movies'));
        self::assertSame(1, $this->countFiles('tv-shows'));
        self::assertSame(0, $this->countFiles('tv-seasons'));
    }

    public function testExcludedLibraryIsSkipped(): void
    {
        $movies = new PlexLibrary('1', 'Movies', 'movie');
        $kids = new PlexLibrary('3', 'Kids', 'movie');
        $plex = new FakePlexClient(
            [$movies, $kids],
            [
                '1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')],
                '3' => [new PlexItem('30', PlexMediaType::Movie, 'Cars', 2006, '/t/30', 'Kids')],
            ],
        );

        $result = $this->service($plex, $this->config(true, true, false, false, ['Kids']))->run();

        self::assertNotNull($result);
        self::assertSame(1, $result->imported());
        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testDisabledDoesNothing(): void
    {
        $plex = new FakePlexClient([new PlexLibrary('1', 'Movies', 'movie')]);

        self::assertNull($this->service($plex, $this->config(false, true, false, false))->run());
        self::assertSame(0, $this->countFiles('movies'));
    }

    public function testNothingSelectedDoesNothing(): void
    {
        $plex = new FakePlexClient([new PlexLibrary('1', 'Movies', 'movie')]);

        self::assertNull($this->service($plex, $this->config(true, false, false, false))->run());
    }

    public function testUnconfiguredDoesNothing(): void
    {
        $plex = new FakePlexClient([new PlexLibrary('1', 'Movies', 'movie')], configured: false);

        self::assertNull($this->service($plex, $this->config(true, true, false, false))->run());
    }
}
