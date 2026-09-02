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
        // Searching narrows the listing; it never reorders it. The selected sort
        // then applies to whatever survives, so the sort control means the same
        // thing whether or not a search is active.
        if ($query !== null && trim($query) !== '') {
            $posters = $this->search->filter($posters, $query, $this->titlesFor($posters));
        }

        usort($posters, $this->comparator->forOrder($sort, $addedAt));

        $total = count($posters);
        $perPage = $this->config->perPage;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));

        $items = array_slice($posters, ($page - 1) * $perPage, $perPage);

        return new Page($items, $page, $perPage, $total);
    }

    /**
     * The recorded Plex titles for a set of posters, keyed by category value then
     * filename — what search matches against, so a poster is found by the title
     * on its card rather than by its sanitised filename.
     *
     * Built here rather than passed in by the controller because this is the one
     * place search is applied, and the repository is already held. Only the
     * categories actually present are read, so a single-category view costs one
     * query and the All view costs four — and nothing at all when no query is
     * active, since the caller only asks while filtering.
     *
     * @param list<Poster> $posters
     *
     * @return array<string, array<string, string>>
     */
    private function titlesFor(array $posters): array
    {
        $titles = [];
        foreach ($posters as $poster) {
            $category = $poster->category->value;
            if (!isset($titles[$category])) {
                $titles[$category] = $this->items->titlesForCategory($category);
            }
        }

        return $titles;
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
