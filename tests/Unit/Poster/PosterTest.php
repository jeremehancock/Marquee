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

    public function testCaptionDropsTheTrailingLibraryToken(): void
    {
        // Import bakes the library into the filename ("…2003 Movies"); given the
        // library name, the caption drops it.
        $poster = new Poster(PosterCategory::Movies, 'Louis_and_the_Nazis_2003_Movies.png', 1024, 42);

        self::assertSame('Louis and the Nazis 2003', $poster->captionTitle('Movies'));
    }

    public function testCaptionDropsAMultiWordLibraryToken(): void
    {
        $poster = new Poster(PosterCategory::TvShows, 'Breaking_Bad_TV_Shows.png', 1024, 42);

        self::assertSame('Breaking Bad', $poster->captionTitle('TV Shows'));
    }

    public function testCaptionKeepsTheFullTitleWhenNoLibraryIsGiven(): void
    {
        // Non-Plex posters (uploaded/URL) have no library to strip.
        $poster = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 42);

        self::assertSame('Solaris', $poster->captionTitle());
    }

    public function testCaptionKeepsTheTitleWhenTheLibraryIsNotTheTrailingToken(): void
    {
        // "Movies" appears in the title but not at the end, so nothing is trimmed.
        $poster = new Poster(PosterCategory::Movies, 'The_Movies_Are_Great.png', 1024, 42);

        self::assertSame('The Movies Are Great', $poster->captionTitle('Movies'));
    }

    public function testSheetTitleParenthesisesTheLibrary(): void
    {
        $poster = new Poster(PosterCategory::Movies, 'Louis_and_the_Nazis_2003_Movies.png', 1024, 42);

        self::assertSame('Louis and the Nazis 2003 (Movies)', $poster->sheetTitle('Movies'));
    }

    public function testSheetTitleKeepsTheFullTitleWhenNoLibraryIsGiven(): void
    {
        $poster = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 42);

        self::assertSame('Solaris', $poster->sheetTitle());
    }
}
