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
     * @param ?string $page the supplying service's own page for this work, when
     *                      that service's licence requires a link back to it.
     *                      Absent otherwise, which is most candidates. It is the
     *                      *presence of this address* that obliges the interface
     *                      to show a credit link — never which service supplied
     *                      the candidate — so a service added upstream that owes
     *                      a link back is credited with no change here. For a
     *                      season this addresses the season's own page, which is
     *                      not the show's; the two are not interchangeable.
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
