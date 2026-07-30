<?php

declare(strict_types=1);

namespace App\Poster\Source;

/**
 * One candidate poster returned by a poster source.
 *
 * Only `url` is guaranteed. Every other field is absent whenever the supplying
 * source does not report it, which is the common case rather than an edge case:
 * fanart.tv supplies no thumbnail or dimensions, TheTVDB no score, and TMDB
 * omits a score or language on a large share of its own posters.
 */
final class PosterCandidate
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $thumb = null,
        public readonly ?string $source = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $language = null,
        public readonly ?float $score = null,
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
