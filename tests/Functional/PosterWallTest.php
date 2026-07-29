<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Plex\PlexClient;
use App\Plex\PlexSession;
use App\Plex\PlexSessionType;
use App\Poster\Wall\StreamToken;
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

    /**
     * The reported failure, end to end: a DVR programme whose artwork lives on
     * the tuner must reach the wall as a placeholder poster it can actually
     * fetch, rather than as a token the proxy answers with a 404.
     */
    public function testDvrSessionAdvertisesAPosterTheWallCanFetch(): void
    {
        $app = $this->appWithSessions([
            new PlexSession(PlexSessionType::LiveTv, 'Evening News', 'jereme', true, grandparentTitle: 'Channel 4'),
        ]);

        $streams = (string) $this->get($app, '/wall/streams')->getBody();
        self::assertStringContainsString('"poster":"/wall/stream-poster/live"', $streams);
        self::assertStringContainsString('"title":"Evening News"', $streams);
        self::assertStringContainsString('Live TV', $streams);

        $poster = $this->get($app, '/wall/stream-poster/live');
        self::assertSame(200, $poster->getStatusCode());
        self::assertStringContainsString('<svg', (string) $poster->getBody());
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
        // This is the intended art for a Live TV session, not a stand-in, so it
        // is free to cache.
        self::assertSame('private, max-age=3600', $response->getHeaderLine('Cache-Control'));
    }

    /**
     * A poster token is deterministic per item, so caching a failure placeholder
     * for long would pin that item to the placeholder well after Plex recovered,
     * with nothing to trigger a retry.
     */
    public function testPlaceholderForAFailedPlexFetchExpiresQuickly(): void
    {
        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'PLEX_TOKEN' => 'plex-secret'],
            [PlexClient::class => static fn (): FakePlexClient => new FakePlexClient(failingThumbs: ['/t/1'])],
        );
        $token = (new StreamToken('plex-secret'))->forThumb('/t/1');

        $response = $this->get($app, '/wall/stream-poster/' . $token);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        self::assertSame('private, max-age=10', $response->getHeaderLine('Cache-Control'));
    }

    /**
     * An unsigned token still resolves to no Plex path, so the proxy refuses to
     * fetch anything — but it answers with the placeholder rather than a 404, so
     * a tile on the wall can never end up with a broken image.
     */
    public function testStreamPosterServesThePlaceholderForAnUnsignedToken(): void
    {
        $response = $this->get($this->app(), '/wall/stream-poster/forged');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<svg', (string) $response->getBody());
        self::assertSame('private, max-age=10', $response->getHeaderLine('Cache-Control'));
    }

    /**
     * The signature verifies but the payload is not a Plex library path, so the
     * proxy must not fetch it. This is the shape a DVR tuner's artwork URL takes.
     */
    public function testStreamPosterServesThePlaceholderForANonPlexPath(): void
    {
        // Tokens are signed with the Plex token, so the test must sign with the
        // same secret for the signature to verify and the path check to be what
        // actually rejects the token.
        $app = $this->makeApp([
            'AUTH_BYPASS' => 'true',
            'POSTERS_DIR' => $this->postersDir,
            'PLEX_TOKEN' => 'plex-secret',
        ]);
        $token = (new StreamToken('plex-secret'))->forThumb('https://tuner.example/artwork/poster');

        $response = $this->get($app, '/wall/stream-poster/' . $token);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<svg', (string) $response->getBody());
        self::assertSame('private, max-age=10', $response->getHeaderLine('Cache-Control'));
    }

    public function testStreamsArePublic(): void
    {
        // No AUTH_BYPASS: the now-playing endpoint must be reachable too.
        $response = $this->get($this->makeApp(['POSTERS_DIR' => $this->postersDir]), '/wall/streams');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"streams"', (string) $response->getBody());
    }
}
