<?php

declare(strict_types=1);

namespace App\Poster\Source;

use App\Plex\PlexMediaType;

/**
 * What to search a poster source for: the work's title and type, plus the facts
 * that disambiguate it.
 *
 * Both extras are nullable and mean "not known", never "zero" — season 0 is the
 * Specials season, so every check on it must be `!== null`.
 */
final class PosterQuery
{
    public function __construct(
        public readonly string $title,
        public readonly PlexMediaType $mediaType,
        public readonly ?int $seasonNumber = null,
        public readonly ?int $year = null,
    ) {
    }
}
