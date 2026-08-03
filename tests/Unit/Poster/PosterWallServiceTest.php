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
        mkdir($this->dir . '/tv-seasons');
        mkdir($this->dir . '/collections');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function service(): PosterWallService
    {
        return new PosterWallService(new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']));
    }

    /**
     * Three works, plus one season and one collection that the wall must never
     * return. The excluded two are seeded in every test that seeds anything, so
     * a regression to iterating every category fails here rather than passing
     * unnoticed.
     */
    private function seed(): void
    {
        $this->writePng($this->dir . '/movies/Solaris.png');
        $this->writePng($this->dir . '/movies/Dune.png');
        $this->writePng($this->dir . '/tv-shows/Severance.png');
        $this->writePng($this->dir . '/tv-seasons/Severance_-_Season_1.png');
        $this->writePng($this->dir . '/collections/Studio_Ghibli.png');
    }

    /**
     * @return list<string>
     */
    private function paths(int $count): array
    {
        // Match the path only: the URL carries a cache-busting ?v=<mtime>, and
        // pinning a real mtime here would tie the test to the fixture's clock.
        return array_map(
            static fn ($p): string => explode('?', $p->url())[0],
            $this->service()->randomPosters($count),
        );
    }

    public function testReturnsPostersAcrossCategoriesUpToCount(): void
    {
        $this->seed();

        $posters = $this->service()->randomPosters(2);

        self::assertCount(2, $posters);
    }

    public function testReturnsEveryWorkWhenCountExceedsLibrary(): void
    {
        $this->seed();

        $paths = $this->paths(100);

        // Three of the five seeded posters are works; the season and the
        // collection are not part of the pool at all.
        self::assertCount(3, $paths);
        self::assertContains('/posters/movies/Solaris.png', $paths);
        self::assertContains('/posters/movies/Dune.png', $paths);
        self::assertContains('/posters/tv-shows/Severance.png', $paths);
    }

    public function testNeverReturnsASeasonPoster(): void
    {
        $this->seed();

        self::assertNotContains('/posters/tv-seasons/Severance_-_Season_1.png', $this->paths(100));
    }

    public function testNeverReturnsACollectionPoster(): void
    {
        $this->seed();

        self::assertNotContains('/posters/collections/Studio_Ghibli.png', $this->paths(100));
    }

    public function testReturnsBothMovieAndShowPosters(): void
    {
        $this->seed();

        $categories = array_unique(array_map(
            static fn (string $path): string => explode('/', $path)[2],
            $this->paths(100),
        ));

        self::assertEqualsCanonicalizing(['movies', 'tv-shows'], array_values($categories));
    }

    /**
     * A library of nothing but seasons and collections leaves the wall with no
     * posters to show — the empty state, reached with a full posters directory.
     */
    public function testLibraryOfOnlySeasonsAndCollectionsReturnsNone(): void
    {
        $this->writePng($this->dir . '/tv-seasons/Severance_-_Season_1.png');
        $this->writePng($this->dir . '/collections/Studio_Ghibli.png');

        self::assertSame([], $this->service()->randomPosters(10));
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
