<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * One page of gallery results.
 */
final class Page
{
    /**
     * @param list<Poster> $items
     * @param string|null  $broaderQuery a shorter query that would find more than
     *        this search did, or null when there is none worth offering. The
     *        gallery offers it as a link; nothing here applies it.
     * @param int          $broaderTotal how many posters that query would find,
     *        shown with the offer so a candidate that is far too broad says so
     *        before anyone follows it
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
        public readonly ?string $broaderQuery = null,
        public readonly int $broaderTotal = 0,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages();
    }

    /**
     * 1-based index of the first item on this page (0 when empty).
     */
    public function firstItemNumber(): int
    {
        return $this->total === 0 ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    /**
     * 1-based index of the last item on this page.
     */
    public function lastItemNumber(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    /**
     * The page numbers to render in a windowed pagination control.
     *
     * Always includes the first and last `edge` pages plus `around` pages
     * either side of the current page. Gaps of two or more omitted pages
     * collapse into a single ellipsis (represented by `null`); a gap of
     * exactly one page renders that page's number rather than an ellipsis, so
     * "1 … 3" never appears where "1 2 3" fits.
     *
     * @return list<int|null> ordered tokens: page numbers and ellipsis (null)
     */
    public function paginationWindow(int $edge = 1, int $around = 1): array
    {
        $totalPages = $this->totalPages();

        $shown = [];
        for ($p = 1; $p <= $edge && $p <= $totalPages; $p++) {
            $shown[$p] = true;
        }
        for ($p = $totalPages - $edge + 1; $p <= $totalPages; $p++) {
            if ($p >= 1) {
                $shown[$p] = true;
            }
        }
        for ($p = $this->page - $around; $p <= $this->page + $around; $p++) {
            if ($p >= 1 && $p <= $totalPages) {
                $shown[$p] = true;
            }
        }

        $pages = array_keys($shown);
        sort($pages);

        $window = [];
        $previous = 0;
        foreach ($pages as $page) {
            $gap = $page - $previous;
            if ($previous !== 0 && $gap > 1) {
                if ($gap === 2) {
                    $window[] = $previous + 1;
                } else {
                    $window[] = null;
                }
            }
            $window[] = $page;
            $previous = $page;
        }

        return $window;
    }
}
