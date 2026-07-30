<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Coerce values of unknown (mixed) type into a concrete scalar at the boundary
 * where untyped data enters the app — PDO rows and session values, whose types
 * the analyzer cannot know. Non-scalar or non-numeric input yields the neutral
 * default rather than an error, matching the previous plain-cast behavior.
 */
final class Scalar
{
    public static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Like int(), but preserves the difference between "absent" and a real zero.
     * A season number of 0 means Specials, so a defaulted 0 cannot stand in for
     * a missing value.
     */
    public static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Like string(), but preserves the difference between "absent" and a real
     * empty string. A TMDB id is either known or not known, and a defaulted ''
     * would read as a known-but-blank id.
     */
    public static function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $string = (string) $value;

        return $string !== '' ? $string : null;
    }
}
