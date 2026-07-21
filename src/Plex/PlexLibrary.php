<?php

declare(strict_types=1);

namespace App\Plex;

/**
 * A Plex library ("section").
 */
final class PlexLibrary
{
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $type,
    ) {
    }

    public function isMovie(): bool
    {
        return $this->type === 'movie';
    }

    public function isShow(): bool
    {
        return $this->type === 'show';
    }
}
