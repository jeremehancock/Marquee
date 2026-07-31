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

    public function testAYearAlreadyInTheTitleMovesIntoParentheses(): void
    {
        // Import writes "(2003)" into a movie's filename and sanitising flattens
        // it to a bare token. It is stripped and re-added, so the year appears
        // once rather than twice.
        $poster = new Poster(PosterCategory::Movies, 'Louis_and_the_Nazis_2003_Movies.png', 1024, 42);

        self::assertSame('Louis and the Nazis (2003)', $poster->captionTitle('Movies', 2003));
    }

    public function testAKnownYearAbsentFromTheTitleIsAdded(): void
    {
        // Import records a year for shows but never writes one into their
        // filename, so there is nothing to strip and the year is simply appended.
        $poster = new Poster(PosterCategory::TvShows, 'Breaking_Bad_TV_Shows.png', 1024, 42);

        self::assertSame('Breaking Bad (2008)', $poster->captionTitle('TV Shows', 2008));
    }

    public function testASeasonGetsItsShowsYear(): void
    {
        $poster = new Poster(PosterCategory::TvSeasons, 'Breaking_Bad_-_Season_1_TV_Shows.png', 1024, 42);

        self::assertSame('Breaking Bad - Season 1 (2008)', $poster->captionTitle('TV Shows', 2008));
    }

    public function testDigitsThatAreNotTheKnownYearAreKept(): void
    {
        // A pattern match on trailing digits would eat the title itself here.
        $poster = new Poster(PosterCategory::TvShows, '1883_TV_Shows.png', 1024, 42);

        self::assertSame('1883 (2021)', $poster->captionTitle('TV Shows', 2021));
    }

    public function testDigitsInTheTitleSurviveAlongsideTheYear(): void
    {
        $poster = new Poster(PosterCategory::Movies, 'Blade_Runner_2049_2017_Movies.png', 1024, 42);

        self::assertSame('Blade Runner 2049 (2017)', $poster->captionTitle('Movies', 2017));
    }

    public function testATitleThatIsOnlyItsOwnYearIsNotStrippedToNothing(): void
    {
        // The leading space is part of the match, so the single token is a title,
        // not a year to move.
        $poster = new Poster(PosterCategory::Movies, '2003_Movies.png', 1024, 42);

        self::assertSame('2003 (2003)', $poster->captionTitle('Movies', 2003));
    }

    public function testNoKnownYearLeavesTheTitleAlone(): void
    {
        // Collections carry no year.
        $poster = new Poster(PosterCategory::Collections, 'Ace_Ventura_Movies.png', 1024, 42);

        self::assertSame('Ace Ventura', $poster->captionTitle('Movies'));
    }

    public function testAYearIsShownEvenWithoutAKnownLibrary(): void
    {
        $poster = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 42);

        self::assertSame('Solaris (1972)', $poster->captionTitle(null, 1972));
    }

    public function testAStaleFilenameYearIsNotMistakenForTheStoredOne(): void
    {
        // Plex corrected the year after import; the filename still says 2002. The
        // stored year is authoritative, and the disagreement stays visible rather
        // than being silently papered over.
        $poster = new Poster(PosterCategory::Movies, 'Some_Film_2002_Movies.png', 1024, 42);

        self::assertSame('Some Film 2002 (2003)', $poster->captionTitle('Movies', 2003));
    }
}
