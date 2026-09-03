<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Tests\AppTestCase;
use App\Tests\Support\MakesImages;
use Slim\App;

/**
 * What a card falls back to when a fact was never recorded.
 *
 * Each of these fallbacks used to be expressed by a read leaving the row out of
 * its map: no title meant no key, and the template's `?? null` did the rest. The
 * combined read returns every row, so each one is now a null on the value object
 * instead. The behaviour is meant to be identical, and identical is only worth
 * anything if both directions are checked — a fact present and the same fact
 * absent, side by side.
 */
final class GalleryFactFallbackTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
    }

    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function app(): App
    {
        return $this->makeSignedInApp(['POSTERS_DIR' => $this->postersDir]);
    }

    /**
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    private function record(App $app, PlexItemRecord $record): void
    {
        $container = $app->getContainer();
        self::assertNotNull($container);
        /** @var PlexItemRepository $items */
        $items = $container->get(PlexItemRepository::class);
        $items->upsert($record);
    }

    public function testARecordedTitleAndYearAreShown(): void
    {
        $this->writePng($this->postersDir . '/movies/Am_lie_Movies.png');
        $app = $this->app();
        $this->record($app, new PlexItemRecord(
            '1',
            'movie',
            'movies',
            'Movies',
            'Amélie',
            'Am_lie_Movies.png',
            time(),
            year: 2001,
        ));

        $body = (string) $this->get($app, '/library/movies')->getBody();

        self::assertStringContainsString('Amélie (2001)', $body);
    }

    /**
     * The other direction. The filename is a lossy copy of the title, and with
     * nothing recorded it is all there is — so the caption reads from it and no
     * year is invented.
     */
    public function testAPosterWithNoRecordIsCaptionedFromItsFilename(): void
    {
        // A filename no other test in this class records. The mapping database
        // is shared for the whole run, so a row written elsewhere would answer
        // for this poster and the fallback would never be exercised.
        $this->writePng($this->postersDir . '/movies/Unmapped_Movies.png');
        $app = $this->app();

        $body = (string) $this->get($app, '/library/movies')->getBody();

        self::assertStringContainsString('Unmapped Movies', $body);
        self::assertStringNotContainsString('Unmapped Movies (', $body);
    }

    /**
     * A row can exist and still say nothing useful. An empty recorded title must
     * fall back exactly as a missing row does, rather than captioning the card
     * with a blank.
     */
    public function testARecordWithAnEmptyTitleStillFallsBackToTheFilename(): void
    {
        $this->writePng($this->postersDir . '/movies/Blank_Movies.png');
        $app = $this->app();
        $this->record($app, new PlexItemRecord(
            '1',
            'movie',
            'movies',
            'Movies',
            '',
            'Blank_Movies.png',
            time(),
        ));

        $body = (string) $this->get($app, '/library/movies')->getBody();

        self::assertStringContainsString('Blank Movies', $body);
    }

    public function testARecordWithNoYearIsCaptionedWithoutOne(): void
    {
        $this->writePng($this->postersDir . '/movies/Yearless_Movies.png');
        $app = $this->app();
        $this->record($app, new PlexItemRecord(
            '1',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Yearless_Movies.png',
            time(),
        ));

        $body = (string) $this->get($app, '/library/movies')->getBody();

        self::assertStringContainsString('Solaris', $body);
        self::assertStringNotContainsString('Solaris (', $body);
    }

    /**
     * A mapped poster offers the Plex actions; the mapping is what says so.
     *
     * There is no "and the other direction" here, and that is worth saying: the
     * gallery cannot be reached at all while Plex is unconfigured — the
     * connection gate redirects first — so the template's own configured check
     * is defensive and unobservable. It is kept because it costs nothing and
     * states the intent, not because a test could catch its removal.
     */
    public function testAMappedPosterOffersThePlexActions(): void
    {
        $this->writePng($this->postersDir . '/movies/Blank_Movies.png');
        $app = $this->app();
        $this->record($app, new PlexItemRecord(
            '1',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Blank_Movies.png',
            time(),
        ));

        $body = (string) $this->get($app, '/library/movies')->getBody();

        self::assertStringContainsString('Send to Plex', $body);
    }

}
