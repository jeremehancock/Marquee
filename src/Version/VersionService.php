<?php

declare(strict_types=1);

namespace App\Version;

/**
 * Reports the current version and whether a newer release is available.
 */
final class VersionService
{
    public function __construct(
        private readonly string $current,
        private readonly LatestReleaseProvider $latest,
    ) {
    }

    public function current(): string
    {
        return $this->current;
    }

    public function latest(): ?string
    {
        return $this->latest->latestVersion();
    }

    public function updateAvailable(): bool
    {
        $latest = $this->latest();

        return $latest !== null && version_compare($latest, $this->current, '>');
    }
}
