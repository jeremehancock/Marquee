<?php

declare(strict_types=1);

namespace App\Poster;

use App\Config\PosterConfig;
use App\Database\PlexItemRepository;
use App\Poster\Search\PosterSearch;

/**
 * Reads a category, applies search or the selected sort order, and paginates.
 */
final class PosterLibrary
{
    public function __construct(
        private readonly PosterStorage $storage,
        private readonly PosterSearch $search,
        private readonly PosterConfig $config,
        private readonly PlexItemRepository $items,
        private readonly SortComparator $comparator,
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
            // Search leads on relevance and settles equally relevant results
            // with the selected order, so the sort control still means something
            // while a search is active.
            $posters = $this->search->filter($posters, $query, $sort, $addedAt);
        } else {
            usort($posters, $this->comparator->forOrder($sort, $addedAt));
        }

        $total = count($posters);
        $perPage = $this->config->perPage;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));

        $items = array_slice($posters, ($page - 1) * $perPage, $perPage);

        return new Page($items, $page, $perPage, $total);
    }

    public function delete(PosterCategory $category, string $filename): bool
    {
        if (!$this->storage->delete($category, $filename)) {
            return false;
        }

        // Deleting the file must also forget its Plex mapping; otherwise the
        // stale row lingers and can resurface as a duplicate orphan once the
        // same item is recreated and re-imported.
        $this->items->deleteByCategoryAndFilename($category->value, $filename);

        return true;
    }
}
