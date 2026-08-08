<?php

declare(strict_types=1);

namespace App\Poster\Wall;

use App\Poster\Poster;
use App\Poster\PosterCategory;
use App\Poster\PosterStorage;

/**
 * Supplies random posters for the Poster Wall, drawn from the library's works.
 */
final class PosterWallService
{
    /**
     * The categories the wall draws from: the ones that are a work in their own
     * right. A season is part of a work and a collection is a set of works, so
     * neither is a title the wall exists to show.
     *
     * Deliberately an allow-list rather than {@see PosterCategory::all()} minus
     * the two — the forms differ in what they do with a category added later.
     * A new case must be named a work before it reaches the wall, rather than
     * arriving there by default and waiting for someone to notice.
     *
     * This lives here and not on PosterCategory because the enum is shared by
     * the gallery, search, import, export and orphan detection, none of which
     * care about the wall.
     *
     * @var list<PosterCategory>
     */
    private const WORK_CATEGORIES = [
        PosterCategory::Movies,
        PosterCategory::TvShows,
    ];

    public function __construct(private readonly PosterStorage $storage)
    {
    }

    /**
     * Whether the wall draws from this category.
     *
     * Public because the route that serves the wall's posters without a session
     * has to refuse everything the wall does not show. Asking here keeps that
     * refusal and the rotation reading from one list, so a category cannot
     * become publicly readable without also appearing on the wall.
     */
    public function shows(PosterCategory $category): bool
    {
        return in_array($category, self::WORK_CATEGORIES, true);
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
        foreach (self::WORK_CATEGORIES as $category) {
            foreach ($this->storage->list($category) as $poster) {
                $posters[] = $poster;
            }
        }

        shuffle($posters);

        return array_slice($posters, 0, $count);
    }
}
