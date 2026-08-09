<?php

declare(strict_types=1);

namespace App\Plex\Poster;

/**
 * What listing an item's Plex-held posters produced, and why.
 */
final class PlexPosterListing
{
    private function __construct(
        public readonly PlexPosterOutcome $outcome,
        public readonly ?PlexPosterList $posters = null,
    ) {
    }

    /**
     * A list that came back empty is {@see PlexPosterOutcome::None}, not an
     * empty success. An item can hold nothing of its own while Plex still
     * offers remote provider artwork for it, and the two read identically to a
     * user staring at a grid with no posters in it — so the distinction is
     * made here rather than left to the template.
     */
    public static function of(PlexPosterList $posters): self
    {
        return $posters->isEmpty()
            ? new self(PlexPosterOutcome::None)
            : new self(PlexPosterOutcome::Ok, $posters);
    }

    public static function failed(PlexPosterOutcome $outcome): self
    {
        return new self($outcome);
    }

    /**
     * @return list<PlexPosterCandidate>
     */
    public function uploaded(): array
    {
        return $this->posters?->uploaded() ?? [];
    }

    /**
     * @return list<PlexPosterCandidate>
     */
    public function server(): array
    {
        return $this->posters?->server() ?? [];
    }
}
