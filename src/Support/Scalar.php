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
}
