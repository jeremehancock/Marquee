<?php

declare(strict_types=1);

namespace App\Poster;

use App\Config\PosterConfig;
use App\Database\PlexItemRepository;
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
        private readonly PlexItemRepository $items,
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
            // is deterministic. Within a single category the category tiebreak
            // never fires, so per-category ordering is unchanged.
            //
            // The raw title breaks what the category cannot. Digit-aware sort
            // keys make "Season 01" and "Season 1" genuinely equal — both pad
            // to the same number — and two such posters in one category would
            // otherwise be left in whatever order usort happened to produce.
            // It sits last so the category rule above it still decides first.
            usort(
                $posters,
                fn (Poster $a, Poster $b): int => [
                    $a->sortKey($this->config->ignoreArticlesInSort),
                    $a->category->sortOrder(),
                    $a->title(),
                ] <=> [
                    $b->sortKey($this->config->ignoreArticlesInSort),
                    $b->category->sortOrder(),
                    $b->title(),
                ],
            );
        }

        $total = count($posters);
        $perPage = $this->config->perPage;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));

        $items = array_slice($posters, ($page - 1) * $perPage, $perPage);

        return new Page($items, $page, $perPage, $total);
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
