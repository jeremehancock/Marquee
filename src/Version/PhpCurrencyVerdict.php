<?php

declare(strict_types=1);

namespace App\Version;

/**
 * The outcome of a PhpCurrencyCheck: whether it passed, and the sentence that
 * says why, naming the versions involved.
 */
final class PhpCurrencyVerdict
{
    private function __construct(
        public readonly bool $passed,
        public readonly string $message,
    ) {
    }

    public static function pass(string $message): self
    {
        return new self(true, $message);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
