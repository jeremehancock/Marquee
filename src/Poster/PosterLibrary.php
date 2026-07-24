<?php

declare(strict_types=1);

namespace App\Poster;

use App\Config\PosterConfig;
use App\Poster\Search\PosterSearch;

/**
 * Reads a category, applies search or an article-aware sort, and paginates.
 */
final class PosterLibrary
{
    public function __construct(
        private readonly PosterStorage $storage,
        private readonly PosterSearch $search,
        private readonly PosterConfig $config,
    ) {
    }

    /**
     * @param array<string, array<string, int>> $addedAt Plex "added at" timestamps
     *        keyed by category value then filename; used only for date-added sort.
     */
    public function browse(
        PosterCategory $category,
        ?string $query,
        int $page,
        SortOrder $sort = SortOrder::Alphabetical,
        array $addedAt = [],
    ): Page {
        return $this->paginate($this->storage->list($category), $query, $page, $sort, $addedAt);
    }

    /**
     * The aggregate "All" view: every category's posters merged into one flat,
     * mixed listing under the selected sort order.
     *
     * @param array<string, array<string, int>> $addedAt see browse()
     */
    public function browseAll(
        ?string $query,
        int $page,
        SortOrder $sort = SortOrder::Alphabetical,
        array $addedAt = [],
    ): Page {
        $posters = [];
        foreach (PosterCategory::all() as $category) {
            $posters = array_merge($posters, $this->storage->list($category));
        }

        return $this->paginate($posters, $query, $page, $sort, $addedAt);
    }

    /**
     * Apply search or the selected sort, then slice into one page.
     *
     * @param list<Poster>                       $posters
     * @param array<string, array<string, int>>  $addedAt
     */
    private function paginate(array $posters, ?string $query, int $page, SortOrder $sort, array $addedAt): Page
    {
        if ($query !== null && trim($query) !== '') {
            // Search results are already ranked by relevance.
            $posters = $this->search->filter($posters, $query);
        } elseif ($sort === SortOrder::DateAdded) {
            // Newest first by Plex "added at", falling back to the file's
            // modification time when Plex has no timestamp for the poster.
            // Category order breaks ties so a mixed listing stays deterministic.
            usort(
                $posters,
                function (Poster $a, Poster $b) use ($addedAt): int {
                    $byDate = $this->addedAtFor($b, $addedAt) <=> $this->addedAtFor($a, $addedAt);

                    return $byDate !== 0
                        ? $byDate
                        : $a->category->sortOrder() <=> $b->category->sortOrder();
                },
            );
        } else {
            // Sort by title, breaking ties by category order so a mixed listing
            // is deterministic. Within a single category the tiebreak never
            // fires, so per-category ordering is unchanged.
            usort(
                $posters,
                fn (Poster $a, Poster $b): int => [
                    $a->sortKey($this->config->ignoreArticlesInSort),
                    $a->category->sortOrder(),
                ] <=> [
                    $b->sortKey($this->config->ignoreArticlesInSort),
                    $b->category->sortOrder(),
                ],
            );
        }

        $total = count($posters);
        $perPage = $this->config->perPage;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));

        $items = array_slice($posters, ($page - 1) * $perPage, $perPage);

        return new Page(array_values($items), $page, $perPage, $total);
    }

    /**
     * The date to order a poster by: its Plex "added at" timestamp when known,
     * otherwise the file's modification time so it still holds a stable place.
     *
     * @param array<string, array<string, int>> $addedAt
     */
    private function addedAtFor(Poster $poster, array $addedAt): int
    {
        return $addedAt[$poster->category->value][$poster->filename] ?? $poster->modifiedAt;
    }

    public function delete(PosterCategory $category, string $filename): bool
    {
        return $this->storage->delete($category, $filename);
    }
}
