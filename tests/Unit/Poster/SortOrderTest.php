<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\SortDirection;
use App\Poster\SortField;
use App\Poster\SortOrder;
use PHPUnit\Framework\TestCase;

final class SortOrderTest extends TestCase
{
    public function testFromSlugResolvesKnownValues(): void
    {
        self::assertSame(SortOrder::Alphabetical, SortOrder::fromSlug('alphabetical'));
        self::assertSame(SortOrder::DateAdded, SortOrder::fromSlug('date_added'));
    }

    /**
     * The two directions added alongside the originals are new slugs, not a
     * renaming — which is what lets an existing session or bookmark holding a
     * bare `alphabetical` keep resolving.
     */
    public function testFromSlugResolvesBothDirections(): void
    {
        self::assertSame(SortOrder::AlphabeticalDesc, SortOrder::fromSlug('alphabetical_desc'));
        self::assertSame(SortOrder::DateAddedAsc, SortOrder::fromSlug('date_added_asc'));
    }

    public function testOriginalSlugsAreEachFieldsDefaultDirection(): void
    {
        self::assertSame(SortOrder::Alphabetical, SortField::Alphabetical->defaultOrder());
        self::assertSame(SortOrder::DateAdded, SortField::DateAdded->defaultOrder());
    }

    public function testFieldAndDirection(): void
    {
        self::assertSame(SortField::Alphabetical, SortOrder::AlphabeticalDesc->field());
        self::assertSame(SortDirection::Descending, SortOrder::AlphabeticalDesc->direction());
        self::assertSame(SortField::DateAdded, SortOrder::DateAddedAsc->field());
        self::assertSame(SortDirection::Ascending, SortOrder::DateAddedAsc->direction());
    }

    public function testFlippedKeepsTheFieldAndReversesTheDirection(): void
    {
        self::assertSame(SortOrder::AlphabeticalDesc, SortOrder::Alphabetical->flipped());
        self::assertSame(SortOrder::Alphabetical, SortOrder::AlphabeticalDesc->flipped());
        self::assertSame(SortOrder::DateAddedAsc, SortOrder::DateAdded->flipped());
        self::assertSame(SortOrder::DateAdded, SortOrder::DateAddedAsc->flipped());
        self::assertSame(SortOrder::ReleaseAsc, SortOrder::Release->flipped());
        self::assertSame(SortOrder::Release, SortOrder::ReleaseAsc->flipped());
    }

    public function testFlippingTwiceReturnsTheSameOrder(): void
    {
        foreach (SortOrder::cases() as $order) {
            self::assertSame($order, $order->flipped()->flipped());
        }
    }

    /**
     * The title button carries its direction in the label; the date and release
     * buttons have no equally short pair of words for it and show direction by
     * arrow alone.
     */
    public function testLabels(): void
    {
        self::assertSame('A–Z', SortOrder::Alphabetical->label());
        self::assertSame('Z–A', SortOrder::AlphabeticalDesc->label());
        self::assertSame('Date added', SortOrder::DateAdded->label());
        self::assertSame('Date added', SortOrder::DateAddedAsc->label());
        self::assertSame('Release', SortOrder::Release->label());
        self::assertSame('Release', SortOrder::ReleaseAsc->label());
    }

    /**
     * The two date-ish fields answer different questions — when a poster's media
     * arrived in Plex, and when the work itself came out — and a library can
     * easily have added its oldest film most recently. Sharing "oldest first"
     * between them would present those as the same question asked twice.
     */
    public function testReleaseAndDateAddedDoNotShareDirectionWords(): void
    {
        $dates = [SortOrder::DateAdded->directionPhrase(), SortOrder::DateAddedAsc->directionPhrase()];
        $releases = [SortOrder::Release->directionPhrase(), SortOrder::ReleaseAsc->directionPhrase()];

        self::assertSame([], array_intersect($dates, $releases));
    }

    /**
     * An arrow is not announced, so every order needs its direction in words.
     */
    public function testEveryOrderHasADistinctAriaLabel(): void
    {
        $labels = array_map(static fn (SortOrder $o): string => $o->actionLabel(), SortOrder::cases());

        self::assertCount(count(SortOrder::cases()), array_unique($labels));
        self::assertSame('Sort by date added, oldest first', SortOrder::DateAddedAsc->actionLabel());
    }

    /**
     * What the control's arrow reports. Not the direction: A–Z is ascending and
     * newest first is descending, yet neither is reversed, so both rest pointing
     * the same way.
     */
    public function testIsReversedTracksTheFieldsDefaultRatherThanTheDirection(): void
    {
        self::assertFalse(SortOrder::Alphabetical->isReversed(), 'A–Z is how titles normally run.');
        self::assertFalse(SortOrder::DateAdded->isReversed(), 'Newest first is how dates normally run.');
        self::assertFalse(SortOrder::Release->isReversed(), 'Latest first is how a release order normally runs.');
        self::assertTrue(SortOrder::AlphabeticalDesc->isReversed());
        self::assertTrue(SortOrder::DateAddedAsc->isReversed());
        self::assertTrue(SortOrder::ReleaseAsc->isReversed());
    }

    public function testFlippingAlwaysChangesWhetherAnOrderIsReversed(): void
    {
        foreach (SortOrder::cases() as $order) {
            self::assertNotSame($order->isReversed(), $order->flipped()->isReversed());
        }
    }

    public function testEveryFieldHasAGlyph(): void
    {
        self::assertSame('sort-title', SortField::Alphabetical->glyph());
        self::assertSame('sort-date', SortField::DateAdded->glyph());
        self::assertSame('sort-release', SortField::Release->glyph());
    }

    /**
     * A field naming the same mark as another would make the control's buttons
     * tell a reader they sort the same way.
     */
    public function testNoTwoFieldsShareAGlyph(): void
    {
        $glyphs = array_map(static fn (SortField $f): string => $f->glyph(), SortField::all());

        self::assertCount(count($glyphs), array_unique($glyphs));
    }

    public function testOtherFieldsAreEveryFieldButTheOneGiven(): void
    {
        self::assertSame(
            [SortField::DateAdded, SortField::Release],
            SortField::Alphabetical->others(),
        );
        self::assertSame(
            [SortField::Alphabetical, SortField::Release],
            SortField::DateAdded->others(),
        );
        self::assertSame(
            [SortField::Alphabetical, SortField::DateAdded],
            SortField::Release->others(),
        );
    }

    /**
     * Every order has a field and every field has both directions, so the two
     * enums cannot drift apart: a case added to one without the other is what
     * would leave the control with a button that cannot be reversed.
     */
    public function testEveryFieldAndDirectionPairsWithAnOrder(): void
    {
        $reachable = [];
        foreach (SortField::all() as $field) {
            $reachable[] = $field->order(SortDirection::Ascending);
            $reachable[] = $field->order(SortDirection::Descending);
        }

        self::assertEqualsCanonicalizing(SortOrder::cases(), $reachable);
    }

    public function testFromSlugAcceptsAlphaShorthandAndIsCaseInsensitive(): void
    {
        self::assertSame(SortOrder::Alphabetical, SortOrder::fromSlug('alpha'));
        self::assertSame(SortOrder::DateAdded, SortOrder::fromSlug('  DATE_ADDED '));
    }

    public function testFromSlugReturnsNullForUnknownValue(): void
    {
        self::assertNull(SortOrder::fromSlug('newest'));
        self::assertNull(SortOrder::fromSlug(''));
    }

    public function testDefaultIsAlphabetical(): void
    {
        self::assertSame(SortOrder::Alphabetical, SortOrder::default());
    }

    /**
     * The two date-shaped fields must rest pointing the same way AND mean the
     * same thing by it.
     *
     * This shipped wrong. Release defaulted to earliest-first while Date added
     * defaults to newest-first, so both buttons sat in the toolbar showing an
     * identical down arrow while ordering time in opposite directions — which is
     * precisely what keying the arrow to "reversed" rather than to
     * ascending/descending is supposed to prevent. The convention only holds if
     * fields answering the same kind of question agree about their ordinary
     * direction.
     *
     * Titles are excluded deliberately: A–Z is not a claim about time, and
     * nobody reads a down arrow on it as "newest".
     */
    public function testBothTimeFieldsRunTheSameWayByDefault(): void
    {
        self::assertSame(
            SortField::DateAdded->defaultDirection(),
            SortField::Release->defaultDirection(),
            'a down arrow must mean the same thing on both date-shaped buttons',
        );
    }

    /**
     * And the direction itself, stated so a later "tidy-up" cannot quietly flip
     * both together and still pass the test above.
     */
    public function testTheTimeFieldsLeadWithTheMostRecent(): void
    {
        self::assertSame(SortDirection::Descending, SortField::DateAdded->defaultDirection());
        self::assertSame(SortDirection::Descending, SortField::Release->defaultDirection());
        self::assertSame('latest first', SortField::Release->defaultOrder()->directionPhrase());
    }

    /**
     * The bare slug names each field's default direction, and the suffixed one
     * its reverse — the pattern date_added/date_added_asc already set. Getting
     * this backwards would make DEFAULT_SORT=release mean the opposite of what
     * the settings screen shows for it.
     */
    public function testTheBareSlugIsAlwaysTheFieldsDefaultDirection(): void
    {
        foreach (SortField::all() as $field) {
            self::assertSame(
                $field->value,
                $field->defaultOrder()->value,
                $field->value . ': the unsuffixed slug must be the default direction',
            );
        }
    }
}
