<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * Turns an already-normalized title into a key that orders numbers by value.
 *
 * A plain string comparison reads a title character by character, so "10" beats
 * "2" on its first digit and a show's seasons list as 1, 10, 11 … 19, 2, 20.
 * Padding every run of digits to a fixed width makes the comparison line the
 * numbers up by magnitude instead, and leaves everything either side of them
 * untouched, so the rest of the ordering is exactly as it was.
 *
 * This is a sort key and nothing else. It is never displayed, never written to
 * a filename, and never stored — the padding would be plainly visible if it
 * were.
 *
 * The input must already be normalized by the caller. Marquee normalizes titles
 * two different ways (the gallery lowercases; search also folds accents and
 * flattens punctuation), and only the padding step is shared, so this
 * deliberately does no normalizing of its own.
 */
final class NaturalOrder
{
    /**
     * Wide enough for any number that appears in a real media title — seasons,
     * sequels, years — with room to spare. It is deliberately not enormous:
     * every digit run in every key carries this width, and a failing test's
     * diff has to stay readable.
     */
    private const PAD_WIDTH = 12;

    /**
     * A digit run longer than the pad is left exactly as it is, so it compares
     * character by character as it always has. That is the pre-existing
     * behaviour rather than a new failure mode: padding such a run to a width
     * shorter than itself would be the thing that ordered it incorrectly.
     */
    public static function key(string $normalized): string
    {
        $padded = preg_replace_callback(
            '/\d+/',
            static function (array $match): string {
                $digits = $match[0];

                return strlen($digits) > self::PAD_WIDTH
                    ? $digits
                    : str_pad($digits, self::PAD_WIDTH, '0', STR_PAD_LEFT);
            },
            $normalized,
        );

        return $padded ?? $normalized;
    }
}
