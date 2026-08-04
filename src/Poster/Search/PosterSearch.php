<?php

declare(strict_types=1);

namespace App\Poster\Search;

use App\Poster\Poster;
use App\Poster\SortComparator;
use App\Poster\SortOrder;
use Normalizer;

/**
 * Specific (not broadly fuzzy) poster search: every query term must appear in
 * the normalized title. Results are ranked by how early the query matches.
 */
final class PosterSearch
{
    public function __construct(private readonly SortComparator $comparator)
    {
    }

    /**
     * @param list<Poster>                      $posters
     * @param array<string, array<string, int>> $addedAt Plex "added at" timestamps,
     *        needed only when the tie-break orders by date
     *
     * @return list<Poster>
     */
    public function filter(
        array $posters,
        string $query,
        SortOrder $sort = SortOrder::Alphabetical,
        array $addedAt = [],
    ): array {
        $terms = $this->terms($query);
        if ($terms === []) {
            return $posters;
        }

        /** @var list<array{score: int, poster: Poster}> $scored */
        $scored = [];
        foreach ($posters as $poster) {
            // Scoring reads the normalized title as-is, because a match position
            // only means anything against the real string.
            $score = $this->score($this->normalize($poster->title()), $terms);
            if ($score !== null) {
                $scored[] = ['score' => $score, 'poster' => $poster];
            }
        }

        // The score leads, so relevance still decides the ranking and the
        // selected order only separates results that match equally early. That
        // keeps the sort control meaningful during a search without ever letting
        // it promote a weaker match above a stronger one.
        $tieBreak = $this->comparator->forOrder($sort, $addedAt);
        usort(
            $scored,
            static function (array $a, array $b) use ($tieBreak): int {
                $byScore = $a['score'] <=> $b['score'];

                return $byScore !== 0 ? $byScore : $tieBreak($a['poster'], $b['poster']);
            },
        );

        return array_map(static fn (array $row): Poster => $row['poster'], $scored);
    }

    /**
     * @param list<string> $terms
     *
     * @return int|null lower is a better match, or null if not all terms match
     */
    private function score(string $haystack, array $terms): ?int
    {
        $firstPosition = null;
        foreach ($terms as $term) {
            $position = strpos($haystack, $term);
            if ($position === false) {
                return null;
            }
            if ($firstPosition === null || $position < $firstPosition) {
                $firstPosition = $position;
            }
        }

        return $firstPosition ?? 0;
    }

    /**
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $normalized = $this->normalize($query);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $normalized), static fn (string $t): bool => $t !== ''));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);

        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);
        if (is_string($decomposed)) {
            $value = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $value;
        }

        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
