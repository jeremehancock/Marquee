<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\RelatedTitle;
use PHPUnit\Framework\TestCase;

final class RelatedTitleTest extends TestCase
{
    public function testARecordedShowTitleWins(): void
    {
        self::assertSame(
            'Severance',
            RelatedTitle::forRecord('Severance - Season 1', 'Severance', 1),
        );
    }

    /**
     * The recorded show title is preferred even where the strip would have found
     * the same answer, because it is the exact one and the strip is a guess about
     * a shape.
     */
    public function testARecordedShowTitleIsUsedEvenWhenItDiffersFromTheDisplayTitle(): void
    {
        self::assertSame(
            'Cowboy Bebop - Remastered',
            RelatedTitle::forRecord('Cowboy Bebop - Remastered - Season 1', 'Cowboy Bebop - Remastered', 1),
        );
    }

    public function testAnItemThatIsNotASeasonAnswersWithItsOwnTitle(): void
    {
        self::assertSame('The Matrix', RelatedTitle::forRecord('The Matrix', '', null));
        self::assertSame('Severance', RelatedTitle::forRecord('Severance', '', null));
    }

    /**
     * The install this is delivered to has no recorded show titles until its next
     * import. Without this the action searches a season's own full title and finds
     * only that season, which is how the feature looks broken on first use.
     */
    public function testASeasonWithNoRecordedShowTitleHasItsSeasonStripped(): void
    {
        self::assertSame('Severance', RelatedTitle::forRecord('Severance - Season 1', '', 1));
        self::assertSame('Severance', RelatedTitle::forRecord('Severance - Season 12', '', 12));
    }

    /**
     * Zero is Specials, which Plex names rather than numbers — and it is a real
     * season number, so it cannot double as "not a season".
     */
    public function testSpecialsIsStripped(): void
    {
        self::assertSame('Severance', RelatedTitle::forRecord('Severance - Specials', '', 0));
        self::assertSame('Severance', RelatedTitle::forRecord('Severance - Season 0', '', 0));
    }

    /**
     * The suffix removed is the one the recorded season number predicts, so a
     * show whose own name ends in something similar is not damaged.
     */
    public function testOnlyTheRecordedSeasonNumbersSuffixIsStripped(): void
    {
        // The row says season 2; the title ends in "Season 1". Leave it alone.
        self::assertSame(
            'Severance - Season 1',
            RelatedTitle::forRecord('Severance - Season 1', '', 2),
        );
    }

    /**
     * A custom season name is not a shape this recognises, so the title stands.
     * Narrow rather than wrong — and `parent_title` corrects it on the next
     * import, which is why the strip may stay this conservative.
     */
    public function testACustomSeasonNameIsLeftAlone(): void
    {
        self::assertSame(
            'The Office - Part 2 - Finale',
            RelatedTitle::forRecord('The Office - Part 2 - Finale', '', 2),
        );
    }

    /**
     * Never a blind split on " - ": a show whose own name contains one would lose
     * half of itself, and the result would look plausible.
     */
    public function testAShowWhoseNameContainsTheSeparatorSurvives(): void
    {
        self::assertSame(
            'Cowboy Bebop - Remastered',
            RelatedTitle::forRecord('Cowboy Bebop - Remastered - Season 1', '', 1),
        );
    }

    /**
     * Stripping must never produce an empty query — that would match everything.
     */
    public function testStrippingNeverEmptiesTheTitle(): void
    {
        self::assertSame(' - Season 1', RelatedTitle::forRecord(' - Season 1', '', 1));
    }
}
