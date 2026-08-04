<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * The gallery sort order: a field and a direction in one value. The backing
 * value is the slug used in the `?sort=` query parameter, in the session, and
 * in the `DEFAULT_SORT` environment variable.
 *
 * `alphabetical` and `date_added` keep the spelling and the meaning they have
 * always had — A–Z and newest first — and are each field's default direction.
 * The two directions added alongside them are new slugs rather than a renaming,
 * so existing configuration, bookmarks, and live sessions keep resolving
 * without migration.
 */
enum SortOrder: string
{
    case Alphabetical = 'alphabetical';
    case AlphabeticalDesc = 'alphabetical_desc';
    case DateAdded = 'date_added';
    case DateAddedAsc = 'date_added_asc';

    /**
     * What the sort control's button reads. The title button carries its
     * direction in the label because A–Z and Z–A are a natural pair; the
     * date-added button keeps one label and shows direction by its arrow
     * alone, there being no equally short pair of words for it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Alphabetical => 'A–Z',
            self::AlphabeticalDesc => 'Z–A',
            self::DateAdded, self::DateAddedAsc => 'Date added',
        };
    }

    /**
     * Field and direction spelled out in words. An arrow is not announced by
     * assistive technology, so every button needs this as its text alternative.
     */
    public function ariaLabel(): string
    {
        return match ($this) {
            self::Alphabetical => 'Sort by title, A to Z',
            self::AlphabeticalDesc => 'Sort by title, Z to A',
            self::DateAdded => 'Sort by date added, newest first',
            self::DateAddedAsc => 'Sort by date added, oldest first',
        };
    }

    public function field(): SortField
    {
        return match ($this) {
            self::Alphabetical, self::AlphabeticalDesc => SortField::Alphabetical,
            self::DateAdded, self::DateAddedAsc => SortField::DateAdded,
        };
    }

    public function direction(): SortDirection
    {
        return match ($this) {
            self::Alphabetical, self::DateAddedAsc => SortDirection::Ascending,
            self::AlphabeticalDesc, self::DateAdded => SortDirection::Descending,
        };
    }

    /**
     * The same field running the other way — what activating the sort control's
     * already-active button applies.
     */
    public function flipped(): self
    {
        return $this->field()->order($this->direction()->flipped());
    }

    /**
     * Whether this order has been turned around from the way its field normally
     * runs: Z–A rather than A–Z, oldest first rather than newest.
     *
     * This, not the direction, is what the control's arrow reports. Ascending
     * and descending do not mean the same thing to a reader across the two
     * fields — A–Z is ascending and newest-first is descending, yet both are the
     * ordinary way to read that field — so an arrow keyed to the direction would
     * point two different ways at two orders that are equally unremarkable. Keyed
     * to this, both buttons rest pointing down and an arrow that has turned over
     * always means the same thing: this one is reversed.
     */
    public function isReversed(): bool
    {
        return $this->direction() !== $this->field()->defaultDirection();
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
