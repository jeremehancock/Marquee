<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * One environment variable that is still set but no longer read, and why.
 */
final class SupersededVariable
{
    public function __construct(
        public readonly string $name,
        public readonly SupersededKind $kind,
    ) {
    }

    public function isRetired(): bool
    {
        return $this->kind === SupersededKind::Retired;
    }

    public function isRelocated(): bool
    {
        return $this->kind === SupersededKind::Relocated;
    }
}
