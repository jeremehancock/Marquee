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
     * @param ?string               $correctedTmdbId the id the source actually
     *                                               matched, set **only** when a
     *                                               TMDB id was sent and the
     *                                               source matched a different
     *                                               one — the signature of a
     *                                               stale stored id. Null in
     *                                               every other case, including
     *                                               a search that sent no id at
     *                                               all, so a caller can act on
     *                                               it without knowing what was
     *                                               sent.
     */
    public function __construct(
        public readonly PosterSearchOutcome $outcome,
        public readonly array $candidates = [],
        public readonly ?string $correctedTmdbId = null,
    ) {
    }

    /**
     * @param list<PosterCandidate> $candidates
     */
    public static function found(array $candidates, bool $partial = false, ?string $correctedTmdbId = null): self
    {
        if ($candidates === []) {
            return new self(PosterSearchOutcome::NoArtwork, [], $correctedTmdbId);
        }

        return new self(
            $partial ? PosterSearchOutcome::Partial : PosterSearchOutcome::Ok,
            $candidates,
            $correctedTmdbId,
        );
    }

    public static function failed(PosterSearchOutcome $outcome): self
    {
        return new self($outcome);
    }
}
