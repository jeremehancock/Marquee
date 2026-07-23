<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\FilesystemPosterStorage;
use App\Poster\Wall\PosterWallService;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;

final class PosterWallServiceTest extends TestCase
{
    use MakesImages;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
        mkdir($this->dir . '/movies');
        mkdir($this->dir . '/tv-shows');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function service(): PosterWallService
    {
        return new PosterWallService(new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']));
    }

    private function seed(): void
    {
        $this->writePng($this->dir . '/movies/Solaris.png');
        $this->writePng($this->dir . '/movies/Dune.png');
        $this->writePng($this->dir . '/tv-shows/Severance.png');
    }

    public function testReturnsPostersAcrossCategoriesUpToCount(): void
    {
        $this->seed();

        $posters = $this->service()->randomPosters(2);

        self::assertCount(2, $posters);
    }

    public function testReturnsAllWhenCountExceedsLibrary(): void
    {
        $this->seed();

        $urls = array_map(static fn ($p): string => $p->url(), $this->service()->randomPosters(100));

        self::assertCount(3, $urls);
        // Match the path only: the URL carries a cache-busting ?v=<mtime>, and
        // pinning a real mtime here would tie the test to the fixture's clock.
        $paths = array_map(static fn (string $url): string => explode('?', $url)[0], $urls);
        self::assertContains('/posters/tv-shows/Severance.png', $paths);
    }

    public function testEmptyLibraryReturnsNone(): void
    {
        self::assertSame([], $this->service()->randomPosters(10));
    }

    public function testZeroCountReturnsNone(): void
    {
        $this->seed();

        self::assertSame([], $this->service()->randomPosters(0));
    }
}
