<?php

declare(strict_types=1);

namespace App\Poster\Source;

/**
 * The result of a poster search: what came back, and why.
 *
 * Candidates stay in the order the source returned them. That order encodes a
 * ranking (language match, then score, then resolution) built from signals that
 * are mostly not in the response, so re-sorting here would throw information
 * away rather than add any.
 */
final class PosterSearchResult
{
    /**
     * @param list<PosterCandidate> $candidates
     */
    public function __construct(
        public readonly PosterSearchOutcome $outcome,
        public readonly array $candidates = [],
    ) {
    }

    /**
     * @param list<PosterCandidate> $candidates
     */
    public static function found(array $candidates, bool $partial = false): self
    {
        if ($candidates === []) {
            return new self(PosterSearchOutcome::NoArtwork);
        }

        return new self($partial ? PosterSearchOutcome::Partial : PosterSearchOutcome::Ok, $candidates);
    }

    public static function failed(PosterSearchOutcome $outcome): self
    {
        return new self($outcome);
    }
}
