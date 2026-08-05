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

    /**
     * A term counts wherever it appears. Ranking a title that begins with the
     * query above one that merely contains it is what used to strand a poster
     * below the sort the user had actually asked for.
     */
    public function testWhereATermMatchesCarriesNoWeight(): void
    {
        $posters = $this->posters(['The Dark Knight.jpg', 'Knight Rider.jpg']);

        $result = $this->titles($this->search->filter($posters, 'knight'));

        self::assertCount(2, $result);
        self::assertContains('Knight Rider', $result);
        self::assertContains('The Dark Knight', $result);
    }

    /**
     * Filtering leaves the incoming order alone: whatever the caller hands over
     * comes back in the same sequence, minus what did not match. The gallery
     * sorts afterwards, so a reordering here would be silently overwritten in
     * production and only ever mislead a reader.
     */
    public function testMatchesComeBackInTheOrderTheyWereGiven(): void
    {
        $posters = $this->posters([
            'Alien Covenant.jpg',
            'Dune.jpg',
            'Aliens.jpg',
            'Alien.jpg',
        ]);

        $result = $this->titles($this->search->filter($posters, 'alien'));

        self::assertSame(['Alien Covenant', 'Aliens', 'Alien'], $result);
    }

    public function testEmptyQueryReturnsEverythingUntouched(): void
    {
        $posters = $this->posters(['Zodiac.jpg', 'Alien.jpg']);

        self::assertSame(['Zodiac', 'Alien'], $this->titles($this->search->filter($posters, '   ')));
    }

    /**
     * Punctuation and separators are flattened on both sides, so a query need not
     * reproduce the filename's own punctuation to match it.
     */
    public function testPunctuationIsFlattenedOnBothSides(): void
    {
        $posters = $this->posters(['Spider-Man - No Way Home.jpg', 'Dune.jpg']);

        $result = $this->titles($this->search->filter($posters, 'spider man'));

        self::assertSame(['Spider-Man - No Way Home'], $result);
    }
}
