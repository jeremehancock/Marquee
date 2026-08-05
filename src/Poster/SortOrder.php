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
     * The direction in words. An arrow is not announced by assistive technology
     * and a rotated one says even less, so every button needs its direction
     * spelled out somewhere.
     */
    public function directionPhrase(): string
    {
        return match ($this) {
            self::Alphabetical => 'A to Z',
            self::AlphabeticalDesc => 'Z to A',
            self::DateAdded => 'newest first',
            self::DateAddedAsc => 'oldest first',
        };
    }

    /**
     * What activating a button applies — phrased as the instruction it is.
     */
    public function actionLabel(): string
    {
        return sprintf('Sort by %s, %s', $this->field()->phrase(), $this->directionPhrase());
    }

    /**
     * The order the gallery is in — phrased as the description it is.
     *
     * Kept distinct from the action because on the active button the two differ,
     * that difference being the whole toggle. One string doing both jobs reads as
     * an instruction while describing a state, which is precisely the misreading
     * to avoid: hovering the active button and being told "Sort by title, A to Z"
     * suggests that is what activating it will do, when activating it reverses.
     */
    public function stateLabel(): string
    {
        return sprintf('Sorted by %s, %s', $this->field()->phrase(), $this->directionPhrase());
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
