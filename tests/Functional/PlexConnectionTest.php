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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
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

    /**
     * Signing in is how a session is obtained, so the screen and the two routes
     * behind it have to be reachable without one.
     */
    public function testSigningInIsReachableWithoutASession(): void
    {
        $app = $this->makeApp($this->env());

        self::assertSame(200, $this->get($app, '/connect')->getStatusCode());
        self::assertSame(200, $this->get($app, '/plex/connection/status')->getStatusCode());
    }

    /**
     * Disconnecting is not among them. It destroys the connection, which is
     * something only a signed-in user may do.
     */
    public function testDisconnectingStillRequiresASession(): void
    {
        $app = $this->makeConnectedApp($this->env());

        $response = $this->postForm($app, '/plex/connection/sign-out', []);

        self::assertSame('/connect', $response->getHeaderLine('Location'));
        self::assertNotNull((new PlexConnectionStore($this->dataDir))->token());
    }

    /**
     * Polling is public, so it must give an anonymous caller nothing but the
     * outcome of the request they started.
     */
    public function testPollingDisclosesNothingButTheOutcome(): void
    {
        $body = (string) $this->get($this->makeApp($this->env()), '/plex/connection/status')->getBody();

        self::assertSame('{"status":"not_started"}', $body);
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
        self::assertStringContainsString('Disconnect from Plex', $body);
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

    public function testScreenWhenSignedInButNotConnected(): void
    {
        $app = $this->makeApp($this->env(['PLEX_SERVER_URL' => 'http://plex:32400']));
        $this->signIn($app);

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('Plex is not connected', $body);
        self::assertStringContainsString('Sign in with Plex', $body);
    }

    /**
     * The screen a visitor with no session meets. One action, because signing in
     * to Plex is both the login and the connection — offering them as two
     * choices would ask the user to pick between two names for one thing.
     */
    public function testScreenOffersOneActionWhenSignedOut(): void
    {
        $app = $this->makeApp($this->env(['PLEX_SERVER_URL' => 'http://plex:32400']));

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('Sign in with Plex', $body);
        self::assertStringContainsString('owns this server', $body);
        // Nothing to disconnect from, and no navigation behind the gate.
        self::assertStringNotContainsString('Disconnect from Plex', $body);
        self::assertStringNotContainsString('/logout', $body);
    }

    public function testScreenSaysWhenTheServerAddressIsMissing(): void
    {
        // No PLEX_SERVER_URL. Signing in cannot supply an address, so offering
        // it as the remedy would strand the user behind the gate.
        $body = (string) $this->get($this->makeApp($this->env()), '/connect')->getBody();

        self::assertStringContainsString('PLEX_SERVER_URL', $body);
        self::assertStringContainsString('docker compose up -d', $body);
        self::assertStringNotContainsString('Connect to Plex</button>', $body);
    }

    public function testScreenExplainsAnObsoleteEnvironmentToken(): void
    {
        $app = $this->makeApp($this->env([
            'PLEX_SERVER_URL' => 'http://plex:32400',
            'PLEX_TOKEN' => 'left-over-from-an-older-version',
        ]));

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('no longer used', $body);
        self::assertStringContainsString('Sign in with Plex', $body);
        // Editing the compose file is not enough — the environment is read once
        // at container start, so the instruction has to say recreate.
        self::assertStringContainsString('docker compose up -d', $body);
        // The obsolete value must never be echoed back.
        self::assertStringNotContainsString('left-over-from-an-older-version', $body);
    }

    public function testTheObsoleteNoticeAdaptsOnceConnected(): void
    {
        $app = $this->makeSignedInApp(
            $this->env(['PLEX_TOKEN' => 'left-over']),
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $body = (string) $this->get($app, '/connect')->getBody();

        // Already connected: telling them to "sign in above" would be nonsense.
        self::assertStringContainsString('safe to remove', $body);
        self::assertStringNotContainsString('Connect above', $body);
        self::assertStringContainsString('docker compose up -d', $body);
    }

    /**
     * An install running unattended on bypass has no login at all today and
     * will start demanding one. Meeting that as a fault rather than as an
     * explained change is the worst version of this upgrade.
     */
    public function testScreenSaysBypassNoLongerDisablesTheLogin(): void
    {
        $app = $this->makeSignedInApp(
            $this->env(['AUTH_BYPASS' => 'true']),
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('AUTH_BYPASS', $body);
        self::assertStringContainsString('no longer disables the login', $body);
        // The wall is the one thing that did run unattended and still does.
        self::assertStringContainsString('Poster Wall is unaffected', $body);
    }

    /**
     * Presence, not truth: `AUTH_BYPASS=false` is just as obsolete, and the
     * remedy — delete the line — is the same.
     */
    public function testTheBypassNoticeIsRaisedByPresenceNotByValue(): void
    {
        $app = $this->makeSignedInApp(
            $this->env(['AUTH_BYPASS' => 'false']),
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        self::assertStringContainsString(
            'no longer disables the login',
            (string) $this->get($app, '/connect')->getBody(),
        );
    }

    public function testScreenSaysTheEnvironmentCredentialsAreNoLongerUsed(): void
    {
        $app = $this->makeSignedInApp(
            $this->env(['AUTH_USERNAME' => 'admin', 'AUTH_PASSWORD' => 'hunter2']),
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('AUTH_USERNAME', $body);
        self::assertStringContainsString('no longer used', $body);
        // Never echoed back, obsolete or not.
        self::assertStringNotContainsString('hunter2', $body);
    }

    public function testScreenIsQuietWhenNoObsoleteVariablesAreSet(): void
    {
        $app = $this->makeSignedInApp(
            $this->env(),
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('Connected to Anansi', $body);
        self::assertStringNotContainsString('AUTH_BYPASS', $body);
        self::assertStringNotContainsString('AUTH_USERNAME', $body);
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
        self::assertStringContainsString('Disconnect from Plex', $body);
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

    public function testBothGatesSendAVisitorToTheSameScreen(): void
    {
        // Neither a session nor a connection. One sign-in satisfies both, so a
        // new install is asked for one thing rather than two in sequence — and
        // the gates cannot disagree about where to send somebody.
        $anonymousAndDisconnected = $this->makeApp($this->env());
        $anonymousButConnected = $this->makeConnectedApp($this->env());

        foreach ([$anonymousAndDisconnected, $anonymousButConnected] as $app) {
            $response = $this->get($app, '/library/all');

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/connect', $response->getHeaderLine('Location'));
        }
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

    /**
     * The owner signed in correctly; PLEX_SERVER_URL points at nothing. Telling
     * them their account does not own the server sends them to the one place
     * that is working.
     */
    public function testAnUnreachableServerIsReportedAsUnreachableNotAsOwnership(): void
    {
        $plexTv = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['authToken' => 'the-owners-token'])),
            // Nothing is listening at the configured address.
            new ConnectException('refused', new Request('GET', 'http://plex:32400/')),
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

        self::assertStringContainsString('unreachable', $poll);
        self::assertStringNotContainsString('not_owner', $poll);
        // Refuses like every other failure: nothing is written.
        self::assertNull((new PlexConnectionStore($this->dataDir))->token());
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

        return $this->makeSignedInApp(
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
