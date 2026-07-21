<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\PosterCategory;
use PHPUnit\Framework\TestCase;

final class PosterCategoryTest extends TestCase
{
    public function testKnownSlugsResolve(): void
    {
        self::assertSame(PosterCategory::Movies, PosterCategory::fromSlug('movies'));
        self::assertSame(PosterCategory::TvSeasons, PosterCategory::fromSlug('tv-seasons'));
    }

    public function testUnknownSlugReturnsNull(): void
    {
        self::assertNull(PosterCategory::fromSlug('books'));
        self::assertNull(PosterCategory::fromSlug(''));
    }

    public function testLabelsAndDirectories(): void
    {
        self::assertSame('TV Shows', PosterCategory::TvShows->label());
        self::assertSame('tv-shows', PosterCategory::TvShows->directory());
    }

    public function testAllReturnsFourCategories(): void
    {
        self::assertCount(4, PosterCategory::all());
        self::assertSame(PosterCategory::Movies, PosterCategory::default());
    }
}
