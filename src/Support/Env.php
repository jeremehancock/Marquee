<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small typed readers for environment variables. Configuration is read through
 * these helpers exactly once at bootstrap; the rest of the code never touches
 * the environment directly.
 */
final class Env
{
    public static function str(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : (int) $value;
    }

    public static function bool(string $key, bool $default): bool
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    public static function list(string $key, array $default = []): array
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            return $default;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v): bool => $v !== ''));
    }
}
