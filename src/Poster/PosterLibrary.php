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
        ?string $setKey = null,
    ): Page {
        return $this->paginate($this->storage->list($category), $query, $page, $sort, $addedAt, $setKey);
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
        ?string $setKey = null,
    ): Page {
        $posters = [];
        foreach (PosterCategory::all() as $category) {
            $posters = array_merge($posters, $this->storage->list($category));
        }

        return $this->paginate($posters, $query, $page, $sort, $addedAt, $setKey);
    }

    /**
     * Apply search or the selected sort, then slice into one page.
     *
     * @param list<Poster>                       $posters
     * @param array<string, array<string, int>>  $addedAt
     */
    private function paginate(
        array $posters,
        ?string $query,
        int $page,
        SortOrder $sort,
        array $addedAt,
        ?string $setKey = null,
    ): Page {
        // A set is an identity, not a description: it narrows the listing to the
        // posters recorded as belonging to one Plex item — a show with its
        // seasons, a collection with its films. It is applied before the search
        // and never alongside it, because the two answer the same question by
        // different means and the caller sends exactly one of them.
        if ($setKey !== null && $setKey !== '') {
            $posters = $this->inSet($posters, $setKey);
        } elseif ($query !== null && trim($query) !== '') {
            // Searching narrows the listing; it never reorders it. The selected
            // sort then applies to whatever survives, so the sort control means
            // the same thing whether or not a search is active.
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
     * The posters recorded as belonging to one set, in the order they arrived.
     *
     * Like the search this only decides which posters survive; the sort applied
     * below orders them, so a set reads in whatever order the user chose.
     *
     * @param list<Poster> $posters
     *
     * @return list<Poster>
     */
    private function inSet(array $posters, string $setKey): array
    {
        $keys = [];
        foreach ($posters as $poster) {
            $category = $poster->category->value;
            if (!isset($keys[$category])) {
                $keys[$category] = $this->items->setKeysForCategory($category);
            }
        }

        // Membership, not equality: a poster records every set it belongs to, and
        // collections overlap. Comparing one key against one key is what left a
        // collection sharing a film with another holding nothing but its own
        // poster.
        return array_values(array_filter(
            $posters,
            static fn (Poster $poster): bool => in_array(
                $setKey,
                $keys[$poster->category->value][$poster->filename] ?? [],
                true,
            ),
        ));
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
