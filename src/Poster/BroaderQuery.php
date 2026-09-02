<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * Shorter queries that might find the rest of a work's set, for a search that
 * found little.
 *
 * Related posters shows the set a poster belongs to, and a set is what Plex
 * says it is — a collection, or a show and its seasons. A library that keeps no
 * collections has no sets for its films, so the action falls back to searching
 * the poster's own title, and that only reaches the rest of a series when you
 * start from the shortest title in it: "The Matrix" finds its sequels, "The
 * Matrix Reloaded" finds itself.
 *
 * This produces the shorter titles worth offering in that case. It is offered,
 * never applied: the gallery shows it as a link with the number of posters it
 * would find, so a candidate that is far too broad announces itself before
 * anyone follows it. That is what makes a guess acceptable here — the user
 * chooses it knowing what it costs, rather than the system quietly widening a
 * search on their behalf.
 *
 * The candidates are deliberately naive, because a rule clever enough to be
 * trusted silently does not exist. Nothing here is used to *decide* anything.
 */
final class BroaderQuery
{
    /**
     * Shorter than this and a query stops describing a work at all. "300" is
     * three characters and a real title; two is not worth offering.
     */
    private const MIN_LENGTH = 3;

    /**
     * Every shorter query worth trying for this one, longest first, without
     * duplicates and without the query itself.
     *
     * Longest first because the least aggressive cut is the one most likely to
     * be what the reader meant, and the caller keeps the best-scoring candidate
     * rather than the first — so the order only breaks ties.
     *
     * @return list<string>
     */
    public static function candidatesFor(string $query): array
    {
        $query = trim($query);

        $candidates = array_filter([
            self::beforeFirst($query, ':'),
            self::beforeFirst($query, ' - '),
            self::withoutTrailingNumber($query),
        ], static fn (?string $c): bool => $c !== null);

        $unique = [];
        foreach ($candidates as $candidate) {
            if ($candidate !== $query && mb_strlen($candidate) >= self::MIN_LENGTH) {
                $unique[$candidate] = true;
            }
        }

        $result = array_keys($unique);
        usort($result, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $result;
    }

    /**
     * The part before the first occurrence of a separator: "Jackass: Best and
     * Last" becomes "Jackass", "Rebel Moon - Part One" becomes "Rebel Moon".
     *
     * The FIRST rather than the last, because a series name comes before its
     * subtitle and a subtitle may carry separators of its own — "Star Wars:
     * Episode II - Attack of the Clones" is the series, an episode, and a name,
     * and only the first cut reaches "Star Wars".
     */
    private static function beforeFirst(string $query, string $separator): ?string
    {
        $at = mb_strpos($query, $separator);
        if ($at === false || $at === 0) {
            return null;
        }

        return trim(mb_substr($query, 0, $at));
    }

    /**
     * The title without a trailing instalment: "Lethal Weapon 2", "Rocky III"
     * and "Jackass 3D" become "Lethal Weapon", "Rocky" and "Jackass".
     *
     * A last word is dropped when it opens with a digit, or is a roman numeral,
     * or is "Part"/"Chapter" followed by either. Roman numerals are matched
     * against a fixed vocabulary rather than a pattern, because a pattern for
     * them also matches real words — "I", "Mix", "Did", "Lid" and "Mill" are all
     * roman numerals to a regular expression.
     */
    private static function withoutTrailingNumber(string $query): ?string
    {
        $words = preg_split('/\s+/', $query) ?: [];
        if (count($words) < 2) {
            return null;
        }

        $last = (string) end($words);
        if (!self::isInstalment($last)) {
            return null;
        }

        array_pop($words);

        // "Part 2" and "Chapter 4" carry the number in the word before it.
        $preceding = count($words) > 1 ? mb_strtolower((string) end($words)) : '';
        if ($preceding === 'part' || $preceding === 'chapter') {
            array_pop($words);
        }

        $base = trim(implode(' ', $words));

        return $base === '' ? null : $base;
    }

    private static function isInstalment(string $word): bool
    {
        $word = rtrim($word, '.,');
        if ($word === '') {
            return false;
        }

        if (preg_match('/^\d/', $word) === 1) {
            return true;
        }

        return in_array(mb_strtoupper($word), [
            'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X',
        ], true);
    }
}
