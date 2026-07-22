<?php

declare(strict_types=1);

namespace App\Plex\Import;

use App\Poster\PosterCategory;

/**
 * Running tally of an import.
 */
final class ImportResult
{
    private int $imported = 0;
    private int $failed = 0;
    private int $skipped = 0;

    /** @var array<string, int> */
    private array $byCategory = [];

    public function recordImported(PosterCategory $category): void
    {
        $this->imported++;
        $this->byCategory[$category->value] = ($this->byCategory[$category->value] ?? 0) + 1;
    }

    public function recordFailed(): void
    {
        $this->failed++;
    }

    public function recordSkipped(): void
    {
        $this->skipped++;
    }

    public function imported(): int
    {
        return $this->imported;
    }

    public function failed(): int
    {
        return $this->failed;
    }

    public function skipped(): int
    {
        return $this->skipped;
    }

    public function countFor(PosterCategory $category): int
    {
        return $this->byCategory[$category->value] ?? 0;
    }

    public function summary(): string
    {
        $summary = sprintf('Imported %d poster%s.', $this->imported, $this->imported === 1 ? '' : 's');
        if ($this->skipped > 0) {
            $summary .= sprintf(' Skipped %d unchanged.', $this->skipped);
        }
        if ($this->failed > 0) {
            $summary .= sprintf(' %d could not be imported.', $this->failed);
        }

        return $summary;
    }
}
