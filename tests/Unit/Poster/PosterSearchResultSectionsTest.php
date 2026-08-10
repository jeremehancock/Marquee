<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Source\PosterCandidate;
use App\Poster\Source\PosterSearchResult;
use App\Poster\Source\PosterSection;
use PHPUnit\Framework\TestCase;

/**
 * Grouping the candidates for display, which is the one place the poster
 * source's cross-service ranking is given up — and the one place it must not be
 * disturbed any further than that.
 */
final class PosterSearchResultSectionsTest extends TestCase
{
    private function candidate(string $url, ?string $source): PosterCandidate
    {
        return new PosterCandidate(url: $url, source: $source);
    }

    /**
     * @param list<PosterCandidate> $candidates
     */
    private function sectionsFor(array $candidates): PosterSearchResult
    {
        return PosterSearchResult::found($candidates);
    }

    /**
     * @return list<string>
     */
    private function labels(PosterSearchResult $result): array
    {
        return array_map(static fn (PosterSection $s): string => $s->label, $result->sections());
    }

    public function testCandidatesAreSplitByTheServiceThatSuppliedThem(): void
    {
        $result = $this->sectionsFor([
            $this->candidate('https://img/tmdb-1.jpg', 'tmdb'),
            $this->candidate('https://img/fanart-1.jpg', 'fanart.tv'),
            $this->candidate('https://img/tmdb-2.jpg', 'tmdb'),
        ]);

        $sections = $result->sections();

        self::assertCount(2, $sections);
        self::assertSame('TMDB', $sections[0]->label);
        self::assertCount(2, $sections[0]->candidates);
        self::assertSame('fanart.tv', $sections[1]->label);
        self::assertCount(1, $sections[1]->candidates);
    }

    /**
     * The arrival order is deliberately the reverse of the section order, so a
     * result that merely echoed the source would fail this.
     */
    public function testSectionOrderIsFixedRegardlessOfArrivalOrder(): void
    {
        $result = $this->sectionsFor([
            $this->candidate('https://img/fanart.jpg', 'fanart.tv'),
            $this->candidate('https://img/tvdb.jpg', 'thetvdb'),
            $this->candidate('https://img/tmdb.jpg', 'tmdb'),
        ]);

        self::assertSame(['TMDB', 'TheTVDB', 'fanart.tv'], $this->labels($result));
    }

    /**
     * The source ranks across all three services at once and that order carries
     * real information, so whatever grouping costs, it must not cost this too.
     */
    public function testOrderWithinASectionIsTheOrderTheSourceReturned(): void
    {
        $result = $this->sectionsFor([
            $this->candidate('https://img/tmdb-first.jpg', 'tmdb'),
            $this->candidate('https://img/fanart.jpg', 'fanart.tv'),
            $this->candidate('https://img/tmdb-second.jpg', 'tmdb'),
            $this->candidate('https://img/tmdb-third.jpg', 'tmdb'),
        ]);

        $tmdb = $result->sections()[0];

        self::assertSame(
            ['https://img/tmdb-first.jpg', 'https://img/tmdb-second.jpg', 'https://img/tmdb-third.jpg'],
            array_map(static fn (PosterCandidate $c): string => $c->url, $tmdb->candidates),
        );
    }

    public function testAServiceThatSuppliedNothingLeavesNoSection(): void
    {
        $result = $this->sectionsFor([
            $this->candidate('https://img/tmdb.jpg', 'tmdb'),
        ]);

        self::assertSame(['TMDB'], $this->labels($result));
    }

    public function testNoCandidatesMeansNoSections(): void
    {
        self::assertSame([], (new PosterSearchResult(
            \App\Poster\Source\PosterSearchOutcome::NoArtwork,
        ))->sections());
    }

    /**
     * The failure this guards is invisible: a dropped candidate looks exactly
     * like the service having no artwork for the item.
     */
    public function testAnUnrecognisedServiceIsKeptInATrailingSection(): void
    {
        $result = $this->sectionsFor([
            $this->candidate('https://img/new.jpg', 'mediux'),
            $this->candidate('https://img/tmdb.jpg', 'tmdb'),
        ]);

        self::assertSame(['TMDB', PosterSection::OTHER], $this->labels($result));
        self::assertSame('https://img/new.jpg', $result->sections()[1]->candidates[0]->url);
    }

    public function testACandidateWithNoServiceIsKeptToo(): void
    {
        $result = $this->sectionsFor([
            $this->candidate('https://img/anonymous.jpg', null),
        ]);

        self::assertSame([PosterSection::OTHER], $this->labels($result));
    }

    public function testUnknownAndAbsentServicesShareOneSection(): void
    {
        $result = $this->sectionsFor([
            $this->candidate('https://img/anonymous.jpg', null),
            $this->candidate('https://img/new.jpg', 'mediux'),
        ]);

        self::assertCount(1, $result->sections());
        self::assertCount(2, $result->sections()[0]->candidates);
    }

    /**
     * The property that matters most: grouping is a rearrangement, never a
     * filter. Every candidate the source returned reaches a section.
     */
    public function testNoCandidateIsLost(): void
    {
        $candidates = [
            $this->candidate('https://img/a.jpg', 'fanart.tv'),
            $this->candidate('https://img/b.jpg', 'tmdb'),
            $this->candidate('https://img/c.jpg', null),
            $this->candidate('https://img/d.jpg', 'thetvdb'),
            $this->candidate('https://img/e.jpg', 'mediux'),
            $this->candidate('https://img/f.jpg', 'tmdb'),
        ];

        $result = $this->sectionsFor($candidates);

        $shown = [];
        foreach ($result->sections() as $section) {
            foreach ($section->candidates as $candidate) {
                $shown[] = $candidate->url;
            }
        }

        sort($shown);
        $expected = array_map(static fn (PosterCandidate $c): string => $c->url, $candidates);
        sort($expected);

        self::assertSame($expected, $shown);
    }
}
