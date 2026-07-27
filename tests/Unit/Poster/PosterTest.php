<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Poster;
use App\Poster\PosterCategory;
use PHPUnit\Framework\TestCase;

final class PosterTest extends TestCase
{
    public function testUrlCarriesTheModificationTime(): void
    {
        $poster = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 1753280400);

        self::assertSame('/posters/movies/Solaris.png?v=1753280400', $poster->url());
    }

    public function testReplacingTheFileChangesTheUrl(): void
    {
        // Same poster, different mtime: the browser must treat it as a new
        // resource, or a changed poster keeps showing the cached image.
        $before = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 1753280400);
        $after = new Poster(PosterCategory::Movies, 'Solaris.png', 2048, 1753366800);

        self::assertNotSame($before->url(), $after->url());
    }

    public function testUnchangedPosterKeepsTheSameUrl(): void
    {
        $first = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 1753280400);
        $second = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 1753280400);

        self::assertSame($first->url(), $second->url());
    }

    public function testUrlOmitsTheParameterWhenTheTimeIsUnknown(): void
    {
        // filemtime() failures surface as 0; ?v=0 would be a constant that
        // never busts anything, so it is left off entirely.
        $poster = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 0);

        self::assertSame('/posters/movies/Solaris.png', $poster->url());
    }

    public function testUrlEncodesTheFilename(): void
    {
        $poster = new Poster(PosterCategory::TvShows, 'The Wire.png', 1024, 42);

        self::assertSame('/posters/tv-shows/The%20Wire.png?v=42', $poster->url());
    }

    public function testCaptionTitleDropsTheTrailingLibraryToken(): void
    {
        // Import names files "Title (Year) [Library]", which reads as a redundant
        // type suffix under the poster; the caption drops it.
        $poster = new Poster(PosterCategory::Movies, 'Solaris (1972) [Movies].png', 1024, 42);

        self::assertSame('Solaris (1972)', $poster->captionTitle());
    }

    public function testCaptionTitleLeavesTitlesWithoutABracketUnchanged(): void
    {
        $poster = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 42);

        self::assertSame('Solaris', $poster->captionTitle());
    }

    public function testCaptionTitleOnlyTrimsABracketAtTheEnd(): void
    {
        // A bracket that is part of the name, not a trailing token, is kept.
        $poster = new Poster(PosterCategory::Movies, 'Fear [and] Loathing.png', 1024, 42);

        self::assertSame('Fear [and] Loathing', $poster->captionTitle());
    }
}
