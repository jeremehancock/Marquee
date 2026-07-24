<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\SortOrder;
use PHPUnit\Framework\TestCase;

final class SortOrderTest extends TestCase
{
    public function testFromSlugResolvesKnownValues(): void
    {
        self::assertSame(SortOrder::Alphabetical, SortOrder::fromSlug('alphabetical'));
        self::assertSame(SortOrder::DateAdded, SortOrder::fromSlug('date_added'));
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
