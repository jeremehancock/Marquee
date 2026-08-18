<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Auth\CsrfMiddleware;
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

    public function testImportPageIsUnreachableUntilPlexIsConnected(): void
    {
        // The import page no longer explains how to connect — it cannot be
        // reached at all until you have. Signed in, so the connection gate is
        // what turns the request away rather than authentication.
        $app = $this->makeApp();
        $this->signIn($app);

        $response = $this->get($app, '/plex');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/connect', $response->getHeaderLine('Location'));
    }

    public function testImportPageOffersNoConnectionManagement(): void
    {
        $body = (string) $this->get($this->makeSignedInApp(), '/plex')->getBody();

        self::assertStringNotContainsString('Connect to Plex', $body);
        self::assertStringNotContainsString('Disconnect from Plex', $body);
    }

    public function testPlexPageListsLibraries(): void
    {
        $fake = new FakePlexClient([
            new PlexLibrary('1', 'Movies', 'movie'),
            new PlexLibrary('2', 'TV', 'show'),
        ]);

        $app = $this->makeSignedInApp(
            [],
            [PlexClient::class => static fn (): PlexClient => $fake],
        );

        $body = (string) $this->get($app, '/plex')->getBody();

        self::assertStringContainsString('Movies', $body);
        self::assertStringContainsString('TV Seasons', $body);
        self::assertStringContainsString('Import', $body);
    }

    public function testPlexPageOmitsExcludedLibraries(): void
    {
        $fake = new FakePlexClient(
            [new PlexLibrary('1', 'Movies', 'movie'), new PlexLibrary('3', 'Kids', 'movie')],
            excluded: ['Kids'],
        );

        $app = $this->makeSignedInApp(
            ['EXCLUDED_LIBRARIES' => 'Kids'],
            [PlexClient::class => static fn (): PlexClient => $fake],
        );

        $body = (string) $this->get($app, '/plex')->getBody();

        self::assertStringContainsString('Movies', $body);
        self::assertStringNotContainsString('Kids', $body);
    }

    public function testPlexPageExplainsWhenEveryLibraryIsExcluded(): void
    {
        $fake = new FakePlexClient(
            [new PlexLibrary('1', 'Movies', 'movie'), new PlexLibrary('2', 'TV', 'show')],
            excluded: ['Movies', 'TV'],
        );

        $app = $this->makeSignedInApp(
            ['EXCLUDED_LIBRARIES' => 'Movies,TV'],
            [PlexClient::class => static fn (): PlexClient => $fake],
        );

        $body = (string) $this->get($app, '/plex')->getBody();

        self::assertStringContainsString('every one of them is', $body);
        // Points at the screen that changes exclusions, not at the variable that
        // used to set them — that line no longer configures anything.
        self::assertStringContainsString('href="/settings"', $body);
        self::assertStringNotContainsString('EXCLUDED_LIBRARIES', $body);
        // Not the "your server has no libraries" message, which would be wrong here.
        self::assertStringNotContainsString('were found on your Plex server', $body);
    }

    public function testImportStoresPosters(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $fake = new FakePlexClient([$library], ['1' => [$movie]]);

        $app = $this->makeSignedInApp(
            ['POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => $fake],
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/plex/import')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        // Built by hand rather than with postForm() because the import posts
        // array-valued fields. The token still has to travel: this route is
        // state-changing, and bypass does not exempt it.
        $request->getBody()->write(http_build_query([
            'sections' => ['1'],
            'types' => ['movie'],
            CsrfMiddleware::FIELD => $this->csrfToken($app),
        ]));
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
