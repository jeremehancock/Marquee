<?php

declare(strict_types=1);

namespace App\Poster\Source;

/**
 * One candidate poster returned by a poster source.
 *
 * Only `url` is guaranteed. Every other field is absent whenever the supplying
 * source does not report it, which is the common case rather than an edge case:
 * fanart.tv supplies no thumbnail or dimensions, TheTVDB no score, and TMDB
 * omits a score or language on a large share of its own posters. TVmaze reports
 * neither a score nor a language at all, and a TVmaze *season* poster reports no
 * dimensions either — so a candidate carrying `url`, `thumb` and `page` and
 * nothing else is a real response, not a malformed one.
 */
final class PosterCandidate
{
    /**
     * The two fields below look alike and are not. Keeping them apart is the
     * whole point:
     *
     * - `page` is **provenance** — where this poster came from. Showing it is a
     *   product decision, ours to make and ours to revisit.
     * - `attributionRequired` is an **obligation** — the supplying service's
     *   licence compels the link to be rendered. Not ours to revisit.
     *
     * Nearly every candidate carries a `page`; only a few are marked. Treating
     * the address as the trigger would assert a licence condition about artwork
     * that carries none, and would leave the real obligation indistinguishable
     * from decoration — so the next change that thins the links would have
     * nothing stopping it dropping the one that counts.
     *
     * @param ?string $page the supplying service's own page for this work. For a
     *                      season this is the season's own page where the service
     *                      publishes one, and its series page where it does not,
     *                      so two candidates for one work may legitimately
     *                      disagree. Optional: a service with no resolvable page
     *                      omits it rather than guessing.
     * @param bool $attributionRequired whether that service's licence requires
     *                                  the link be shown wherever the poster is.
     *                                  Never conditioned on which service
     *                                  supplied the candidate, so a service
     *                                  licensed this way later is credited with
     *                                  no change here.
     */
    public function __construct(
        public readonly string $url,
        public readonly ?string $thumb = null,
        public readonly ?string $source = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $language = null,
        public readonly ?float $score = null,
        public readonly ?string $page = null,
        public readonly bool $attributionRequired = false,
    ) {
    }

    /**
     * The image to show in the candidate grid: the reduced-size thumbnail when
     * the source supplied one, else the full-resolution image.
     */
    public function displayUrl(): string
    {
        return $this->thumb ?? $this->url;
    }
}
