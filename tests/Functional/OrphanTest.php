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

    public function testOrphansPageListsOnlyOrphans(): void
    {
        $body = (string) $this->get($this->app(), '/orphans')->getBody();

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

    /**
     * The shared fade-in script reveals posters by finding `.card__image`
     * inside a `.card__frame`; without that markup an orphan renders as a
     * permanently transparent image behind a shimmer that never resolves.
     */
    public function testOrphanUsesSharedCardMarkup(): void
    {
        $body = (string) $this->get($this->app(), '/orphans')->getBody();

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
}
