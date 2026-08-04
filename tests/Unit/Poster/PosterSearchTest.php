<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Config\PosterConfig;
use App\Poster\Poster;
use App\Poster\PosterCategory;
use App\Poster\Search\PosterSearch;
use App\Poster\SortComparator;
use App\Poster\SortOrder;
use PHPUnit\Framework\TestCase;

final class PosterSearchTest extends TestCase
{
    private PosterSearch $search;

    protected function setUp(): void
    {
        $config = new PosterConfig(24, 5_000_000, ['jpg', 'jpeg', 'png', 'webp'], true, SortOrder::Alphabetical);
        $this->search = new PosterSearch(new SortComparator($config));
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

    /**
     * Every season matches at the same position, so the tiebreak decides the
     * whole order. Without a digit-aware key this lists 1, 10, 11, 2 — the
     * defect the gallery's own ordering no longer has.
     */
    public function testEquallyRelevantResultsOrderNumbersByValue(): void
    {
        $posters = $this->posters([
            'Breaking Bad - Season 11.jpg',
            'Breaking Bad - Season 2.jpg',
            'Breaking Bad - Season 10.jpg',
            'Breaking Bad - Season 1.jpg',
        ]);

        $result = $this->titles($this->search->filter($posters, 'breaking bad'));

        self::assertSame([
            'Breaking Bad - Season 1',
            'Breaking Bad - Season 2',
            'Breaking Bad - Season 10',
            'Breaking Bad - Season 11',
        ], $result);
    }

    /**
     * Relevance still leads: the digit-aware key only separates results that
     * match equally early, it never overrides where the query matched.
     */
    public function testMatchPositionStillOutranksTheNumber(): void
    {
        // "Episode 2" matches "episode" at position 0; "Season 1 Episode 9"
        // matches it late. The later match sorts last despite the lower number.
        $posters = $this->posters(['Season 1 Episode 9.jpg', 'Episode 2.jpg']);

        $result = $this->titles($this->search->filter($posters, 'episode'));

        self::assertSame(['Episode 2', 'Season 1 Episode 9'], $result);
    }

    /**
     * The sort control keeps working while a search is active: it decides the
     * order of results that are equally relevant to each other.
     */
    public function testDescendingSortReversesEquallyRelevantResults(): void
    {
        $posters = $this->posters(['Alien.jpg', 'Alien Covenant.jpg', 'Aliens.jpg']);

        $result = $this->titles($this->search->filter($posters, 'alien', SortOrder::AlphabeticalDesc));

        self::assertSame(['Aliens', 'Alien Covenant', 'Alien'], $result);
    }

    /**
     * Reversing the order must not promote a weaker match. "Alien" matches at
     * position 0 and "The Alien" late, so "Alien" leads under Z–A too — the
     * opposite of what a plain reversal of the whole list would produce.
     */
    public function testMatchPositionStillLeadsUnderADescendingSort(): void
    {
        $posters = $this->posters(['The Alien.jpg', 'Alien.jpg']);

        $result = $this->titles($this->search->filter($posters, 'alien', SortOrder::AlphabeticalDesc));

        self::assertSame(['Alien', 'The Alien'], $result);
    }

    public function testDateOrderBreaksTiesWhileSearching(): void
    {
        $posters = [
            new Poster(PosterCategory::Movies, 'Alien.jpg', 100, 10),
            new Poster(PosterCategory::Movies, 'Aliens.jpg', 100, 30),
            new Poster(PosterCategory::Movies, 'Alien Covenant.jpg', 100, 20),
        ];
        $addedAt = ['movies' => ['Alien.jpg' => 10, 'Aliens.jpg' => 30, 'Alien Covenant.jpg' => 20]];

        $newest = $this->titles($this->search->filter($posters, 'alien', SortOrder::DateAdded, $addedAt));
        $oldest = $this->titles($this->search->filter($posters, 'alien', SortOrder::DateAddedAsc, $addedAt));

        self::assertSame(['Aliens', 'Alien Covenant', 'Alien'], $newest);
        self::assertSame(['Alien', 'Alien Covenant', 'Aliens'], $oldest);
    }

    /**
     * The default keeps the behaviour every existing caller relied on before the
     * tie-break became configurable.
     */
    public function testDefaultsToAscendingTitleTieBreak(): void
    {
        $posters = $this->posters(['Aliens.jpg', 'Alien.jpg', 'Alien Covenant.jpg']);

        $result = $this->titles($this->search->filter($posters, 'alien'));

        self::assertSame(['Alien', 'Alien Covenant', 'Aliens'], $result);
    }
}
