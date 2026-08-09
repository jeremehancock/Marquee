<?php

declare(strict_types=1);

namespace App\Plex\Poster;

use App\Database\PlexItemRepository;
use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Poster\PosterCategory;

/**
 * The posters Plex holds for the item a stored poster is linked to.
 *
 * The counterpart to Find Posters, and deliberately not a
 * {@see \App\Poster\Source\PosterSource}: that interface resolves a *title* to
 * a work and can fail at it, while this is addressed by a rating key already
 * recorded at import. There is no matching step here, so there is no no-match,
 * no rate limit, and no stale identifier to correct.
 */
final class PlexPosterService
{
    public function __construct(
        private readonly PlexClient $plex,
        private readonly PlexItemRepository $items,
    ) {
    }

    public function forPoster(PosterCategory $category, string $filename): PlexPosterListing
    {
        $record = $this->items->findByFilename($category->value, $filename);
        if ($record === null) {
            return PlexPosterListing::failed(PlexPosterOutcome::NotLinked);
        }

        try {
            return PlexPosterListing::of($this->plex->itemPosters($record->ratingKey));
        } catch (PlexException) {
            // Unreachable, unconfigured, or refused — all transient from the
            // user's side, and all leaving the stored poster untouched.
            return PlexPosterListing::failed(PlexPosterOutcome::Unavailable);
        }
    }
}
