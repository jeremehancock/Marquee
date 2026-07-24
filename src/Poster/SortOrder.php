<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * The gallery sort order. The backing value is the slug used in the `?sort=`
 * query parameter, in the session, and in the `DEFAULT_SORT` environment
 * variable.
 */
enum SortOrder: string
{
    case Alphabetical = 'alphabetical';
    case DateAdded = 'date_added';

    public function label(): string
    {
        return match ($this) {
            self::Alphabetical => 'A–Z',
            self::DateAdded => 'Date added',
        };
    }

    /**
     * Resolve a slug to a sort order, or null when it is unrecognized. Accepts
     * the `alpha` shorthand alongside the full `alphabetical` value.
     */
    public static function fromSlug(string $slug): ?self
    {
        $slug = strtolower(trim($slug));
        if ($slug === 'alpha') {
            return self::Alphabetical;
        }

        return self::tryFrom($slug);
    }

    public static function default(): self
    {
        return self::Alphabetical;
    }
}
