<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Poster;
use App\Poster\PosterCategory;
use App\Poster\Search\PosterSearch;
use PHPUnit\Framework\TestCase;

final class PosterSearchTest extends TestCase
{
    private PosterSearch $search;

    protected function setUp(): void
    {
        $this->search = new PosterSearch();
    }

    /**
     * @param list<string> $filenames
     *
     * @return list<Poster>
     */
    private function posters(array $filenames): array
    {
        return array_map(
            static fn (string $name): Poster => new Poster(PosterCategory::Movies, $name, 100, 0),
            $filenames,
        );
    }

    /**
     * @param list<Poster> $result
     *
     * @return list<string>
     */
    private function titles(array $result): array
    {
        return array_map(static fn (Poster $p): string => $p->title(), $result);
    }

    public function testAllTermsMustMatch(): void
    {
        $posters = $this->posters(['Star Wars.jpg', 'Star Trek.jpg', 'Wars of Old.jpg']);

        $result = $this->titles($this->search->filter($posters, 'star wars'));

        self::assertSame(['Star Wars'], $result);
    }

    /**
     * The filename this poster actually reaches disk with. Import sanitises every
     * character outside A-Za-z0-9._- to an underscore and appends the source
     * library, so "Amélie" is stored as Am_lie_2001_Movies.png and its
     * filename-derived title is "Am lie 2001 Movies".
     *
     * This test used to build a poster literally named `Amélie.png`, which
     * plex-import cannot produce — posters enter the library only through it. So
     * the accent case passed against a shape the app never creates, while the
     * real one could not be found by its own name at all: the query folds to
     * "amelie", which is not a substring of "am lie".
     *
     * It is the recorded Plex title that makes it findable, which is why the map
     * is what this asserts on.
     */
    public function testAnAccentedTitleIsFoundByItsOwnName(): void
    {
        $posters = $this->posters(['Am_lie_2001_Movies.png', 'Other_Movies.png']);
        $titles = ['movies' => ['Am_lie_2001_Movies.png' => 'Amélie']];

        $result = $this->search->filter($posters, 'Amélie', $titles);

        self::assertCount(1, $result);
        self::assertSame('Am_lie_2001_Movies.png', $result[0]->filename);
    }

    public function testAccentAndCaseInsensitive(): void
    {
        $posters = $this->posters(['Am_lie_2001_Movies.png', 'Other_Movies.png']);
        $titles = ['movies' => ['Am_lie_2001_Movies.png' => 'Amélie']];

        $result = $this->search->filter($posters, 'amelie', $titles);

        self::assertCount(1, $result);
        self::assertSame('Am_lie_2001_Movies.png', $result[0]->filename);
    }

    /**
     * A poster with no Plex record — or one whose recorded title is empty — is
     * matched on its filename exactly as before. This is what keeps an orphan
     * findable.
     */
    public function testAPosterWithNoRecordedTitleFallsBackToItsFilename(): void
    {
        $posters = $this->posters(['Solaris.png', 'Dune.png']);

        self::assertSame(['Solaris'], $this->titles($this->search->filter($posters, 'solaris')));
        self::assertSame(
            ['Solaris'],
            $this->titles($this->search->filter($posters, 'solaris', ['movies' => ['Solaris.png' => '']])),
        );
    }

    /**
     * Import appends the source library to every filename. Matching the filename
     * therefore made the library name silently searchable, even though it appears
     * nowhere on the card. With the recorded title as the haystack it does not.
     */
    public function testTheSourceLibraryIsNotSearchable(): void
    {
        $posters = $this->posters(['Solaris_1972_Movies.png']);
        $titles = ['movies' => ['Solaris_1972_Movies.png' => 'Solaris']];

        self::assertSame([], $this->search->filter($posters, 'movies', $titles));
        self::assertCount(1, $this->search->filter($posters, 'solaris', $titles));
    }

    /**
     * Exactly one haystack per poster. Matching the filename as well would look
     * safer, but it is the accidental behaviour that would be preserved: a poster
     * matching for reasons the user cannot see on the card.
     */
    public function testTheFilenameIsNotAlsoMatchedWhenATitleIsRecorded(): void
    {
        $posters = $this->posters(['Old_Working_Title.png']);
        $titles = ['movies' => ['Old_Working_Title.png' => 'Stalker']];

        self::assertSame([], $this->search->filter($posters, 'working', $titles));
        self::assertCount(1, $this->search->filter($posters, 'stalker', $titles));
    }

    /**
     * Filenames are unique only within a category and the All view merges all
     * four, so the map is keyed by both. A title recorded under one category must
     * not decide the haystack for a same-named file in another.
     */
    public function testTheMapIsKeyedByCategoryAsWellAsFilename(): void
    {
        $posters = [
            new Poster(PosterCategory::Movies, 'Dune.png', 100, 0),
            new Poster(PosterCategory::Collections, 'Dune.png', 100, 0),
        ];
        $titles = ['movies' => ['Dune.png' => 'Arrakis']];

        $arrakis = $this->search->filter($posters, 'arrakis', $titles);
        self::assertCount(1, $arrakis);
        self::assertSame(PosterCategory::Movies, $arrakis[0]->category);

        // The collections poster keeps its filename-derived title.
        $dune = $this->search->filter($posters, 'dune', $titles);
        self::assertCount(1, $dune);
        self::assertSame(PosterCategory::Collections, $dune[0]->category);
    }

    public function testNoMatchReturnsEmpty(): void
    {
        $posters = $this->posters(['Dune.jpg', 'Alien.jpg']);

        self::assertSame([], $this->search->filter($posters, 'matrix'));
    }

    /**
     * A term counts wherever it appears. Ranking a title that begins with the
     * query above one that merely contains it is what used to strand a poster
     * below the sort the user had actually asked for.
     */
    public function testWhereATermMatchesCarriesNoWeight(): void
    {
        $posters = $this->posters(['The Dark Knight.jpg', 'Knight Rider.jpg']);

        $result = $this->titles($this->search->filter($posters, 'knight'));

        self::assertCount(2, $result);
        self::assertContains('Knight Rider', $result);
        self::assertContains('The Dark Knight', $result);
    }

    /**
     * Filtering leaves the incoming order alone: whatever the caller hands over
     * comes back in the same sequence, minus what did not match. The gallery
     * sorts afterwards, so a reordering here would be silently overwritten in
     * production and only ever mislead a reader.
     */
    public function testMatchesComeBackInTheOrderTheyWereGiven(): void
    {
        $posters = $this->posters([
            'Alien Covenant.jpg',
            'Dune.jpg',
            'Aliens.jpg',
            'Alien.jpg',
        ]);

        $result = $this->titles($this->search->filter($posters, 'alien'));

        self::assertSame(['Alien Covenant', 'Aliens', 'Alien'], $result);
    }

    public function testEmptyQueryReturnsEverythingUntouched(): void
    {
        $posters = $this->posters(['Zodiac.jpg', 'Alien.jpg']);

        self::assertSame(['Zodiac', 'Alien'], $this->titles($this->search->filter($posters, '   ')));
    }

    /**
     * Punctuation and separators are flattened on both sides, so a query need not
     * reproduce the filename's own punctuation to match it.
     */
    public function testPunctuationIsFlattenedOnBothSides(): void
    {
        $posters = $this->posters(['Spider-Man - No Way Home.jpg', 'Dune.jpg']);

        $result = $this->titles($this->search->filter($posters, 'spider man'));

        self::assertSame(['Spider-Man - No Way Home'], $result);
    }
}
