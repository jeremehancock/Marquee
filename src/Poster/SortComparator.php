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
     * @param PosterFactsIndex $facts what import recorded about each poster;
     *        the date field reads its timestamps from here
     *
     * @return callable(Poster, Poster): int
     */
    public function forOrder(SortOrder $sort, PosterFactsIndex $facts = new PosterFactsIndex()): callable
    {
        $descending = $sort->direction() === SortDirection::Descending;

        return match ($sort->field()) {
            SortField::Alphabetical => $this->byTitle($descending),
            SortField::DateAdded => $this->byDateAdded($descending, $facts),
            SortField::Release => $this->byRelease($descending, $facts),
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
     * Release order: the year the work came out, then the season within it.
     *
     * **An unknown year sorts FIRST.** That is not a preference; it is the
     * placement that stays correct however Plex answers a question this code
     * cannot settle on its own — whether a collection carries a year. If it does
     * not, the collection leads the films it holds, which is how a set should
     * read. If it does, the collection sorts among its earliest films, which
     * also reads correctly. Sorting unknowns last is right only in the second
     * case, so it is the more fragile of the two.
     *
     * **Season number breaks a tie in the year, with "no season" first.** This
     * is what makes a show's own poster precede its seasons, and it needs no
     * special case to do it: a season records its SHOW's year (Plex reports none
     * on a season node), so a show and every season of it tie on the year, and
     * the show is the only one of them with no season number.
     *
     * Below those, category and then the article-aware key, so the listing is
     * fully deterministic rather than left in whatever order usort produced.
     *
     * @return callable(Poster, Poster): int
     */
    private function byRelease(bool $descending, PosterFactsIndex $facts): callable
    {
        $ignoreArticles = $this->config->ignoreArticlesInSort;

        return static function (Poster $a, Poster $b) use ($descending, $facts, $ignoreArticles): int {
            // PHP_INT_MIN rather than 0: a year of 0 is a year, and a poster
            // recording one would otherwise be indistinguishable from a poster
            // recording none.
            $yearOf = static fn (Poster $poster): int
                => $facts->for($poster)->year ?? PHP_INT_MIN;

            $primary = $yearOf($a) <=> $yearOf($b);
            if ($primary !== 0) {
                return $descending ? -$primary : $primary;
            }

            // Everything below the chosen field runs forwards whichever way that
            // field is pointing — the rule this class already follows — so a
            // show's seasons still read 1, 2, 3 with the order reversed.
            $seasonOf = static fn (Poster $poster): int
                => $facts->for($poster)->seasonNumber ?? PHP_INT_MIN;

            return [$seasonOf($a), $a->category->sortOrder(), $a->sortKey($ignoreArticles)]
                <=> [$seasonOf($b), $b->category->sortOrder(), $b->sortKey($ignoreArticles)];
        };
    }

    /**
     * Plex "added at" order, falling back to the file's modification time when
     * Plex has no timestamp for the poster. Category order breaks ties so a
     * mixed listing stays deterministic.
     *
     * @return callable(Poster, Poster): int
     */
    private function byDateAdded(bool $descending, PosterFactsIndex $facts): callable
    {
        return static function (Poster $a, Poster $b) use ($descending, $facts): int {
            $dateOf = static fn (Poster $poster): int
                => $facts->for($poster)->addedAt ?? $poster->modifiedAt;

            $primary = $dateOf($a) <=> $dateOf($b);
            if ($primary !== 0) {
                return $descending ? -$primary : $primary;
            }

            return $a->category->sortOrder() <=> $b->category->sortOrder();
        };
    }
}
