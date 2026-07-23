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

    public function browse(PosterCategory $category, ?string $query, int $page): Page
    {
        return $this->paginate($this->storage->list($category), $query, $page);
    }

    /**
     * The aggregate "All" view: every category's posters merged into one flat,
     * mixed-alphabetical listing.
     */
    public function browseAll(?string $query, int $page): Page
    {
        $posters = [];
        foreach (PosterCategory::all() as $category) {
            $posters = array_merge($posters, $this->storage->list($category));
        }

        return $this->paginate($posters, $query, $page);
    }

    /**
     * Apply search or the article-aware sort, then slice into one page.
     *
     * @param list<Poster> $posters
     */
    private function paginate(array $posters, ?string $query, int $page): Page
    {
        if ($query !== null && trim($query) !== '') {
            // Search results are already ranked by relevance.
            $posters = $this->search->filter($posters, $query);
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

    public function delete(PosterCategory $category, string $filename): bool
    {
        return $this->storage->delete($category, $filename);
    }
}
