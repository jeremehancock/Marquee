<?php

declare(strict_types=1);

namespace App\Poster\Search;

use App\Poster\Poster;
use Normalizer;

/**
 * Specific (not broadly fuzzy) poster search: every query term must appear in
 * the normalized title.
 *
 * This decides which posters match and nothing else. Their order is the gallery's
 * to choose, and it applies the sort the user selected — searching narrows the
 * listing without rearranging what survives.
 *
 * It did once rank by how early the query matched, which read as a defect the
 * moment the sort control gained directions: a poster whose title merely contained
 * the query sat below every title beginning with it, however the user had asked
 * for the list to be ordered. Sorting by date added would then leave the newest
 * poster stranded at the bottom. Relevance is not something the user asked for;
 * the sort order is.
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

        return array_values(array_filter(
            $posters,
            fn (Poster $poster): bool => $this->matches($this->normalize($poster->title()), $terms),
        ));
    }

    /**
     * Every term must appear somewhere in the title — where it appears carries no
     * weight, only whether it does.
     *
     * @param list<string> $terms
     */
    private function matches(string $haystack, array $terms): bool
    {
        foreach ($terms as $term) {
            if (!str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
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
