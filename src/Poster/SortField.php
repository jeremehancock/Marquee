<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * What the gallery orders on, independent of which way it runs. The backing
 * value names the field in the session key that remembers its direction.
 */
enum SortField: string
{
    case Alphabetical = 'alphabetical';
    case DateAdded = 'date_added';
    case Release = 'release';

    /**
     * Every field, in the order the sort control draws them. Fixed, so the
     * control does not reshuffle itself as the user sorts.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::Alphabetical, self::DateAdded, self::Release];
    }

    /**
     * The direction this field runs in until the user chooses otherwise.
     * Titles read forwards; both date-shaped fields lead with the most recent.
     *
     * Release leads with the LATEST deliberately, matching Date added. The two
     * sit side by side and both answer a question about time, so a down arrow —
     * which means "this field is running its ordinary way" — has to mean the
     * same thing on each. Defaulting release to earliest-first left the two
     * buttons resting identically while meaning opposite orders, which is how it
     * shipped to the first person who looked at it and is the one thing the
     * arrow convention exists to prevent.
     *
     * A SET still opens earliest-first: reading a trilogy in the order it came
     * out is a different act from browsing a library, and the set asks for that
     * order explicitly rather than inheriting it from here.
     */
    public function defaultDirection(): SortDirection
    {
        return match ($this) {
            self::Alphabetical => SortDirection::Ascending,
            self::DateAdded, self::Release => SortDirection::Descending,
        };
    }

    public function defaultOrder(): SortOrder
    {
        return $this->order($this->defaultDirection());
    }

    /**
     * Nested rather than matching on a [field, direction] pair, because only a
     * match over a single enum is provably exhaustive to a static analyser.
     */
    public function order(SortDirection $direction): SortOrder
    {
        return match ($this) {
            self::Alphabetical => match ($direction) {
                SortDirection::Ascending => SortOrder::Alphabetical,
                SortDirection::Descending => SortOrder::AlphabeticalDesc,
            },
            self::DateAdded => match ($direction) {
                SortDirection::Descending => SortOrder::DateAdded,
                SortDirection::Ascending => SortOrder::DateAddedAsc,
            },
            self::Release => match ($direction) {
                SortDirection::Descending => SortOrder::Release,
                SortDirection::Ascending => SortOrder::ReleaseAsc,
            },
        };
    }

    /**
     * The field named in words, for the sentences a button's text alternative
     * and tooltip are built from.
     */
    public function phrase(): string
    {
        return match ($this) {
            self::Alphabetical => 'title',
            self::DateAdded => 'date added',
            self::Release => 'release',
        };
    }

    /**
     * The glyph naming this field on its sort button, resolved here rather than
     * by a conditional in the template — the same way a category tab's icon is
     * named by the category itself.
     */
    public function glyph(): string
    {
        return match ($this) {
            self::Alphabetical => 'sort-title',
            self::DateAdded => 'sort-date',
            self::Release => 'sort-release',
        };
    }

    /**
     * The other fields, which the sort control renders as its inactive buttons.
     *
     * Was a single `other()` when there were two fields. A third makes "the
     * other one" not a thing, and the caller has to remember each of them
     * separately anyway — every field keeps its own last-used direction.
     *
     * @return list<self>
     */
    public function others(): array
    {
        return array_values(array_filter(self::all(), fn (self $field): bool => $field !== $this));
    }
}
