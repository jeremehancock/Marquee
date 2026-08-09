<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\PlexClient;
use App\Plex\PlexPosterWriter;
use App\Plex\SignedImagePath;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\FakePlexPosterWriter;
use App\Tests\Support\MakesImages;
use Slim\App;

/**
 * Listing the posters Plex already holds for an item, and applying one.
 */
final class PlexPostersTabTest extends AppTestCase
{
    use MakesImages;

    private const POSTERS = <<<'XML'
        <MediaContainer>
          <Photo key="/library/metadata/10/file?url=upload%3A%2F%2Fposters%2Fold"
                 ratingKey="upload://posters/old"
                 thumb="/library/metadata/10/file?url=upload%3A%2F%2Fposters%2Fold" selected="0" />
          <Photo key="/library/metadata/10/file?url=upload%3A%2F%2Fposters%2Fnow"
                 ratingKey="upload://posters/now"
                 thumb="/library/metadata/10/file?url=upload%3A%2F%2Fposters%2Fnow" selected="1" />
          <Photo key="/library/metadata/10/file?url=metadata%3A%2F%2Fposters%2Fagent"
                 ratingKey="metadata://posters/tv.plex.agents.movie_agent"
                 thumb="/library/metadata/10/file?url=metadata%3A%2F%2Fposters%2Fagent"
                 selected="0" provider="tmdb" />
          <Photo key="https://image.tmdb.org/t/p/original/remote.jpg"
                 ratingKey="https://image.tmdb.org/t/p/original/remote.jpg"
                 thumb="https://images.plex.tv/photo?url=remote" selected="0" provider="tmdb" />
        </MediaContainer>
        XML;

    private string $postersDir;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
        $this->dataDir = $this->makeTempDir();

        file_put_contents($this->postersDir . '/movies/Solaris.jpg', $this->pngBytes(5, 5));
        // A second poster with no plex_items row, for the unlinked case.
        file_put_contents($this->postersDir . '/movies/Stray.jpg', $this->pngBytes(5, 5));

        (new PlexItemRepository(new Database($this->dataDir . '/marquee.sqlite')))->upsert(
            new PlexItemRecord('10', 'movie', 'movies', 'Movies', 'Solaris', 'Solaris.jpg', time(), '1', year: 1972)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
        $this->removeDir($this->dataDir);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function app(array $overrides = []): App
    {
        return $this->makeSignedInApp(
            ['POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            $overrides + [
                PlexClient::class => fn (): PlexClient => new FakePlexClient(
                    postersByKey: ['10' => self::POSTERS],
                ),
            ],
        );
    }

    /**
     * @param App<\Psr\Container\ContainerInterface|null> $app
     *
     * @return array{uploaded: list<array{token: string, thumb: string, selected: bool}>, server: list<array{token: string, thumb: string, selected: bool}>, error: string|null}
     */
    private function listFor(App $app, string $filename = 'Solaris.jpg'): array
    {
        $response = $this->get($app, '/library/movies/plex-posters?filename=' . rawurlencode($filename));
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);

        /** @var array{uploaded: list<array{token: string, thumb: string, selected: bool}>, server: list<array{token: string, thumb: string, selected: bool}>, error: string|null} $payload */
        return $payload;
    }

    public function testListsServerHeldPostersInTwoGroups(): void
    {
        $payload = $this->listFor($this->app());

        self::assertNull($payload['error']);
        self::assertCount(2, $payload['uploaded']);
        self::assertCount(1, $payload['server']);
    }

    public function testTheRemoteProviderPosterIsNotListed(): void
    {
        $payload = $this->listFor($this->app());
        $all = array_merge($payload['uploaded'], $payload['server']);

        self::assertCount(3, $all);
        foreach ($all as $candidate) {
            self::assertStringStartsWith('/plex-poster-image/', $candidate['thumb']);
        }
    }

    public function testTheSelectedPosterIsFlagged(): void
    {
        $payload = $this->listFor($this->app());

        self::assertSame([false, true], array_column($payload['uploaded'], 'selected'));
        self::assertSame([false], array_column($payload['server'], 'selected'));
    }

    /**
     * The grid receives opaque tokens. A Plex path in the payload would be a
     * path in the page, which is the thing the proxy exists to prevent.
     */
    public function testThePayloadCarriesNoPlexPath(): void
    {
        $response = $this->get($this->app(), '/library/movies/plex-posters?filename=Solaris.jpg');

        $body = (string) $response->getBody();
        self::assertStringNotContainsString('/library/metadata/', $body);
        self::assertStringNotContainsString('upload://', $body);
    }

    public function testAnUnlinkedPosterIsReportedDistinctly(): void
    {
        $payload = $this->listFor($this->app(), 'Stray.jpg');

        self::assertSame('This poster is not linked to a Plex item.', $payload['error']);
        self::assertSame([], $payload['uploaded']);
        self::assertSame([], $payload['server']);
    }

    public function testAnItemWithNoServerHeldPostersIsReportedDistinctly(): void
    {
        $app = $this->makeSignedInApp(
            ['POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient(
                postersByKey: ['10' => '<MediaContainer/>'],
            )],
        );

        self::assertSame('Plex has no posters of its own for this item.', $this->listFor($app)['error']);
    }

    public function testAnUnreachablePlexIsReportedAsRetryable(): void
    {
        $app = $this->makeSignedInApp(
            ['POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient(
                failingKeys: ['10'],
                postersByKey: ['10' => self::POSTERS],
            )],
        );

        self::assertSame(
            'Plex could not be reached. Trying again shortly may work.',
            $this->listFor($app)['error']
        );
    }

    public function testApplyingACandidateStoresItAndSelectsItInPlex(): void
    {
        $writer = new FakePlexPosterWriter();
        $app = $this->app([PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer]);

        $token = $this->listFor($app)['uploaded'][0]['token'];

        $response = $this->postForm($app, '/library/movies/change/plex-poster', [
            'filename' => 'Solaris.jpg',
            'token' => $token,
        ]);

        self::assertSame(302, $response->getStatusCode());
        // FakePlexClient::imageAt() answers with a generated PNG, so a stored
        // poster that differs from the 5x5 fixture is one that really was fetched.
        self::assertNotSame($this->pngBytes(5, 5), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));
        self::assertSame([['rating' => '10', 'poster' => 'upload://posters/old']], $writer->selected);
        self::assertSame(['10'], $writer->locked);
    }

    /**
     * The whole reason this tab selects rather than uploads. Plex never prunes
     * an item's posters, so an upload here would leave a second copy of a poster
     * the server already had — and applying the one already in use would
     * duplicate it against itself.
     */
    public function testApplyingACandidateNeverUploadsIt(): void
    {
        $writer = new FakePlexPosterWriter();
        $app = $this->app([PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer]);

        // The candidate Plex already has selected: the sharpest case.
        $inUse = $this->listFor($app)['uploaded'][1];
        self::assertTrue($inUse['selected']);

        $this->postForm($app, '/library/movies/change/plex-poster', [
            'filename' => 'Solaris.jpg',
            'token' => $inUse['token'],
        ]);

        self::assertSame([], $writer->uploaded);
        self::assertSame([['rating' => '10', 'poster' => 'upload://posters/now']], $writer->selected);
    }

    /**
     * The dialog can sit open for a long time. A signed path proves this
     * application minted it, never that Plex still has a poster there.
     */
    public function testApplyingAPosterPlexNoLongerHasFailsPlainly(): void
    {
        $writer = new FakePlexPosterWriter();
        $app = $this->app([PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer]);
        $token = $this->listFor($app)['uploaded'][0]['token'];

        // Same app, but Plex has since dropped every poster for the item.
        $gone = $this->makeSignedInApp(
            ['POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [
                PlexClient::class => static fn (): PlexClient => new FakePlexClient(
                    postersByKey: ['10' => '<MediaContainer/>'],
                ),
                PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer,
            ],
        );

        $this->postForm($gone, '/library/movies/change/plex-poster', [
            'filename' => 'Solaris.jpg',
            'token' => $token,
        ]);

        self::assertSame($this->pngBytes(5, 5), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));
        self::assertSame([], $writer->selected);
        self::assertSame([], $writer->uploaded);
    }

    /**
     * The apply endpoint resolves the token itself rather than trusting a path,
     * so a forged one changes nothing.
     */
    public function testApplyingRefusesATokenTheApplicationDidNotSign(): void
    {
        $writer = new FakePlexPosterWriter();
        $app = $this->app([PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer]);
        $forged = (new SignedImagePath('not-the-real-secret'))->sign('/library/metadata/10/file?url=upload%3A%2F%2Fx');

        $response = $this->postForm($app, '/library/movies/change/plex-poster', [
            'filename' => 'Solaris.jpg',
            'token' => $forged,
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->pngBytes(5, 5), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));
        self::assertSame([], $writer->uploaded);
    }
}
