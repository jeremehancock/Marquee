<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\PlexClient;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;

final class OrphanTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
        $this->dataDir = $this->makeTempDir();

        $repo = new PlexItemRepository(new Database($this->dataDir . '/marquee.sqlite'));
        foreach (['Solaris.jpg' => '10', 'Gone.jpg' => '99'] as $filename => $ratingKey) {
            $this->writePng($this->postersDir . '/movies/' . $filename);
            $repo->upsert(new PlexItemRecord($ratingKey, 'movie', 'movies', 'Movies', $filename, $filename, time(), '1'));
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
        $this->removeDir($this->dataDir);
    }

    /**
     * Plex still has "10" but not "99".
     *
     * @return \Slim\App<\Psr\Container\ContainerInterface|null>
     */
    private function app(): \Slim\App
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $stillThere = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $fake = new FakePlexClient([$library], ['1' => [$stillThere]]);

        return $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => $fake],
        );
    }

    /**
     * The shell renders instantly with the spinner up and does NOT run the scan;
     * the orphan listing arrives separately from GET /orphans/list.
     */
    public function testOrphansShellRendersSpinnerWithoutScanning(): void
    {
        $body = (string) $this->get($this->app(), '/orphans')->getBody();

        self::assertStringContainsString('orphansPage(true)', $body);
        self::assertStringContainsString('Checking Plex for orphans', $body);
        self::assertStringNotContainsString('Gone', $body);
        self::assertStringNotContainsString('Solaris', $body);
    }

    public function testOrphansListReturnsOnlyOrphans(): void
    {
        $body = (string) $this->get($this->app(), '/orphans/list')->getBody();

        self::assertStringContainsString('Gone', $body);
        self::assertStringNotContainsString('Solaris', $body);
        self::assertStringContainsString('Delete all orphans', $body);
    }

    public function testOrphansPageExplainsWhatDeletionRemoves(): void
    {
        $body = (string) $this->get($this->app(), '/orphans')->getBody();

        self::assertStringContainsString('imported from Plex whose media no longer exists', $body);
        self::assertStringContainsString('removes its poster file and its link to Plex', $body);
    }

    public function testOrphansPageClaimsNoExemption(): void
    {
        $body = (string) $this->get($this->app(), '/orphans')->getBody();

        self::assertStringNotContainsString('uploaded yourself', $body);
        self::assertStringNotContainsString('never treated as orphans', $body);
    }

    public function testOrphansPageWithoutPlexShowsMessageAndNoSpinner(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $unconfigured = new FakePlexClient([$library], [], [], [], [], false);
        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => $unconfigured],
        );

        $body = (string) $this->get($app, '/orphans')->getBody();

        self::assertStringContainsString('Plex must be configured', $body);
        self::assertStringContainsString('orphansPage(false)', $body);
        self::assertStringNotContainsString('Checking Plex for orphans', $body);
    }

    /**
     * The shared fade-in script reveals posters by finding `.card__image`
     * inside a `.card__frame`; without that markup an orphan renders as a
     * permanently transparent image behind a shimmer that never resolves.
     */
    public function testOrphanUsesSharedCardMarkup(): void
    {
        $body = (string) $this->get($this->app(), '/orphans/list')->getBody();

        self::assertMatchesRegularExpression(
            '/class="card__frame">\s*<img class="card__image"/',
            $body,
        );
    }

    public function testDeleteAllRemovesOrphanFiles(): void
    {
        $response = $this->postForm($this->app(), '/orphans/delete-all', []);

        self::assertSame(302, $response->getStatusCode());
        self::assertFileDoesNotExist($this->postersDir . '/movies/Gone.jpg');
        self::assertFileExists($this->postersDir . '/movies/Solaris.jpg');
    }

    public function testOrphanListOffersPerOrphanDownloadAndDelete(): void
    {
        $body = (string) $this->get($this->app(), '/orphans/list')->getBody();

        self::assertStringContainsString('href="/posters/movies/Gone.jpg" download', $body);
        self::assertStringContainsString('action="/orphans/delete"', $body);
        self::assertStringContainsString('>Delete</button>', $body);
    }

    public function testDeleteRemovesASingleOrphan(): void
    {
        $response = $this->postForm($this->app(), '/orphans/delete', [
            'category' => 'movies',
            'filename' => 'Gone.jpg',
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertFileDoesNotExist($this->postersDir . '/movies/Gone.jpg');
        self::assertFileExists($this->postersDir . '/movies/Solaris.jpg');
    }

    public function testDeleteLeavesNonOrphansUntouched(): void
    {
        // "Solaris" still exists in Plex, so it is not an orphan; the request
        // must be a no-op that leaves the poster in place.
        $response = $this->postForm($this->app(), '/orphans/delete', [
            'category' => 'movies',
            'filename' => 'Solaris.jpg',
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertFileExists($this->postersDir . '/movies/Solaris.jpg');
    }

    public function testDeleteWithUnknownCategoryIs404(): void
    {
        $response = $this->postForm($this->app(), '/orphans/delete', [
            'category' => 'not-a-category',
            'filename' => 'Gone.jpg',
        ]);

        self::assertSame(404, $response->getStatusCode());
        self::assertFileExists($this->postersDir . '/movies/Gone.jpg');
    }
}
