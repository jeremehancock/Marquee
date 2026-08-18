<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\PosterConfig;
use App\Poster\SortDirection;
use App\Poster\SortField;
use App\Poster\SortOrder;
use App\Tests\Support\SeedsSettings;
use PHPUnit\Framework\TestCase;

final class PosterConfigTest extends TestCase
{
    use SeedsSettings;

    protected function tearDown(): void
    {
        putenv('DEFAULT_SORT');
    }

    public function testDefaultSortIsAlphabeticalWhenUnset(): void
    {
        putenv('DEFAULT_SORT');

        self::assertSame(SortOrder::Alphabetical, PosterConfig::resolve($this->seededStore())->defaultSort);
    }

    public function testDefaultSortReadsDateAdded(): void
    {
        putenv('DEFAULT_SORT=date_added');

        self::assertSame(SortOrder::DateAdded, PosterConfig::resolve($this->seededStore())->defaultSort);
    }

    public function testUnrecognizedDefaultSortFallsBackToAlphabetical(): void
    {
        putenv('DEFAULT_SORT=whatever');

        self::assertSame(SortOrder::Alphabetical, PosterConfig::resolve($this->seededStore())->defaultSort);
    }

    /**
     * The documented values are the two this variable has always accepted, and
     * each still selects its field's default direction. Directions reach the
     * gallery through the sort control, not through configuration, so an install
     * default cannot silently become a reversed one.
     */
    public function testDocumentedValuesSelectEachFieldsDefaultDirection(): void
    {
        putenv('DEFAULT_SORT=alphabetical');
        $alphabetical = PosterConfig::resolve($this->seededStore())->defaultSort;

        putenv('DEFAULT_SORT=date_added');
        $dateAdded = PosterConfig::resolve($this->seededStore())->defaultSort;

        self::assertSame(SortDirection::Ascending, $alphabetical->direction());
        self::assertSame(SortField::Alphabetical, $alphabetical->field());
        self::assertSame(SortDirection::Descending, $dateAdded->direction());
        self::assertSame(SortField::DateAdded, $dateAdded->field());
    }
}
