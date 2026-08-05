<?php

declare(strict_types=1);

namespace App\Poster;

use App\Config\PosterConfig;

/**
 * Builds the comparison a sort order asks for, so the gallery's own listing and
 * a search's tie-break order posters by exactly the same rules.
 *
 * Direction reverses the chosen field and nothing else. The tie-breaks below it
 * always run forwards, so posters that compare equal on the field the user
 * actually picked keep their relative order — a show's seasons stay 1, 2, 3
 * under Z–A rather than scrambling, which is the whole point of having a
 * digit-aware key in the first place.
 */
final class SortComparator
{
    public function __construct(private readonly PosterConfig $config)
    {
    }

    /**
     * @param array<string, array<string, int>> $addedAt Plex "added at" timestamps
     *        keyed by category value then filename; used only by the date field.
     *
     * @return callable(Poster, Poster): int
     */
    public function forOrder(SortOrder $sort, array $addedAt = []): callable
    {
        $descending = $sort->direction() === SortDirection::Descending;

        return match ($sort->field()) {
            SortField::Alphabetical => $this->byTitle($descending),
            SortField::DateAdded => $this->byDateAdded($descending, $addedAt),
        };
    }

    /**
     * Article-aware title order. Category breaks a tie so a mixed listing is
     * deterministic; within one category that never fires, so per-category
     * ordering is unchanged. The raw title breaks what the category cannot —
     * digit-aware keys make "Season 01" and "Season 1" genuinely equal, and two
     * such posters in one category would otherwise be left in whatever order
     * usort happened to produce.
     *
     * @return callable(Poster, Poster): int
     */
    private function byTitle(bool $descending): callable
    {
        $ignoreArticles = $this->config->ignoreArticlesInSort;

        return static function (Poster $a, Poster $b) use ($descending, $ignoreArticles): int {
            $primary = $a->sortKey($ignoreArticles) <=> $b->sortKey($ignoreArticles);
            if ($primary !== 0) {
                return $descending ? -$primary : $primary;
            }

            return [$a->category->sortOrder(), $a->title()] <=> [$b->category->sortOrder(), $b->title()];
        };
    }

    /**
     * Plex "added at" order, falling back to the file's modification time when
     * Plex has no timestamp for the poster. Category order breaks ties so a
     * mixed listing stays deterministic.
     *
     * @param array<string, array<string, int>> $addedAt
     *
     * @return callable(Poster, Poster): int
     */
    private function byDateAdded(bool $descending, array $addedAt): callable
    {
        return static function (Poster $a, Poster $b) use ($descending, $addedAt): int {
            $dateOf = static fn (Poster $poster): int
                => $addedAt[$poster->category->value][$poster->filename] ?? $poster->modifiedAt;

            $primary = $dateOf($a) <=> $dateOf($b);
            if ($primary !== 0) {
                return $descending ? -$primary : $primary;
            }

            return $a->category->sortOrder() <=> $b->category->sortOrder();
        };
    }
}
