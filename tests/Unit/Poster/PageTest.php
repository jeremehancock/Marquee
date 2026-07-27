<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Page;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    /**
     * @param list<int|null> $expected
     */
    #[DataProvider('windowCases')]
    public function testPaginationWindow(int $page, int $total, int $perPage, array $expected): void
    {
        $window = (new Page([], $page, $perPage, $total))->paginationWindow();

        self::assertSame($expected, $window);
    }

    /**
     * @return iterable<string, array{int, int, int, list<int|null>}>
     */
    public static function windowCases(): iterable
    {
        // Single page: just the one number, no first/last duplication.
        yield 'single page' => [1, 5, 10, [1]];

        // Small totals with no ellipsis — every page fits.
        yield 'three pages from first' => [1, 30, 10, [1, 2, 3]];
        yield 'five pages from middle' => [3, 50, 10, [1, 2, 3, 4, 5]];

        // Large total, current page near the start: 1 2 3 … 82.
        yield 'large total at start' => [2, 820, 10, [1, 2, 3, null, 82]];

        // Large total, current page near the end.
        yield 'large total at end' => [81, 820, 10, [1, null, 80, 81, 82]];

        // Large total, current page in the middle — ellipsis on both sides.
        yield 'large total in middle' => [40, 820, 10, [1, null, 39, 40, 41, null, 82]];

        // Gap of exactly one page shows the number rather than an ellipsis.
        yield 'one-page gap fills the number' => [1, 40, 10, [1, 2, 3, 4]];
        yield 'one-page gap at boundary' => [3, 50, 10, [1, 2, 3, 4, 5]];
    }

    public function testCurrentPageIsAlwaysPresent(): void
    {
        $window = (new Page([], 40, 10, 820))->paginationWindow();

        self::assertContains(40, $window);
    }
}
