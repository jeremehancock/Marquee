<?php

declare(strict_types=1);

namespace App\Plex;

use App\Poster\PosterCategory;

/**
 * The kinds of Plex item Marquee imports, mapped to poster categories.
 */
enum PlexMediaType: string
{
    case Movie = 'movie';
    case Show = 'show';
    case Season = 'season';
    case Collection = 'collection';

    public function category(): PosterCategory
    {
        return match ($this) {
            self::Movie => PosterCategory::Movies,
            self::Show => PosterCategory::TvShows,
            self::Season => PosterCategory::TvSeasons,
            self::Collection => PosterCategory::Collections,
        };
    }

    /**
     * Plex's numeric metadata type, used when editing an item's labels.
     */
    public function plexTypeNumber(): int
    {
        return match ($this) {
            self::Movie => 1,
            self::Show => 2,
            self::Season => 3,
            self::Collection => 18,
        };
    }

    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
