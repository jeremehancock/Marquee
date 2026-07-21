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
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
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
}
