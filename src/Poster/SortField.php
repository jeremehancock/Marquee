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
     * Titles read forwards; dates lead with whatever arrived most recently; a
     * release order reads the way a series was released, earliest first.
     */
    public function defaultDirection(): SortDirection
    {
        return match ($this) {
            self::Alphabetical, self::Release => SortDirection::Ascending,
            self::DateAdded => SortDirection::Descending,
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
                SortDirection::Ascending => SortOrder::Release,
                SortDirection::Descending => SortOrder::ReleaseDesc,
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
