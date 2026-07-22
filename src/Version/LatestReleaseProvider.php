<?php

declare(strict_types=1);

namespace App\Version;

/**
 * Supplies the latest released version, or null when unknown or disabled.
 */
interface LatestReleaseProvider
{
    public function latestVersion(): ?string;
}
