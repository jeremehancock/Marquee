<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Plex\PlexPosterWriter;

/**
 * Records the write calls made against it, for asserting export behavior.
 */
final class FakePlexPosterWriter implements PlexPosterWriter
{
    /** @var list<string> */
    public array $uploaded = [];

    /** @var list<string> */
    public array $locked = [];

    /** @var list<array{section: string, type: int, rating: string}> */
    public array $labelRemovals = [];

    public function uploadPoster(string $ratingKey, string $imageBytes): void
    {
        $this->uploaded[] = $ratingKey;
    }

    public function lockPoster(string $ratingKey): void
    {
        $this->locked[] = $ratingKey;
    }

    public function removeOverlayLabel(string $sectionKey, int $plexType, string $ratingKey): void
    {
        $this->labelRemovals[] = ['section' => $sectionKey, 'type' => $plexType, 'rating' => $ratingKey];
    }
}
