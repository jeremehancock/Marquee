<?php

declare(strict_types=1);

namespace App\Poster\Wall;

use App\Poster\Poster;
use App\Poster\PosterCategory;
use App\Poster\PosterStorage;

/**
 * Supplies random posters for the Poster Wall, drawn from every category.
 */
final class PosterWallService
{
    public function __construct(private readonly PosterStorage $storage)
    {
    }

    /**
     * @return list<Poster>
     */
    public function randomPosters(int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $posters = [];
        foreach (PosterCategory::all() as $category) {
            foreach ($this->storage->list($category) as $poster) {
                $posters[] = $poster;
            }
        }

        shuffle($posters);

        return array_slice($posters, 0, $count);
    }
}
