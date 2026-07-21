<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Poster;
use App\Poster\PosterCategory;
use App\Poster\Search\PosterSearch;
use PHPUnit\Framework\TestCase;

final class PosterSearchTest extends TestCase
{
    private PosterSearch $search;

    protected function setUp(): void
    {
        $this->search = new PosterSearch();
    }

    /**
     * @param list<string> $filenames
     *
     * @return list<Poster>
     */
    private function posters(array $filenames): array
    {
        return array_map(
            static fn (string $name): Poster => new Poster(PosterCategory::Movies, $name, 100, 0),
            $filenames,
        );
    }

    /**
     * @param list<Poster> $result
     *
     * @return list<string>
     */
    private function titles(array $result): array
    {
        return array_map(static fn (Poster $p): string => $p->title(), $result);
    }

    public function testAllTermsMustMatch(): void
    {
        $posters = $this->posters(['Star Wars.jpg', 'Star Trek.jpg', 'Wars of Old.jpg']);

        $result = $this->titles($this->search->filter($posters, 'star wars'));

        self::assertSame(['Star Wars'], $result);
    }

    public function testAccentAndCaseInsensitive(): void
    {
        $posters = $this->posters(['Amélie.png', 'Other.png']);

        $result = $this->titles($this->search->filter($posters, 'amelie'));

        self::assertSame(['Amélie'], $result);
    }

    public function testNoMatchReturnsEmpty(): void
    {
        $posters = $this->posters(['Dune.jpg', 'Alien.jpg']);

        self::assertSame([], $this->search->filter($posters, 'matrix'));
    }

    public function testRanksEarlierMatchesFirst(): void
    {
        $posters = $this->posters(['The Dark Knight.jpg', 'Knight Rider.jpg']);

        $result = $this->titles($this->search->filter($posters, 'knight'));

        self::assertSame(['Knight Rider', 'The Dark Knight'], $result);
    }
}
