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

    public function testCaptionPrefersTheRecordedTitleOverTheFilename(): void
    {
        // The filename carries the library and a flattened year; the recorded
        // title carries neither, so none of that has to be undone.
        $poster = new Poster(PosterCategory::Movies, 'Louis_and_the_Nazis_2003_Movies.png', 1024, 42);

        self::assertSame('Louis and the Nazis (2003)', $poster->captionTitle('Louis and the Nazis', 2003));
    }

    public function testCaptionRestoresPunctuationTheFilenameLost(): void
    {
        // Sanitising replaces every run of non-alphanumerics with "_", so the
        // filename-derived title reads "Marvel s Agents of S H I E L D".
        $poster = new Poster(PosterCategory::TvShows, 'Marvel_s_Agents_of_S.H.I.E.L.D._TV_Shows.png', 1024, 42);

        self::assertSame(
            "Marvel's Agents of S.H.I.E.L.D. (2013)",
            $poster->captionTitle("Marvel's Agents of S.H.I.E.L.D.", 2013),
        );
    }

    public function testAKnownYearIsAppended(): void
    {
        $poster = new Poster(PosterCategory::TvShows, 'Breaking_Bad_TV_Shows.png', 1024, 42);

        self::assertSame('Breaking Bad (2008)', $poster->captionTitle('Breaking Bad', 2008));
    }

    public function testASeasonGetsItsShowsYear(): void
    {
        $poster = new Poster(PosterCategory::TvSeasons, 'Breaking_Bad_-_Season_1_TV_Shows.png', 1024, 42);

        self::assertSame(
            'Breaking Bad - Season 1 (2008)',
            $poster->captionTitle('Breaking Bad - Season 1', 2008),
        );
    }

    public function testAYearAlreadyInTheTitleIsNotRepeated(): void
    {
        // Plex names the show "Lucky (2026)". Appending the year again would read
        // "Lucky (2026) (2026)".
        $poster = new Poster(PosterCategory::TvShows, 'Lucky_2026_TV_Shows.png', 1024, 42);

        self::assertSame('Lucky (2026)', $poster->captionTitle('Lucky (2026)', 2026));
    }

    /**
     * The case that sent this back from :dev. A season's recorded title is built
     * from its show's, so it inherits the parenthesised year mid-string — where a
     * trailing-token rule could never see it.
     */
    public function testASeasonOfAYearNamedShowNamesTheYearOnce(): void
    {
        $poster = new Poster(PosterCategory::TvSeasons, 'Lucky_2026_-_Season_1_TV_Shows.png', 1024, 42);

        self::assertSame(
            'Lucky (2026) - Season 1',
            $poster->captionTitle('Lucky (2026) - Season 1', 2026),
        );
    }

    public function testBareDigitsAreNotReadAsAYearAlreadyPresent(): void
    {
        // "Class of 2026" airing in 2026: the digits are part of the name, so the
        // release year is still appended. Matching bare digits would drop it.
        $poster = new Poster(PosterCategory::Movies, 'Class_of_2026_Movies.png', 1024, 42);

        self::assertSame('Class of 2026 (2026)', $poster->captionTitle('Class of 2026', 2026));
    }

    public function testDigitsInTheTitleSurviveAlongsideTheYear(): void
    {
        $poster = new Poster(PosterCategory::Movies, 'Blade_Runner_2049_2017_Movies.png', 1024, 42);

        self::assertSame('Blade Runner 2049 (2017)', $poster->captionTitle('Blade Runner 2049', 2017));
    }

    public function testAShortNumericTitleKeepsItsDigitsAndGainsItsYear(): void
    {
        $poster = new Poster(PosterCategory::TvShows, '1883_TV_Shows.png', 1024, 42);

        self::assertSame('1883 (2021)', $poster->captionTitle('1883', 2021));
    }

    public function testNoKnownYearLeavesTheTitleAlone(): void
    {
        // Collections carry no year.
        $poster = new Poster(PosterCategory::Collections, 'Ace_Ventura_Movies.png', 1024, 42);

        self::assertSame('Ace Ventura', $poster->captionTitle('Ace Ventura'));
    }

    public function testNoRecordedTitleFallsBackToTheFilename(): void
    {
        // A poster with no Plex mapping — uploaded, or placed by hand. It renders
        // exactly as it did before the caption started reading from the database.
        $poster = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 42);

        self::assertSame('Solaris', $poster->captionTitle());
    }

    public function testAnEmptyRecordedTitleFallsBackRatherThanBlanking(): void
    {
        $poster = new Poster(PosterCategory::Movies, 'Solaris.png', 1024, 42);

        self::assertSame('Solaris (1972)', $poster->captionTitle('', 1972));
    }
}
