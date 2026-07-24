<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\PosterConfig;
use App\Poster\SortOrder;
use PHPUnit\Framework\TestCase;

final class PosterConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('DEFAULT_SORT');
    }

    public function testDefaultSortIsAlphabeticalWhenUnset(): void
    {
        putenv('DEFAULT_SORT');

        self::assertSame(SortOrder::Alphabetical, PosterConfig::fromEnv()->defaultSort);
    }

    public function testDefaultSortReadsDateAdded(): void
    {
        putenv('DEFAULT_SORT=date_added');

        self::assertSame(SortOrder::DateAdded, PosterConfig::fromEnv()->defaultSort);
    }

    public function testUnrecognizedDefaultSortFallsBackToAlphabetical(): void
    {
        putenv('DEFAULT_SORT=whatever');

        self::assertSame(SortOrder::Alphabetical, PosterConfig::fromEnv()->defaultSort);
    }
}
