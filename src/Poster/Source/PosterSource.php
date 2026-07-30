<?php

declare(strict_types=1);

namespace App\Poster\Source;

/**
 * Finds candidate posters for a media item.
 */
interface PosterSource
{
    public function find(PosterQuery $query): PosterSearchResult;
}
