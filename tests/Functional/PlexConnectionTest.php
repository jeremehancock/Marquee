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

        foreach ([['GET', '/connect'], ['GET', '/plex/connection/status']] as [, $path]) {
            $response = $this->get($app, $path);
            self::assertSame(302, $response->getStatusCode(), $path);
            self::assertSame('/login', $response->getHeaderLine('Location'), $path);
        }

        $signIn = $this->postForm($app, '/plex/connection/sign-in', []);
        self::assertSame('/login', $signIn->getHeaderLine('Location'));

        $signOut = $this->postForm($app, '/plex/connection/sign-out', []);
        self::assertSame('/login', $signOut->getHeaderLine('Location'));
    }

    public function testNoResponseEverCarriesTheToken(): void
    {
        $app = $this->connectedApp();

        foreach (['/connect', '/plex/connection/status', '/library/all', '/orphans'] as $path) {
            $body = (string) $this->get($app, $path)->getBody();
            self::assertStringNotContainsString('test-plex-token', $body, $path);
        }
    }

    public function testSignOutClearsOnlyTheTokenAndReturnsToTheScreen(): void
    {
        $app = $this->connectedApp();
        $store = new PlexConnectionStore($this->dataDir);
        $clientId = $store->clientIdentifier();
        $secret = $store->signingSecret();

        $response = $this->postForm($app, '/plex/connection/sign-out', []);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/connect', $response->getHeaderLine('Location'));

        $after = new PlexConnectionStore($this->dataDir);
        self::assertNull($after->token());
        self::assertSame($clientId, $after->clientIdentifier());
        self::assertSame($secret, $after->signingSecret());
    }

    // ---- The connection screen ----

    public function testScreenWhenConnected(): void
    {
        $body = (string) $this->get($this->connectedApp(), '/connect')->getBody();

        self::assertStringContainsString('Connected to Anansi', $body);
        self::assertStringContainsString('Sign out of Plex', $body);
        self::assertStringNotContainsString('Plex is not connected', $body);
    }

    public function testConnectedScreenOffersAWayBackToTheGallery(): void
    {
        $body = (string) $this->get($this->connectedApp(), '/connect')->getBody();

        self::assertStringContainsString('Back to gallery', $body);
        self::assertStringContainsString('href="/library/all"', $body);
    }

    public function testDisconnectedScreenOffersNoWayBack(): void
    {
        // The gate would refuse it, so the link would bounce straight back here.
        $app = $this->makeApp($this->env(['PLEX_SERVER_URL' => 'http://plex:32400']));

        self::assertStringNotContainsString('Back to gallery', (string) $this->get($app, '/connect')->getBody());
    }

    public function testScreenWhenNotConnected(): void
    {
        $app = $this->makeApp($this->env(['PLEX_SERVER_URL' => 'http://plex:32400']));

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('Plex is not connected', $body);
        self::assertStringContainsString('Sign in to Plex', $body);
    }

    public function testScreenSaysWhenTheServerAddressIsMissing(): void
    {
        // No PLEX_SERVER_URL. Signing in cannot supply an address, so offering
        // it as the remedy would strand the user behind the gate.
        $body = (string) $this->get($this->makeApp($this->env()), '/connect')->getBody();

        self::assertStringContainsString('PLEX_SERVER_URL', $body);
        self::assertStringContainsString('docker compose up -d', $body);
        self::assertStringNotContainsString('Sign in to Plex</button>', $body);
    }

    public function testScreenExplainsAnObsoleteEnvironmentToken(): void
    {
        $app = $this->makeApp($this->env([
            'PLEX_SERVER_URL' => 'http://plex:32400',
            'PLEX_TOKEN' => 'left-over-from-an-older-version',
        ]));

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('no longer used', $body);
        self::assertStringContainsString('Sign in to Plex', $body);
        // Editing the compose file is not enough — the environment is read once
        // at container start, so the instruction has to say recreate.
        self::assertStringContainsString('docker compose up -d', $body);
        // The obsolete value must never be echoed back.
        self::assertStringNotContainsString('left-over-from-an-older-version', $body);
    }

    public function testTheObsoleteNoticeAdaptsOnceConnected(): void
    {
        $app = $this->makeConnectedApp(
            $this->env(['PLEX_TOKEN' => 'left-over']),
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $body = (string) $this->get($app, '/connect')->getBody();

        // Already connected: telling them to "sign in above" would be nonsense.
        self::assertStringContainsString('safe to remove', $body);
        self::assertStringNotContainsString('Sign in above', $body);
        self::assertStringContainsString('docker compose up -d', $body);
    }

    public function testScreenWarnsWhenLoginIsDisabled(): void
    {
        // Bypass now exposes a credential that can write to the user's library,
        // not just a gallery — so the screen carrying the sign-out button says so.
        $body = (string) $this->get($this->connectedApp(), '/connect')->getBody();

        self::assertStringContainsString('AUTH_BYPASS', $body);
        self::assertStringContainsString('Login is disabled', $body);
    }

    public function testScreenIsQuietWhenAuthenticationIsEnforced(): void
    {
        $app = $this->makeConnectedApp(
            $this->env(['AUTH_BYPASS' => 'false']),
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $this->postForm($app, '/login', ['username' => 'admin', 'password' => 'secret']);
        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('Connected to Anansi', $body);
        self::assertStringNotContainsString('Login is disabled', $body);
    }

    public function testScreenSaysNothingAboutTheVariableWhenItIsAbsent(): void
    {
        $body = (string) $this->get($this->connectedApp(), '/connect')->getBody();

        self::assertStringNotContainsString('no longer used', $body);
    }

    public function testScreenFallsBackWhenTheServerNameIsUnavailable(): void
    {
        $app = $this->connectedApp($this->namelessClient());

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('Connected to Plex', $body);
        self::assertStringContainsString('Sign out of Plex', $body);
    }

    // ---- The gate ----

    public function testGalleryIsUnreachableUntilPlexIsConnected(): void
    {
        $app = $this->makeApp($this->env(['PLEX_SERVER_URL' => 'http://plex:32400']));

        foreach (['/library/all', '/plex', '/orphans'] as $path) {
            $response = $this->get($app, $path);
            self::assertSame(302, $response->getStatusCode(), $path);
            self::assertSame('/connect', $response->getHeaderLine('Location'), $path);
        }
    }

    public function testConnectingReleasesTheGate(): void
    {
        self::assertSame(200, $this->get($this->connectedApp(), '/library/all')->getStatusCode());
    }

    public function testAuthenticationComesBeforeTheGate(): void
    {
        // Not logged in and not connected: login wins, or the connection screen
        // and its sign-in action would be exposed to anyone reaching the host.
        $app = $this->makeApp($this->env(['AUTH_BYPASS' => 'false']));

        $response = $this->get($app, '/library/all');

        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testTheWallRunsWithoutAPlexConnection(): void
    {
        // Specified to run unattended on a display; a gate would break that
        // exactly while someone is reconfiguring Plex.
        $app = $this->makeApp($this->env());

        self::assertSame(200, $this->get($app, '/wall')->getStatusCode());
        self::assertSame(200, $this->get($app, '/wall/posters')->getStatusCode());
    }

    public function testHealthAndManifestStayReachable(): void
    {
        $app = $this->makeApp($this->env());

        self::assertSame(200, $this->get($app, '/health')->getStatusCode());
        self::assertSame(200, $this->get($app, '/manifest.webmanifest')->getStatusCode());
    }

    // ---- Signing in end to end ----

    public function testACompleteSignInStoresTheTokenAndNeverLogsIt(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($handler);

        $plexTv = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['authToken' => 'granted-secret-token'])),
            // The server, asked with the new token, names its owner...
            new Response(200, [], '<MediaContainer friendlyName="Anansi" myPlexUsername="owner@example.com"/>'),
            // ...and plex.tv says who the token belongs to.
            new Response(200, [], (string) json_encode(['username' => 'owner', 'email' => 'owner@example.com'])),
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

        // The browser leaves for the gallery next, so the confirmation has to be
        // waiting on the next page rather than on the screen it just left.
        // Asserted here against the connection screen rather than the gallery:
        // configuration resolves once per container, so within a single test app
        // the gate still holds the pre-sign-in view and would turn the gallery
        // away. A real request builds a fresh container and sees the token.
        self::assertStringContainsString('Signed in to Plex.', (string) $this->get($app, '/connect')->getBody());

        self::assertSame(
            'granted-secret-token',
            (new PlexConnectionStore($this->dataDir))->token(),
        );

        foreach ($handler->getRecords() as $record) {
            self::assertStringNotContainsString('granted-secret-token', (string) json_encode($record->toArray()));
        }
    }

    public function testASignInByANonOwnerIsRefusedAndStoresNothing(): void
    {
        $plexTv = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['authToken' => 'a-guests-token'])),
            new Response(200, [], '<MediaContainer friendlyName="Anansi" myPlexUsername="owner@example.com"/>'),
            new Response(200, [], (string) json_encode(['username' => 'guest', 'email' => 'guest@example.com'])),
        ]))]);

        $app = $this->makeApp(
            $this->env(['PLEX_SERVER_URL' => 'http://plex:32400']),
            [
                PlexClient::class => static fn (): PlexClient => new FakePlexClient(),
                ClientInterface::class => static fn (): ClientInterface => $plexTv,
            ],
        );

        $this->postForm($app, '/plex/connection/sign-in', []);
        $poll = (string) $this->get($app, '/plex/connection/status')->getBody();

        self::assertStringContainsString('not_owner', $poll);
        self::assertNull((new PlexConnectionStore($this->dataDir))->token());
        // The refusal must not tell a stranger who the owner is.
        self::assertStringNotContainsString('owner@example.com', $poll);
    }

    public function testAFirstSignInSucceedsWithNoTokenStoredYet(): void
    {
        // The regression that made the owner check unusable: it consulted the
        // stored configuration, which on a first connection has no token, and
        // the fail-closed rule turned "cannot tell" into a refusal of the owner.
        $plexTv = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['authToken' => 'the-owners-token'])),
            new Response(200, [], '<MediaContainer friendlyName="Anansi" myPlexUsername="owner@example.com"/>'),
            new Response(200, [], (string) json_encode(['username' => 'owner', 'email' => 'owner@example.com'])),
        ]))]);

        $app = $this->makeApp(
            $this->env(['PLEX_SERVER_URL' => 'http://plex:32400']),
            [
                PlexClient::class => static fn (): PlexClient => new FakePlexClient(),
                ClientInterface::class => static fn (): ClientInterface => $plexTv,
            ],
        );

        $this->postForm($app, '/plex/connection/sign-in', []);
        $poll = (string) $this->get($app, '/plex/connection/status')->getBody();

        self::assertStringContainsString('completed', $poll);
        self::assertSame('the-owners-token', (new PlexConnectionStore($this->dataDir))->token());
    }

    public function testAbandonedSignInLeavesAnExistingConnectionAlone(): void
    {
        $plexTv = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(404, [], '{}'),
        ]))]);

        $app = $this->makeConnectedApp(
            $this->env(),
            [
                PlexClient::class => static fn (): PlexClient => new FakePlexClient(),
                ClientInterface::class => static fn (): ClientInterface => $plexTv,
            ],
        );

        $this->postForm($app, '/plex/connection/sign-in', []);
        $poll = (string) $this->get($app, '/plex/connection/status')->getBody();

        self::assertStringContainsString('expired', $poll);
        self::assertSame(
            'test-plex-token',
            (new PlexConnectionStore($this->dataDir))->token(),
        );
    }

    public function testStatusReportsNothingOutstandingWhenNoSignInIsRunning(): void
    {
        $body = (string) $this->get($this->connectedApp(), '/plex/connection/status')->getBody();

        self::assertStringContainsString('not_started', $body);
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
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function connectedApp(?PlexClient $client = null): App
    {
        $client ??= new FakePlexClient();

        return $this->makeConnectedApp(
            $this->env(),
            [PlexClient::class => static fn (): PlexClient => $client],
        );
    }

    /**
     * A configured server that never yields a name, standing in for one that is
     * unreachable.
     */
    private function namelessClient(): FakePlexClient
    {
        return new FakePlexClient([], [], [], [], [], true, [], [], [], null);
    }
}
