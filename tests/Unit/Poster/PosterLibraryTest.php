<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Config\PosterConfig;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Poster\PosterLibrary;
use App\Poster\Search\PosterSearch;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;

final class PosterLibraryTest extends TestCase
{
    use MakesImages;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
        mkdir($this->dir . '/movies');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    /**
     * @param list<string> $filenames
     */
    private function library(array $filenames, int $perPage = 24, bool $ignoreArticles = true): PosterLibrary
    {
        foreach ($filenames as $name) {
            $this->writePng($this->dir . '/movies/' . $name);
        }

        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $config = new PosterConfig($perPage, 5_000_000, ['jpg', 'jpeg', 'png', 'webp'], $ignoreArticles);

        return new PosterLibrary($storage, new PosterSearch(), $config);
    }

    public function testArticleAwareSort(): void
    {
        $library = $this->library(['The Matrix.png', 'Alien.png', 'Zodiac.png']);

        $titles = array_map(
            static fn ($p): string => $p->title(),
            $library->browse(PosterCategory::Movies, null, 1)->items,
        );

        self::assertSame(['Alien', 'The Matrix', 'Zodiac'], $titles);
    }

    public function testPagination(): void
    {
        $library = $this->library(['A.png', 'B.png', 'C.png', 'D.png', 'E.png'], perPage: 2);

        $page1 = $library->browse(PosterCategory::Movies, null, 1);
        self::assertSame(5, $page1->total);
        self::assertSame(3, $page1->totalPages());
        self::assertCount(2, $page1->items);
        self::assertTrue($page1->hasNext());
        self::assertFalse($page1->hasPrevious());

        self::assertCount(1, $library->browse(PosterCategory::Movies, null, 3)->items);
    }

    public function testOutOfRangePageIsClamped(): void
    {
        $library = $this->library(['A.png', 'B.png', 'C.png'], perPage: 2);

        $page = $library->browse(PosterCategory::Movies, null, 99);

        self::assertSame(2, $page->page);
        self::assertCount(1, $page->items);
    }

    public function testSearchFiltersWithinCategory(): void
    {
        $library = $this->library(['Star Wars.png', 'Star Trek.png']);

        $result = $library->browse(PosterCategory::Movies, 'wars', 1);

        self::assertSame(1, $result->total);
        self::assertSame('Star Wars', $result->items[0]->title());
    }

    public function testDeleteRemovesPoster(): void
    {
        $library = $this->library(['Gone.png']);

        self::assertTrue($library->delete(PosterCategory::Movies, 'Gone.png'));
        self::assertSame(0, $library->browse(PosterCategory::Movies, null, 1)->total);
    }
}
