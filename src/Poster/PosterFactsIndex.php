<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * Everything the recorded Plex facts say about the posters in one view, keyed by
 * category and then by filename.
 *
 * Keyed by both because a filename is unique only within its own category, and
 * the All view merges all four.
 *
 * Built once per render and passed to everything that needs it — the listing,
 * the sort, the search and the template alike — so that how many reads a render
 * costs is decided by how many categories it holds and by nothing else. The
 * library used to read the titles again while filtering and the set keys again
 * while showing a set; with the index passed in there is no repository call left
 * down there to drift.
 */
final class PosterFactsIndex
{
    /**
     * @param array<string, array<string, PosterFacts>> $byCategory
     */
    public function __construct(private readonly array $byCategory = [])
    {
    }

    /**
     * What is recorded for one poster — never null, so no caller has to spell
     * the absent case a second time. A poster with no Plex mapping answers with
     * a set of facts that record nothing, which is what every fallback already
     * keys on.
     */
    public function for(Poster $poster): PosterFacts
    {
        return $this->byCategory[$poster->category->value][$poster->filename] ?? PosterFacts::none();
    }

    /**
     * The title Related posters searches for, falling back to the
     * filename-derived title for a poster with nothing recorded.
     *
     * Resolved here rather than in the template because the fallback is the same
     * wherever the question is asked.
     */
    public function relatedTitleFor(Poster $poster): string
    {
        $related = $this->for($poster)->relatedTitle;

        return $related === '' ? $poster->title() : $related;
    }

    /**
     * The recorded titles for one category, keyed by filename — what search
     * matches against, so a poster is found by the title on its card rather than
     * by its sanitised filename.
     *
     * Shaped for {@see \App\Poster\Search\PosterSearch}, which takes the map it
     * has always taken; a poster with no recorded title is left out so that it
     * falls back to matching its filename exactly as before.
     *
     * @return array<string, array<string, string>>
     */
    public function titlesByCategory(): array
    {
        $titles = [];
        foreach ($this->byCategory as $category => $facts) {
            foreach ($facts as $filename => $fact) {
                if ($fact->title !== null) {
                    $titles[$category][$filename] = $fact->title;
                }
            }
        }

        return $titles;
    }
}
