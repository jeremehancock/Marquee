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
    }

    public function testFlippingTwiceReturnsTheSameOrder(): void
    {
        foreach (SortOrder::cases() as $order) {
            self::assertSame($order, $order->flipped()->flipped());
        }
    }

    /**
     * The title button carries its direction in the label; the date button has
     * no equally short pair of words for it and shows direction by arrow alone.
     */
    public function testLabels(): void
    {
        self::assertSame('A–Z', SortOrder::Alphabetical->label());
        self::assertSame('Z–A', SortOrder::AlphabeticalDesc->label());
        self::assertSame('Date added', SortOrder::DateAdded->label());
        self::assertSame('Date added', SortOrder::DateAddedAsc->label());
    }

    /**
     * An arrow is not announced, so every order needs its direction in words.
     */
    public function testEveryOrderHasADistinctAriaLabel(): void
    {
        $labels = array_map(static fn (SortOrder $o): string => $o->ariaLabel(), SortOrder::cases());

        self::assertCount(4, array_unique($labels));
        self::assertSame('Sort by date added, oldest first', SortOrder::DateAddedAsc->ariaLabel());
    }

    public function testEveryFieldHasAGlyph(): void
    {
        self::assertSame('sort-title', SortField::Alphabetical->glyph());
        self::assertSame('sort-date', SortField::DateAdded->glyph());
    }

    public function testOtherFieldIsTheOneNotGiven(): void
    {
        self::assertSame(SortField::DateAdded, SortField::Alphabetical->other());
        self::assertSame(SortField::Alphabetical, SortField::DateAdded->other());
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
}
