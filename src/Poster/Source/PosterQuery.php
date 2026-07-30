<?php

declare(strict_types=1);

namespace App\Poster\Source;

use App\Plex\PlexMediaType;

/**
 * What to search a poster source for: the work's title and type, plus the facts
 * that disambiguate it.
 *
 * Every extra is nullable and means "not known", never "zero" — season 0 is the
 * Specials season, so every check on it must be `!== null`.
 *
 * `$tmdbId` identifies the work exactly, where the title only describes it. For
 * a season it is the **show's** id: a season is addressed as a show plus a
 * season number and has no id of its own. It stays a string because that is how
 * Plex reports it and how it is stored; what counts as a *sendable* id is the
 * source's decision, not this object's.
 */
final class PosterQuery
{
    public function __construct(
        public readonly string $title,
        public readonly PlexMediaType $mediaType,
        public readonly ?int $seasonNumber = null,
        public readonly ?int $year = null,
        public readonly ?string $tmdbId = null,
    ) {
    }
}
