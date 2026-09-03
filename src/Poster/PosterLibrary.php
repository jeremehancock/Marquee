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
        // Deleting a poster must also forget its Plex mapping. Reading facts for
        // a render does NOT happen here — the caller reads them once and passes
        // them in — so this is a write dependency only.
        private readonly PlexItemRepository $items,
        private readonly SortComparator $comparator,
    ) {
    }

    /**
     * @param PosterFactsIndex $facts what import recorded about the posters in
     *        this view, read once by the caller — see paginate()
     */
    public function browse(
        PosterCategory $category,
        ?string $query,
        int $page,
        SortOrder $sort = SortOrder::Alphabetical,
        PosterFactsIndex $facts = new PosterFactsIndex(),
        ?string $setKey = null,
        ?string $originTitle = null,
    ): Page {
        return $this->paginate($this->storage->list($category), $query, $page, $sort, $facts, $setKey, $originTitle);
    }

    /**
     * The aggregate "All" view: every category's posters merged into one flat,
     * mixed listing under the selected sort order.
     *
     * @param PosterFactsIndex $facts see browse()
     */
    public function browseAll(
        ?string $query,
        int $page,
        SortOrder $sort = SortOrder::Alphabetical,
        PosterFactsIndex $facts = new PosterFactsIndex(),
        ?string $setKey = null,
        ?string $originTitle = null,
    ): Page {
        $posters = [];
        foreach (PosterCategory::all() as $category) {
            $posters = array_merge($posters, $this->storage->list($category));
        }

        return $this->paginate($posters, $query, $page, $sort, $facts, $setKey, $originTitle);
    }

    /**
     * Apply search or the selected sort, then slice into one page.
     *
     * The recorded facts arrive from the caller rather than being read here.
     * This used to fetch the titles again while filtering and the set keys again
     * while showing a set — two more scans of rows the caller had already read
     * for the same render. Taking them as a parameter is what makes "read once
     * per render" a property of the code rather than a coincidence of it: there
     * is no repository call left down here to drift.
     *
     * @param list<Poster> $posters
     */
    private function paginate(
        array $posters,
        ?string $query,
        int $page,
        SortOrder $sort,
        PosterFactsIndex $facts,
        ?string $setKey = null,
        ?string $originTitle = null,
    ): Page {
        // A set is an identity, not a description: it narrows the listing to the
        // posters recorded as belonging to one Plex item — a show with its
        // seasons, a collection with its films. It is applied before the search
        // and never alongside it, because the two answer the same question by
        // different means and the caller sends exactly one of them.
        $broaderQuery = null;
        $broaderTotal = 0;

        if ($setKey !== null && $setKey !== '') {
            $all = $posters;
            $posters = $this->inSet($posters, $setKey, $facts);

            // A set can be narrower than the library, and nothing on screen would
            // say so. Membership being exact does not make a COLLECTION complete:
            // a Plex collection holds what somebody put in it, so a set of eight
            // where the library holds nine is the ordinary result of a film never
            // added. Offered on exactly the terms a narrow typed search is — with
            // its count, never applied — and only when there is an origin poster
            // to derive a title from.
            if ($originTitle !== null && trim($originTitle) !== '') {
                [$broaderQuery, $broaderTotal] = $this->broaderThan(
                    $all,
                    $facts->titlesByCategory(),
                    count($posters),
                    // The title itself is a candidate here, unlike in the search
                    // case where it IS the baseline. "Jackass Forever" reaches
                    // nothing extra; "Jackass" reaches the ninth film.
                    [trim($originTitle), ...BroaderQuery::candidatesFor($originTitle)],
                );
            }
        } elseif ($query !== null && trim($query) !== '') {
            // Searching narrows the listing; it never reorders it. The selected
            // sort then applies to whatever survives, so the sort control means
            // the same thing whether or not a search is active.
            $titles = $facts->titlesByCategory();
            $all = $posters;
            $posters = $this->search->filter($posters, $query, $titles);
            [$broaderQuery, $broaderTotal] = $this->broaderThan(
                $all,
                $titles,
                count($posters),
                BroaderQuery::candidatesFor($query),
            );
        }

        usort($posters, $this->comparator->forOrder($sort, $facts));

        $total = count($posters);
        $perPage = $this->config->perPage;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));

        $items = array_slice($posters, ($page - 1) * $perPage, $perPage);

        return new Page($items, $page, $perPage, $total, $broaderQuery, $broaderTotal);
    }

    /**
     * The shortest-cut query worth offering alongside a search that found little,
     * with how many posters it would find.
     *
     * A library that keeps no Plex collections has no sets for its films, so
     * Related posters falls back to searching the poster's own title — which
     * reaches the rest of a series only from the shortest title in it. This is
     * what lets the gallery offer a way out of that without ever taking it: the
     * candidate is a link, and the count travels with it so one that is far too
     * broad announces itself before anyone follows it.
     *
     * Evaluated against the same unfiltered list the search just ran over, so it
     * costs no listing of its own. Candidates that find no more than the search
     * already did are dropped — there is nothing to offer.
     *
     * The same rule serves a narrow search and a narrow set, because they are
     * the same question: is there a shorter query that would find more than what
     * is on screen? Only the baseline differs — the number of matches for a
     * search, the size of the set for a set — and the comparison against it is
     * the whole of the suppression. A collection whose films share no words is
     * offered nothing without anything having to know that is what it is: no
     * title query finds more of the MCU than the MCU already holds.
     *
     * @param list<Poster>                         $all        the unfiltered listing
     * @param array<string, array<string, string>> $titles     recorded titles
     * @param int                                  $found      what is on screen now
     * @param list<string>                         $candidates queries worth trying
     *
     * @return array{0: string|null, 1: int}
     */
    private function broaderThan(array $all, array $titles, int $found, array $candidates): array
    {
        $best = null;
        $bestTotal = $found;

        foreach ($candidates as $candidate) {
            $total = count($this->search->filter($all, $candidate, $titles));
            if ($total > $bestTotal) {
                $best = $candidate;
                $bestTotal = $total;
            }
        }

        return $best === null ? [null, 0] : [$best, $bestTotal];
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
    private function inSet(array $posters, string $setKey, PosterFactsIndex $facts): array
    {
        // Membership, not equality: a poster records every set it belongs to, and
        // collections overlap. Comparing one key against one key is what left a
        // collection sharing a film with another holding nothing but its own
        // poster.
        return array_values(array_filter(
            $posters,
            static fn (Poster $poster): bool => in_array($setKey, $facts->for($poster)->setKeys, true),
        ));
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
