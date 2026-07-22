<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * The four fixed poster categories. The backing value is the URL slug, which is
 * also the on-disk directory name.
 */
enum PosterCategory: string
{
    case Movies = 'movies';
    case TvShows = 'tv-shows';
    case TvSeasons = 'tv-seasons';
    case Collections = 'collections';

    public function label(): string
    {
        return match ($this) {
            self::Movies => 'Movies',
            self::TvShows => 'TV Shows',
            self::TvSeasons => 'TV Seasons',
            self::Collections => 'Collections',
        };
    }

    /**
     * The directory name for this category (identical to the slug).
     */
    public function directory(): string
    {
        return $this->value;
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom($slug);
    }

    public static function default(): self
    {
        return self::Movies;
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
