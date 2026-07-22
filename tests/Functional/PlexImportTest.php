<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Plex\PlexClient;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PlexImportTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
        $this->dataDir = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
        $this->removeDir($this->dataDir);
    }

    public function testPlexPageShowsNotConfiguredMessage(): void
    {
        $response = $this->get($this->makeApp(['AUTH_BYPASS' => 'true']), '/plex');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('not configured', (string) $response->getBody());
    }

    public function testPlexPageListsLibraries(): void
    {
        $fake = new FakePlexClient([
            new PlexLibrary('1', 'Movies', 'movie'),
            new PlexLibrary('2', 'TV', 'show'),
        ]);

        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true'],
            [PlexClient::class => static fn (): PlexClient => $fake],
        );

        $body = (string) $this->get($app, '/plex')->getBody();

        self::assertStringContainsString('Movies', $body);
        self::assertStringContainsString('TV Seasons', $body);
        self::assertStringContainsString('Import', $body);
    }

    public function testImportStoresPosters(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $fake = new FakePlexClient([$library], ['1' => [$movie]]);

        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => $fake],
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/plex/import')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query(['sections' => ['1'], 'types' => ['movie']]));
        $request->getBody()->rewind();

        $response = $app->handle($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/plex', $response->getHeaderLine('Location'));

        $files = array_filter(
            scandir($this->postersDir . '/movies') ?: [],
            fn (string $f): bool => is_file($this->postersDir . '/movies/' . $f),
        );
        self::assertCount(1, $files);
    }
}
