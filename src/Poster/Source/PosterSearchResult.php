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
 *
 * {@see sections()} groups them for display without disturbing that: the ranking
 * is given up *across* services, which is the cost of a section order that does
 * not move from item to item, but is preserved exactly within each one.
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

    /**
     * The candidates split into the labelled sections the results are shown in,
     * one per supplying service, in a fixed order.
     *
     * Built by partition, never by sorting: each candidate is appended to its
     * provider's bucket in arrival order, so the source's ranking survives inside
     * every section by construction. There is no comparator here that a later
     * change could quietly turn into re-ranking.
     *
     * A candidate whose service this build does not recognise — or which reports
     * none — goes to a trailing {@see PosterSection::OTHER} section rather than
     * being dropped. Dropping it would look exactly like that service having no
     * artwork, and nothing anywhere would say otherwise.
     *
     * Empty sections are omitted, so a service that returned nothing for an item
     * leaves no heading behind.
     *
     * @return list<PosterSection>
     */
    public function sections(): array
    {
        /** @var array<string, list<PosterCandidate>> $buckets */
        $buckets = [];
        /** @var list<PosterCandidate> $unrecognised */
        $unrecognised = [];

        foreach ($this->candidates as $candidate) {
            $provider = $candidate->source === null
                ? null
                : PosterProvider::tryFrom($candidate->source);

            if ($provider === null) {
                $unrecognised[] = $candidate;
                continue;
            }

            $buckets[$provider->value][] = $candidate;
        }

        $sections = [];
        foreach (PosterProvider::inSectionOrder() as $provider) {
            $candidates = $buckets[$provider->value] ?? [];
            if ($candidates !== []) {
                $sections[] = new PosterSection($provider->label(), $candidates);
            }
        }

        if ($unrecognised !== []) {
            $sections[] = new PosterSection(PosterSection::OTHER, $unrecognised);
        }

        return $sections;
    }
}
