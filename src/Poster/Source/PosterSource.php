<?php

declare(strict_types=1);

namespace App\Poster\Source;

use App\Plex\PlexMediaType;

/**
 * Finds candidate poster image URLs for a media item.
 */
interface PosterSource
{
    /**
     * @return list<string> candidate poster image URLs (empty if none/unavailable)
     */
    public function find(string $title, PlexMediaType $mediaType, ?int $season): array;
}
