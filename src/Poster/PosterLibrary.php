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
        $posters = $this->storage->list($category);

        if ($query !== null && trim($query) !== '') {
            // Search results are already ranked by relevance.
            $posters = $this->search->filter($posters, $query);
        } else {
            usort(
                $posters,
                fn (Poster $a, Poster $b): int => $a->sortKey($this->config->ignoreArticlesInSort)
                    <=> $b->sortKey($this->config->ignoreArticlesInSort),
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
