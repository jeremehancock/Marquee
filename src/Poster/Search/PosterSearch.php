<?php

declare(strict_types=1);

namespace App\Poster\Search;

use App\Poster\NaturalOrder;
use App\Poster\Poster;
use Normalizer;

/**
 * Specific (not broadly fuzzy) poster search: every query term must appear in
 * the normalized title. Results are ranked by how early the query matches.
 */
final class PosterSearch
{
    /**
     * @param list<Poster> $posters
     *
     * @return list<Poster>
     */
    public function filter(array $posters, string $query): array
    {
        $terms = $this->terms($query);
        if ($terms === []) {
            return $posters;
        }

        /** @var list<array{score: int, order: string, poster: Poster}> $scored */
        $scored = [];
        foreach ($posters as $poster) {
            $title = $this->normalize($poster->title());
            $score = $this->score($title, $terms);
            if ($score !== null) {
                // Scoring reads the normalized title as-is, because a match
                // position only means anything against the real string. Only
                // the tie-break key is padded.
                $scored[] = [
                    'score' => $score,
                    'order' => NaturalOrder::key($title),
                    'poster' => $poster,
                ];
            }
        }

        // The score leads, so relevance still decides the ranking and the
        // digit-aware key only separates results that match equally early.
        // Without it a search for a show would list its seasons 1, 10, 11, 2 —
        // the same defect the gallery's own ordering has already lost.
        usort(
            $scored,
            static fn (array $a, array $b): int => [$a['score'], $a['order']] <=> [$b['score'], $b['order']],
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
