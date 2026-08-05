<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * Which way a sort field runs. The backing value is what the session stores as
 * a field's remembered direction.
 */
enum SortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';

    public function flipped(): self
    {
        return match ($this) {
            self::Ascending => self::Descending,
            self::Descending => self::Ascending,
        };
    }

    /**
     * Resolve a stored value to a direction, or null when it is unrecognized so
     * the caller can fall back to a field's default.
     */
    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom(strtolower(trim($slug)));
    }
}
