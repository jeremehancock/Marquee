<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Plex\Connection\PlexConnectionStore;
use App\Plex\PlexClient;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\App;

final class PlexConnectionTest extends AppTestCase
{
    use MakesImages;

    private string $dataDir = '';
    private string $postersDir = '';

    protected function setUp(): void
    {
        $this->dataDir = $this->makeTempDir();
        $this->postersDir = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dataDir);
        $this->removeDir($this->postersDir);
    }

    // ---- Routes are protected and disclose nothing ----

    public function testConnectionRoutesRequireAuthentication(): void
    {
        $app = $this->makeApp($this->env(['AUTH_BYPASS' => 'false']));

        $status = $this->get($app, '/plex/connection/status');
        self::assertSame(302, $status->getStatusCode());
        self::assertSame('/login', $status->getHeaderLine('Location'));

        $signIn = $this->postForm($app, '/plex/connection/sign-in', []);
        self::assertSame(302, $signIn->getStatusCode());
        self::assertSame('/login', $signIn->getHeaderLine('Location'));

        $signOut = $this->postForm($app, '/plex/connection/sign-out', []);
        self::assertSame(302, $signOut->getStatusCode());
        self::assertSame('/login', $signOut->getHeaderLine('Location'));
    }

    public function testNoResponseEverCarriesTheToken(): void
    {
        $app = $this->signedInApp('super-secret-token');

        foreach (['/plex', '/plex/connection/status', '/library/all', '/orphans'] as $path) {
            $body = (string) $this->get($app, $path)->getBody();
            self::assertStringNotContainsString('super-secret-token', $body, $path);
        }
    }

    public function testACompleteSignInStoresTheTokenAndNeverLogsIt(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($handler);

        $plexTv = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['authToken' => 'granted-secret-token'])),
        ]))]);

        $app = $this->makeApp(
            $this->env(['PLEX_SERVER_URL' => 'http://plex:32400']),
            [
                PlexClient::class => static fn (): PlexClient => new FakePlexClient(),
                ClientInterface::class => static fn (): ClientInterface => $plexTv,
                LoggerInterface::class => static fn (): LoggerInterface => $logger,
            ],
        );

        $start = $this->postForm($app, '/plex/connection/sign-in', []);
        self::assertSame(200, $start->getStatusCode());
        self::assertStringContainsString('app.plex.tv', (string) $start->getBody());

        $poll = (string) $this->get($app, '/plex/connection/status')->getBody();
        self::assertStringContainsString('completed', $poll);
        self::assertStringNotContainsString('granted-secret-token', $poll);

        self::assertSame('granted-secret-token', $this->store()->token());

        foreach ($handler->getRecords() as $record) {
            // The whole record — message, context, extra, formatted output.
            self::assertStringNotContainsString('granted-secret-token', (string) json_encode($record->toArray()));
        }
    }

    public function testAbandonedSignInLeavesAnExistingConnectionAlone(): void
    {
        $plexTv = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(404, [], '{}'),
        ]))]);

        $app = $this->makeApp(
            $this->env(['PLEX_SERVER_URL' => 'http://plex:32400']),
            [
                PlexClient::class => static fn (): PlexClient => new FakePlexClient(),
                ClientInterface::class => static fn (): ClientInterface => $plexTv,
            ],
        );
        $this->store()->storeToken('already-connected');

        $this->postForm($app, '/plex/connection/sign-in', []);
        $poll = (string) $this->get($app, '/plex/connection/status')->getBody();

        self::assertStringContainsString('expired', $poll);
        self::assertSame('already-connected', $this->store()->token());
    }

    public function testStatusReportsNothingOutstandingWhenNoSignInIsRunning(): void
    {
        $body = (string) $this->get($this->connectedApp(), '/plex/connection/status')->getBody();

        self::assertStringContainsString('not_started', $body);
    }

    public function testSignOutClearsOnlyTheToken(): void
    {
        $app = $this->signedInApp('stored-token');
        $store = $this->store();
        $clientId = $store->clientIdentifier();
        $secret = $store->signingSecret();

        $response = $this->postForm($app, '/plex/connection/sign-out', []);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/plex', $response->getHeaderLine('Location'));

        $after = $this->store();
        self::assertNull($after->token());
        self::assertSame($clientId, $after->clientIdentifier());
        self::assertSame($secret, $after->signingSecret());
    }

    // ---- The four connection panel states ----

    public function testPanelWhenNotConnected(): void
    {
        $body = (string) $this->get($this->makeApp($this->env()), '/plex')->getBody();

        self::assertStringContainsString('Plex is not connected', $body);
        self::assertStringContainsString('Sign in to Plex', $body);
        self::assertStringNotContainsString('Signed in to Plex.', $body);
    }

    public function testPanelWhenSignedIn(): void
    {
        $body = (string) $this->get($this->signedInApp('stored-token'), '/plex')->getBody();

        self::assertStringContainsString('Connected to Anansi', $body);
        self::assertStringContainsString('Signed in to Plex.', $body);
        self::assertStringContainsString('Sign out of Plex', $body);
    }

    public function testPanelWhenUsingTheEnvironmentVariable(): void
    {
        $body = (string) $this->get(
            $this->connectedApp(['PLEX_TOKEN' => 'from-environment']),
            '/plex',
        )->getBody();

        self::assertStringContainsString('Connected to Anansi', $body);
        self::assertStringContainsString('Using <code>PLEX_TOKEN</code>', $body);
        self::assertStringContainsString('Sign in to Plex instead', $body);
        self::assertStringNotContainsString('not in use', $body);
    }

    public function testPanelWhenSignedInButOverriddenByTheEnvironment(): void
    {
        $app = $this->signedInApp('stored-token', ['PLEX_TOKEN' => 'from-environment']);

        $body = (string) $this->get($app, '/plex')->getBody();

        // The state that must never be reported as a plain "signed in".
        self::assertStringContainsString('stored but not in use', $body);
        self::assertStringContainsString('takes precedence', $body);
        self::assertStringContainsString('Remove <code>PLEX_TOKEN</code>', $body);
    }

    public function testPanelFallsBackWhenTheServerNameIsUnavailable(): void
    {
        $app = $this->signedInApp('stored-token', [], $this->namelessClient());

        $body = (string) $this->get($app, '/plex')->getBody();

        self::assertStringContainsString('Connected to Plex', $body);
        self::assertStringContainsString('Signed in to Plex.', $body);
    }

    public function testPanelLinksToTheComparisonDocumentation(): void
    {
        $body = (string) $this->get($this->makeApp($this->env()), '/plex')->getBody();

        self::assertStringContainsString('docs/plex-connection.md', $body);
        self::assertStringContainsString("What's the difference?", $body);
    }

    // ---- The app-wide status line ----

    public function testStatusLineAppearsOnAnAuthenticatedPage(): void
    {
        $app = $this->signedInApp('stored-token');
        // The panel is what refreshes the cached name the status line reports.
        $this->get($app, '/plex');

        $body = (string) $this->get($app, '/library/all')->getBody();

        self::assertStringContainsString('Plex: Anansi (signed in)', $body);
    }

    public function testStatusLineNamesTheEnvironmentSource(): void
    {
        $app = $this->connectedApp(['PLEX_TOKEN' => 'from-environment']);
        $this->get($app, '/plex');

        $body = (string) $this->get($app, '/library/all')->getBody();

        self::assertStringContainsString('Plex: Anansi (PLEX_TOKEN)', $body);
    }

    public function testStatusLineReportsNotConnected(): void
    {
        $body = (string) $this->get($this->makeApp($this->env()), '/library/all')->getBody();

        self::assertStringContainsString('Plex: not connected', $body);
    }

    public function testStatusLineIsAbsentFromThePosterWall(): void
    {
        $app = $this->signedInApp('stored-token');
        $this->get($app, '/plex');

        $body = (string) $this->get($app, '/wall')->getBody();

        self::assertStringNotContainsString('Plex: Anansi', $body);
        self::assertStringNotContainsString('footer__plex', $body);
    }

    public function testAnUnreachablePlexDoesNotStopPagesRendering(): void
    {
        // Configured, but the server never answers — the case a health check on
        // every page render would stall for the connect timeout.
        $app = $this->signedInApp('stored-token', [], $this->namelessClient());

        $response = $this->get($app, '/library/all');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Plex:', (string) $response->getBody());
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    private function env(array $extra = []): array
    {
        return array_merge([
            'AUTH_BYPASS' => 'true',
            'DATA_DIR' => $this->dataDir,
            'POSTERS_DIR' => $this->postersDir,
        ], $extra);
    }

    /**
     * An app whose Plex server address is set, so a token is all that is
     * missing.
     *
     * @param array<string, string> $extra
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function connectedApp(array $extra = [], ?PlexClient $client = null): App
    {
        $client ??= new FakePlexClient();

        return $this->makeApp(
            $this->env(array_merge(['PLEX_SERVER_URL' => 'http://plex:32400'], $extra)),
            [PlexClient::class => static fn (): PlexClient => $client],
        );
    }

    /**
     * The same, with a token already stored.
     *
     * The token is written after the app is built, because building one clears
     * any stored connection so that tests cannot leak one into each other. The
     * configuration is resolved lazily on the first request, so a token written
     * here is still the one the request sees.
     *
     * @param array<string, string> $extra
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function signedInApp(string $token, array $extra = [], ?PlexClient $client = null): App
    {
        $app = $this->connectedApp($extra, $client);
        $this->store()->storeToken($token);

        return $app;
    }

    /**
     * A configured server that never yields a name, standing in for one that is
     * unreachable.
     */
    private function namelessClient(): FakePlexClient
    {
        return new FakePlexClient([], [], [], [], [], true, [], [], [], null);
    }

    private function store(): PlexConnectionStore
    {
        return new PlexConnectionStore($this->dataDir);
    }
}
