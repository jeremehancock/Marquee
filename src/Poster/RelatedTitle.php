<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * The title a poster's related set is named by — what Related posters searches
 * for.
 *
 * A season answers with its show's title, so the search gathers the show and
 * every sibling season rather than the one season it started from. Everything
 * else answers with its own title.
 *
 * The year is deliberately never part of this. The caption appends it, but a
 * query carrying "(1999)" would narrow the search back to the single poster the
 * action was started from.
 *
 * There are two ways to reach a season's show title, and the order matters:
 *
 *   1. `parent_title`, recorded at import. Exact — Plex reports the show and the
 *      season separately, so nothing is guessed. This is the answer whenever it
 *      is there.
 *   2. Failing that, strip the season off the recorded display title. Import
 *      joined it as `<show> - <season>`, and the season number is already
 *      recorded, so the suffix being removed is one we can name rather than one
 *      we go looking for.
 *
 * The second exists because the first only arrives on the next import that
 * includes TV Seasons. Without it the feature looks broken on the install it is
 * delivered to: every season searches its own full title and finds only itself.
 * A fallback that costs nothing and self-corrects is better than a correct
 * feature nobody sees working.
 *
 * The strip is deliberately narrow. It removes a trailing " - Season <n>" only
 * where <n> is the season number already recorded for that row, and
 * " - Specials" only for season zero. It is NOT a split on " - ": that misreads a
 * show whose own name contains one ("Cowboy Bebop - Remastered") and a season
 * whose name does ("Part 2 - Finale"). Anything it does not recognise is left
 * exactly as it is, which is the pre-existing narrow behaviour and never wrong —
 * and `parent_title` corrects those on the next import.
 */
final class RelatedTitle
{
    /**
     * @param string   $title        the recorded display title
     * @param string   $parentTitle  the recorded show title, empty when not a
     *                               season or not yet recorded
     * @param int|null $seasonNumber the recorded Plex season number, null when
     *                               the item is not a season
     */
    public static function forRecord(string $title, string $parentTitle, ?int $seasonNumber): string
    {
        if ($parentTitle !== '') {
            return $parentTitle;
        }

        if ($seasonNumber === null) {
            return $title;
        }

        return self::withoutSeasonSuffix($title, $seasonNumber);
    }

    /**
     * The display title with its own season removed, when the suffix is the one
     * the recorded season number predicts. Zero is Specials, which Plex names
     * rather than numbers.
     *
     * Trailing whitespace is not trimmed back into a match: a title is compared
     * against the exact suffix import would have produced, so a season named
     * "Season 1 (Remastered)" does not lose part of itself to a prefix match.
     */
    private static function withoutSeasonSuffix(string $title, int $seasonNumber): string
    {
        $candidates = [' - Season ' . $seasonNumber];
        if ($seasonNumber === 0) {
            $candidates[] = ' - Specials';
        }

        foreach ($candidates as $suffix) {
            if (!str_ends_with($title, $suffix)) {
                continue;
            }

            $base = substr($title, 0, -strlen($suffix));

            // A season whose show title is somehow empty leaves nothing to search
            // for, so keep the whole title rather than returning "".
            if ($base !== '') {
                return $base;
            }
        }

        return $title;
    }
}
