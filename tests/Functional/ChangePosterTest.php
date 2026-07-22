<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\PlexClient;
use App\Plex\PlexMediaType;
use App\Plex\PlexPosterWriter;
use App\Poster\Source\PosterSource;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\FakePlexPosterWriter;
use App\Tests\Support\MakesImages;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

final class ChangePosterTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
        $this->dataDir = $this->makeTempDir();

        file_put_contents($this->postersDir . '/movies/Solaris.jpg', $this->pngBytes(5, 5));
        $repo = new PlexItemRepository(new Database($this->dataDir . '/marquee.sqlite'));
        $repo->upsert(new PlexItemRecord('10', 'movie', 'movies', 'Movies', 'Solaris', 'Solaris.jpg', time(), '1'));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
        $this->removeDir($this->dataDir);
    }

    public function testChangeFromUrlReplacesAndPushes(): void
    {
        $writer = new FakePlexPosterWriter();
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], $this->pngBytes(2, 3)));

        $app = $this->makeApp(
            [
                'AUTH_BYPASS' => 'true',
                'POSTERS_DIR' => $this->postersDir,
                'DATA_DIR' => $this->dataDir,
                'PLEX_SERVER_URL' => 'http://plex:32400',
                'PLEX_TOKEN' => 'token',
            ],
            [
                ClientInterface::class => static fn (): ClientInterface => $http,
                PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer,
            ],
        );

        $response = $this->postForm($app, '/library/movies/change/url', [
            'filename' => 'Solaris.jpg',
            'url' => 'https://example.com/p.png',
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->pngBytes(2, 3), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));
        self::assertSame(['10'], $writer->uploaded);
    }

    public function testFindPostersReturnsCandidates(): void
    {
        $source = new class () implements PosterSource {
            public function find(string $title, PlexMediaType $mediaType, ?int $season): array
            {
                return ['https://img/a.jpg', 'https://img/b.jpg'];
            }
        };

        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PosterSource::class => static fn (): PosterSource => $source],
        );

        $response = $this->get($app, '/library/movies/find-posters?filename=Solaris.jpg');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('https://img/a.jpg', (string) $response->getBody());
    }

    public function testFetchFromPlexReplacesLocal(): void
    {
        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $response = $this->postForm($app, '/library/movies/fetch-from-plex', ['filename' => 'Solaris.jpg']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->pngBytes(2, 3), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));
    }
}
