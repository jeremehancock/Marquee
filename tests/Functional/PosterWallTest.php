<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Plex\PlexClient;
use App\Plex\PlexSession;
use App\Plex\PlexSessionType;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;
use Slim\App;

final class PosterWallTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
        $this->writePng($this->postersDir . '/movies/Solaris.png');
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
        return $this->makeApp(['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir]);
    }

    public function testWallPageRenders(): void
    {
        $response = $this->get($this->app(), '/wall');

        $body = (string) $response->getBody();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('wall__layer', $body);
        self::assertStringContainsString('/assets/favicon.svg', $body);
    }

    public function testPostersEndpointReturnsJson(): void
    {
        $response = $this->get($this->app(), '/wall/posters');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('/posters/movies/Solaris.png', (string) $response->getBody());
    }

    public function testWallIsPublic(): void
    {
        // No AUTH_BYPASS: the wall must still render for an unattended display.
        $response = $this->get($this->makeApp(['POSTERS_DIR' => $this->postersDir]), '/wall');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('wall__layer', (string) $response->getBody());
    }

    /**
     * @param list<PlexSession> $sessions
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function appWithSessions(array $sessions): App
    {
        return $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir],
            [PlexClient::class => static fn (): FakePlexClient => new FakePlexClient(sessions: $sessions)],
        );
    }

    public function testStreamsEndpointReportsNowPlaying(): void
    {
        $app = $this->appWithSessions([
            new PlexSession(PlexSessionType::Movie, 'Dune', 'jereme', false, '/t/1', year: 2021),
            new PlexSession(PlexSessionType::Music, 'A Song', 'dj', false),
        ]);

        $response = $this->get($app, '/wall/streams');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('"title":"Dune"', $body);
        self::assertStringContainsString('"user":"jereme"', $body);
        // Music is excluded from the wall.
        self::assertStringNotContainsString('A Song', $body);
    }

    public function testStreamsEmptyWhenPlexUnconfigured(): void
    {
        $response = $this->get($this->app(), '/wall/streams');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"streams":[]', (string) $response->getBody());
    }

    public function testLiveTvStreamPosterServesPlaceholder(): void
    {
        $app = $this->appWithSessions([
            new PlexSession(PlexSessionType::LiveTv, 'SportsCenter', 'guest', true, grandparentTitle: 'ESPN'),
        ]);

        $response = $this->get($app, '/wall/stream-poster/live');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<svg', (string) $response->getBody());
    }

    public function testStreamPosterRejectsAnUnsignedToken(): void
    {
        $response = $this->get($this->app(), '/wall/stream-poster/forged');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testStreamsArePublic(): void
    {
        // No AUTH_BYPASS: the now-playing endpoint must be reachable too.
        $response = $this->get($this->makeApp(['POSTERS_DIR' => $this->postersDir]), '/wall/streams');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"streams"', (string) $response->getBody());
    }
}
