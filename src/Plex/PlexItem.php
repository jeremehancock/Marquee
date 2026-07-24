<?php

declare(strict_types=1);

namespace App\Plex;

/**
 * A Plex item (movie, show, season, or collection) with its current poster path.
 */
final class PlexItem
{
    public function __construct(
        public readonly string $ratingKey,
        public readonly PlexMediaType $mediaType,
        public readonly string $title,
        public readonly ?int $year,
        public readonly ?string $thumb,
        public readonly string $libraryTitle,
        public readonly ?string $parentTitle = null,
        public readonly string $sectionKey = '',
        public readonly ?int $addedAt = null,
    ) {
    }

    /**
     * Display title, qualifying seasons with their show's title.
     */
    public function displayTitle(): string
    {
        if ($this->mediaType === PlexMediaType::Season && $this->parentTitle !== null && $this->parentTitle !== '') {
            return $this->parentTitle . ' - ' . $this->title;
        }

        return $this->title;
    }
}
