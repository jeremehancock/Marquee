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

    /**
     * The direction this field runs in until the user chooses otherwise.
     * Titles read forwards; dates lead with whatever arrived most recently.
     */
    public function defaultDirection(): SortDirection
    {
        return match ($this) {
            self::Alphabetical => SortDirection::Ascending,
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
        };
    }

    /**
     * The other field, which the sort control renders as its inactive button.
     */
    public function other(): self
    {
        return match ($this) {
            self::Alphabetical => self::DateAdded,
            self::DateAdded => self::Alphabetical,
        };
    }
}
