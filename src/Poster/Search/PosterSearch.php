<?php

declare(strict_types=1);

namespace App\Poster\Search;

use App\Poster\Poster;
use Normalizer;

/**
 * Specific (not broadly fuzzy) poster search: every query term must appear in
 * the normalized title.
 *
 * THE TITLE MATCHED IS THE ONE ON THE CARD. Where Plex recorded a title for a
 * poster, that is the haystack; only a poster with no record (or an empty one)
 * falls back to the filename-derived title. The filename is a sanitised copy —
 * every character outside A-Za-z0-9._- flattened to a separator, and the source
 * library appended — so matching it means answering for text that appears nowhere
 * on screen. Two consequences were the reason for the change:
 *
 *   - A title carrying a character the filename cannot hold was unfindable by its
 *     own name. "Amélie" reaches disk as Am_lie_2001_Movies.jpg, whose searchable
 *     text is "am lie 2001 movies"; normalize() folds the query's accent but keeps
 *     the letter, giving "amelie", which is not a substring of "am lie".
 *   - The appended library was silently matchable, so searching the name of a Plex
 *     library matched every poster imported from it.
 *
 * Exactly one haystack per poster, never both. Matching the filename as well would
 * look safer — nothing that matches today would stop matching — but the behaviour
 * preserved would be the accidental one, and a poster matching for reasons the
 * user cannot see on the card is precisely the confusion being removed.
 *
 * This decides which posters match and nothing else. sortKey() still derives from
 * the filename, so ordering is untouched by any of the above.
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
     * @param list<Poster>                          $posters
     * @param array<string, array<string, string>>  $titles Recorded Plex titles,
     *        keyed by category value then filename. Keyed by both because
     *        filenames are unique only within a category and the All view merges
     *        all four.
     *
     * @return list<Poster>
     */
    public function filter(array $posters, string $query, array $titles = []): array
    {
        $terms = $this->terms($query);
        if ($terms === []) {
            return $posters;
        }

        return array_values(array_filter(
            $posters,
            fn (Poster $poster): bool => $this->matches(
                $this->normalize($this->haystackFor($poster, $titles)),
                $terms,
            ),
        ));
    }

    /**
     * The text this poster is matched against: its recorded Plex title, or the
     * filename-derived title when it has no record. An empty recorded title falls
     * back too — that is already how the gallery treats one.
     *
     * @param array<string, array<string, string>> $titles
     */
    private function haystackFor(Poster $poster, array $titles): string
    {
        $recorded = $titles[$poster->category->value][$poster->filename] ?? '';

        return $recorded === '' ? $poster->title() : $recorded;
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
