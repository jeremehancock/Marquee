<?php

declare(strict_types=1);

namespace App\Poster\Search;

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
            return array_values($posters);
        }

        /** @var list<array{score: int, title: string, poster: Poster}> $scored */
        $scored = [];
        foreach ($posters as $poster) {
            $title = $this->normalize($poster->title());
            $score = $this->score($title, $terms);
            if ($score !== null) {
                $scored[] = ['score' => $score, 'title' => $title, 'poster' => $poster];
            }
        }

        usort(
            $scored,
            static fn (array $a, array $b): int => [$a['score'], $a['title']] <=> [$b['score'], $b['title']],
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
        if ($decomposed !== false) {
            $value = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $value;
        }

        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
