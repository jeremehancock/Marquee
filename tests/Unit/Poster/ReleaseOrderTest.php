<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Config\PosterConfig;
use App\Poster\Poster;
use App\Poster\PosterCategory;
use App\Poster\PosterFacts;
use App\Poster\PosterFactsIndex;
use App\Poster\SortComparator;
use App\Poster\SortOrder;
use PHPUnit\Framework\TestCase;

/**
 * Release order: the year the work came out, then the season within it.
 *
 * The reason this field exists is that A–Z is wrong for a set: a show sorts
 * after its own seasons, and a series runs out of order wherever its titles do
 * not agree with its release dates. Both are asserted at the foot of this class
 * against filenames as import STORES them, rather than against tidy invented
 * titles — the season symptom does not appear at all without the library name
 * import appends, so convenient data would have said it was imaginary.
 *
 * Every rule is checked in both directions — a year present and a year absent, a
 * season number present and absent, ascending and descending — because the last
 * change to sets was undone three times by designs that looked right against
 * data chosen to suit them.
 */
final class ReleaseOrderTest extends TestCase
{
    /**
     * @param array<string, array{0: ?int, 1: ?int}> $recorded   filename => [year, season]
     * @param list<string>                           $filenames
     * @param array<string, PosterCategory>          $categories filename => category,
     *        for the few cases where the category is what breaks the tie
     *
     * @return list<string>
     */
    private function order(
        array $filenames,
        array $recorded,
        SortOrder $sort,
        array $categories = [],
    ): array {
        $posters = [];
        $facts = [];
        foreach ($filenames as $filename) {
            $category = $categories[$filename] ?? PosterCategory::Movies;
            $posters[] = new Poster($category, $filename, 1, 0);
            [$year, $season] = $recorded[$filename] ?? [null, null];
            $facts[$category->value][$filename] = PosterFacts::fromRecorded('', $year, $season, '', [], 0);
        }

        $config = new PosterConfig(24, 1, ['png'], true, SortOrder::Alphabetical);
        usort(
            $posters,
            (new SortComparator($config))->forOrder($sort, new PosterFactsIndex($facts)),
        );

        return array_map(static fn (Poster $p): string => $p->title(), $posters);
    }

    public function testATrilogyReadsInTheOrderItWasReleased(): void
    {
        $order = $this->order(
            ['The Matrix Reloaded.png', 'The Matrix.png', 'The Matrix Revolutions.png'],
            [
                'The Matrix.png' => [1999, null],
                'The Matrix Reloaded.png' => [2003, null],
                'The Matrix Revolutions.png' => [2003, null],
            ],
            SortOrder::Release,
        );

        self::assertSame('The Matrix', $order[0]);
        // The two 2003 films tie on the year and fall to the title.
        self::assertSame(['The Matrix Reloaded', 'The Matrix Revolutions'], array_slice($order, 1));
    }

    public function testReversingPutsTheLatestFirst(): void
    {
        $order = $this->order(
            ['The Matrix Reloaded.png', 'The Matrix.png'],
            ['The Matrix.png' => [1999, null], 'The Matrix Reloaded.png' => [2003, null]],
            SortOrder::ReleaseDesc,
        );

        self::assertSame(['The Matrix Reloaded', 'The Matrix'], $order);
    }

    /**
     * A season records its SHOW's year — Plex reports none on a season node — so
     * a show and every season of it tie on the year, and the show is the only
     * one of them with no season number. That is what puts it first, with no
     * special case for it.
     */
    public function testAShowPrecedesItsSeasonsAndTheSeasonsRunInOrder(): void
    {
        $order = $this->order(
            ['Breaking Bad - Season 2.png', 'Breaking Bad.png', 'Breaking Bad - Season 1.png'],
            [
                'Breaking Bad.png' => [2008, null],
                'Breaking Bad - Season 1.png' => [2008, 1],
                'Breaking Bad - Season 2.png' => [2008, 2],
            ],
            SortOrder::Release,
        );

        self::assertSame(
            ['Breaking Bad', 'Breaking Bad - Season 1', 'Breaking Bad - Season 2'],
            $order,
        );
    }

    /**
     * The other direction, and the point of the rule that tie-breaks always run
     * forwards: reversing the field must not scramble a show's seasons.
     */
    public function testReversingLeavesAShowAndItsSeasonsInTheSameOrder(): void
    {
        $filenames = ['Breaking Bad - Season 2.png', 'Breaking Bad.png', 'Breaking Bad - Season 1.png'];
        $recorded = [
            'Breaking Bad.png' => [2008, null],
            'Breaking Bad - Season 1.png' => [2008, 1],
            'Breaking Bad - Season 2.png' => [2008, 2],
        ];

        self::assertSame(
            $this->order($filenames, $recorded, SortOrder::Release),
            $this->order($filenames, $recorded, SortOrder::ReleaseDesc),
        );
    }

    /**
     * Unknown first, and this is the rule the design leans on: a collection Plex
     * reports no year for leads the films it holds, which is how a set should
     * read. It is chosen because it is also correct when Plex DOES report one —
     * the collection then sorts among its earliest films — whereas unknown-last
     * is right only in that second case.
     */
    public function testAnUnknownYearSortsBeforeEveryKnownOne(): void
    {
        $order = $this->order(
            ['Godzilla.png', 'MonsterVerse.png', 'Kong.png'],
            [
                'Godzilla.png' => [2014, null],
                'Kong.png' => [2017, null],
                'MonsterVerse.png' => [null, null],
            ],
            SortOrder::Release,
        );

        self::assertSame(['MonsterVerse', 'Godzilla', 'Kong'], $order);
    }

    /**
     * A year of zero is a year. Treating "no year" as zero would make a poster
     * recording one indistinguishable from a poster recording none, which is why
     * the comparison uses a sentinel below every real year rather than zero.
     */
    public function testAYearOfZeroIsNotMistakenForAnUnknownYear(): void
    {
        $order = $this->order(
            ['Zero.png', 'Unknown.png'],
            ['Zero.png' => [0, null], 'Unknown.png' => [null, null]],
            SortOrder::Release,
        );

        self::assertSame(['Unknown', 'Zero'], $order);
    }

    public function testAPosterWithNothingRecordedIsStillOrdered(): void
    {
        $order = $this->order(
            ['Unmapped.png', 'Dune.png'],
            ['Dune.png' => [2021, null]],
            SortOrder::Release,
        );

        self::assertCount(2, $order);
        self::assertSame(['Unmapped', 'Dune'], $order);
    }

    /**
     * Season number only breaks a tie in the year; it never outranks it. Two
     * unrelated works must not be interleaved by their season numbers.
     */
    public function testSeasonNumberNeverOutranksTheYear(): void
    {
        $order = $this->order(
            ['Newer - Season 1.png', 'Older - Season 9.png'],
            ['Older - Season 9.png' => [1999, 9], 'Newer - Season 1.png' => [2020, 1]],
            SortOrder::Release,
        );

        self::assertSame(['Older - Season 9', 'Newer - Season 1'], $order);
    }

    /**
     * Nothing about the other fields moves. Release is an addition, not a
     * replacement.
     */
    public function testAlphabeticalOrderIsUnchanged(): void
    {
        $order = $this->order(
            ['The Matrix Reloaded.png', 'The Matrix.png'],
            ['The Matrix.png' => [1999, null], 'The Matrix Reloaded.png' => [2003, null]],
            SortOrder::Alphabetical,
        );

        self::assertSame(['The Matrix', 'The Matrix Reloaded'], $order);
    }

    /**
     * The two things A–Z does to a set, pinned so the reason this field exists
     * is a fact about the code rather than a claim in a proposal.
     *
     * The season case only appears with filenames as import STORES them, which
     * carry the library name: "Breaking Bad - Season 1 TV" against "Breaking Bad
     * TV", where "-" sorts below "T". Given tidy titles it does not happen at
     * all — which is exactly the way a test built on convenient data would have
     * declared the symptom imaginary.
     */
    public function testAlphabeticalPutsAShowAfterItsOwnSeasons(): void
    {
        $order = $this->order(
            [
                'Breaking_Bad_TV.jpg',
                'Breaking_Bad_-_Season_1_TV.jpg',
                'Breaking_Bad_-_Season_2_TV.jpg',
            ],
            [],
            SortOrder::Alphabetical,
        );

        self::assertSame('Breaking Bad TV', $order[2], 'the show sorts last under A–Z');
    }

    /**
     * And a series runs out of order wherever its titles do not agree with its
     * release dates: Resurrections is 2021 and Revolutions is 2003, so A–Z lands
     * the fourth film third. Release order is what puts it right — asserted here
     * side by side so the difference is the test rather than the comment.
     */
    public function testReleaseOrderCorrectsWhatAlphabeticalGetsWrong(): void
    {
        $filenames = [
            'The_Matrix_1999_Movies.jpg',
            'The_Matrix_Reloaded_2003_Movies.jpg',
            'The_Matrix_Revolutions_2003_Movies.jpg',
            'The_Matrix_Resurrections_2021_Movies.jpg',
        ];
        $recorded = [
            'The_Matrix_1999_Movies.jpg' => [1999, null],
            'The_Matrix_Reloaded_2003_Movies.jpg' => [2003, null],
            'The_Matrix_Revolutions_2003_Movies.jpg' => [2003, null],
            'The_Matrix_Resurrections_2021_Movies.jpg' => [2021, null],
        ];

        $alphabetical = $this->order($filenames, $recorded, SortOrder::Alphabetical);
        self::assertStringContainsString('Resurrections', $alphabetical[2]);
        self::assertStringContainsString('Revolutions', $alphabetical[3]);

        $release = $this->order($filenames, $recorded, SortOrder::Release);
        self::assertStringContainsString('Revolutions', $release[2]);
        self::assertStringContainsString('Resurrections', $release[3]);
    }

    /**
     * The limit of "a collection leads the films it holds", pinned because it is
     * the one case where that sentence does not hold.
     *
     * It holds by virtue of the collection having no year while its films do. A
     * film with NO year recorded ties with the collection, and the tie falls to
     * category order — Movies before Collections — so that film leads instead.
     *
     * Deterministic and derivable from the stated rules rather than a surprise,
     * and left alone deliberately: reordering the categories for this one field
     * would be exactly the sort-specific special case the design set out to
     * avoid.
     */
    public function testAYearlessFilmTiesWithAYearlessCollectionAndCategoryDecides(): void
    {
        $order = $this->order(
            ['MonsterVerse.png', 'Undated Film.png', 'Godzilla.png'],
            [
                'MonsterVerse.png' => [null, null],
                'Undated Film.png' => [null, null],
                'Godzilla.png' => [2014, null],
            ],
            SortOrder::Release,
            [
                'MonsterVerse.png' => PosterCategory::Collections,
                'Undated Film.png' => PosterCategory::Movies,
                'Godzilla.png' => PosterCategory::Movies,
            ],
        );

        self::assertSame(['Undated Film', 'MonsterVerse', 'Godzilla'], $order);
    }

    /**
     * And the case the design actually leans on, stated with real categories:
     * a collection Plex reports no year for leads the films it holds.
     */
    public function testACollectionWithNoYearLeadsTheFilmsItHolds(): void
    {
        $order = $this->order(
            ['Kong.png', 'MonsterVerse.png', 'Godzilla.png'],
            [
                'MonsterVerse.png' => [null, null],
                'Godzilla.png' => [2014, null],
                'Kong.png' => [2017, null],
            ],
            SortOrder::Release,
            ['MonsterVerse.png' => PosterCategory::Collections],
        );

        self::assertSame(['MonsterVerse', 'Godzilla', 'Kong'], $order);
    }
}
