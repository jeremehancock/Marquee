<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\BroaderQuery;
use PHPUnit\Framework\TestCase;

final class BroaderQueryTest extends TestCase
{
    public function testASubtitleIsCutAtTheColon(): void
    {
        self::assertContains('Jackass', BroaderQuery::candidatesFor('Jackass: Best and Last'));
        self::assertContains('Star Wars', BroaderQuery::candidatesFor('Star Wars: Episode II - Attack of the Clones'));
    }

    /**
     * The first separator, not the last: a subtitle can carry separators of its
     * own, and only the first cut reaches the series.
     */
    public function testTheCutIsAtTheFirstSeparator(): void
    {
        self::assertContains('Rebel Moon', BroaderQuery::candidatesFor('Rebel Moon - Part One: A Child of Fire'));
    }

    public function testATrailingNumberIsDropped(): void
    {
        self::assertContains('Lethal Weapon', BroaderQuery::candidatesFor('Lethal Weapon 2'));
        self::assertContains('Jackass', BroaderQuery::candidatesFor('Jackass 3D'));
        self::assertContains('Jackass', BroaderQuery::candidatesFor('Jackass 2.5'));
    }

    public function testATrailingRomanNumeralIsDropped(): void
    {
        self::assertContains('Rocky', BroaderQuery::candidatesFor('Rocky III'));
        self::assertContains('The Godfather', BroaderQuery::candidatesFor('The Godfather II'));
    }

    /**
     * A pattern for roman numerals also matches ordinary words. Matching a fixed
     * vocabulary is what stops "Mix", "Did" and "Mill" being read as numbers.
     */
    public function testAWordThatMerelyLooksLikeARomanNumeralIsKept(): void
    {
        self::assertSame([], BroaderQuery::candidatesFor('Boogie Mix'));
        self::assertSame([], BroaderQuery::candidatesFor('Steel Mill'));
        self::assertSame([], BroaderQuery::candidatesFor('What She Did'));
    }

    public function testPartAndChapterTakeTheirNumberWithThem(): void
    {
        self::assertContains('John Wick', BroaderQuery::candidatesFor('John Wick Chapter 4'));
        self::assertContains('Harry Potter', BroaderQuery::candidatesFor('Harry Potter Part 2'));
    }

    public function testATitleWithNothingToCutOffersNothing(): void
    {
        self::assertSame([], BroaderQuery::candidatesFor('Solaris'));
        self::assertSame([], BroaderQuery::candidatesFor('The Matrix'));
    }

    /**
     * A cut that leaves almost nothing describes no work at all.
     */
    public function testAnUnusablyShortCutIsNotOffered(): void
    {
        self::assertSame([], BroaderQuery::candidatesFor('It: Chapter Two'));
        self::assertNotContains('', BroaderQuery::candidatesFor(': Something'));
    }

    /**
     * A leading separator would cut to nothing, so it is not a cut at all.
     */
    public function testALeadingSeparatorIsNotACut(): void
    {
        self::assertSame([], BroaderQuery::candidatesFor(': Wow'));
    }

    public function testTheQueryItselfIsNeverOffered(): void
    {
        foreach (['Jackass: Best and Last', 'Rocky III', 'Solaris'] as $query) {
            self::assertNotContains($query, BroaderQuery::candidatesFor($query));
        }
    }

    /**
     * Longest first, so the least aggressive cut leads. The caller keeps the
     * best-scoring candidate, so this only breaks ties.
     */
    public function testCandidatesAreOrderedLongestFirst(): void
    {
        $candidates = BroaderQuery::candidatesFor('Rebel Moon - Part Two: The Scargiver');

        self::assertNotSame([], $candidates);
        $lengths = array_map(mb_strlen(...), $candidates);
        $sorted = $lengths;
        rsort($sorted);
        self::assertSame($sorted, $lengths);
    }

    public function testNumbersAndDigitsInTheMiddleAreLeftAlone(): void
    {
        self::assertSame([], BroaderQuery::candidatesFor('Blade Runner 2049 Redux'));
        self::assertSame([], BroaderQuery::candidatesFor('Class of 2026 Reunion'));
    }
}
