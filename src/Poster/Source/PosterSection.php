<?php

declare(strict_types=1);

namespace App\Poster\Source;

/**
 * One labelled section of the Find Posters results: the candidates one service
 * supplied, under the name that service is shown by.
 *
 * The label is resolved here rather than in the page so the browser never learns
 * a provider name. It renders whatever sections it is given, in the order given,
 * which means adding a provider is a change on this side of the wire only.
 */
final class PosterSection
{
    /**
     * The label for candidates whose service this build does not recognise.
     *
     * Deliberately vague, because by the time it is rendered the system does not
     * know what it is looking at. It should never appear: the source populates
     * every candidate's `source` from a closed set. It exists so that a service
     * that adds a provider costs users a vague heading rather than posters that
     * quietly go missing.
     */
    public const OTHER = 'Other';

    /**
     * @param list<PosterCandidate> $candidates in the order the source returned
     *                                          them, which is preserved within a
     *                                          section even though the sections
     *                                          themselves are ordered by provider
     */
    public function __construct(
        public readonly string $label,
        public readonly array $candidates,
    ) {
    }
}
