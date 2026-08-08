<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Auth\SessionAuthenticator;
use App\Config\AuthConfig;
use App\Config\PlexConfig;
use App\Plex\Connection\PlexConnectionStore;
use App\Plex\Connection\PlexPinClient;
use App\Plex\Connection\PlexServerOwner;
use App\Plex\Connection\PlexSignInException;
use App\Plex\Connection\PlexSignInService;
use App\Plex\Connection\PlexSignInStatus;
use App\Support\Session\ArraySession;
use App\Support\Session\SessionInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class PlexSignInServiceTest extends TestCase
{
    private string $dir = '';

    /** @var list<RequestInterface> */
    private array $sent = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/marquee-signin-' . bin2hex(random_bytes(6));
        $this->sent = [];
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testSuccessfulSignInStoresTheToken(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse(),
            $this->accountResponse('owner@example.com'),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::Completed, $service->poll());
        self::assertSame('granted-token', $store->token());
    }

    public function testPendingWhileTheUserHasNotApproved(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => null])),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::Pending, $service->poll());
        self::assertNull($store->token());
    }

    public function testPollWithoutStartingIsNotStarted(): void
    {
        self::assertSame(PlexSignInStatus::NotStarted, $this->service([])->poll());
    }

    public function testAnotherSessionCannotClaimTheSignIn(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $starter = new ArraySession();
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse(),
            $this->accountResponse('owner@example.com'),
        ], $store, $starter);

        $service->start();

        // A different browser session shares the store but not the session, so
        // it has no outstanding request and nothing it can supply to claim one.
        $otherSession = new ArraySession();
        $other = new PlexSignInService(
            $this->pinClient([], $store),
            $store,
            $otherSession,
            new PlexServerOwner(new Client(['handler' => $this->stack([])]), new PlexConfig('http://plex:32400', '', 10, 60)),
            new SessionAuthenticator(new AuthConfig(sessionDuration: 3600), $otherSession),
        );

        self::assertSame(PlexSignInStatus::NotStarted, $other->poll());
        self::assertNull($store->token());

        // The session that started it still completes normally.
        self::assertSame(PlexSignInStatus::Completed, $service->poll());
    }

    public function testExpiryIsReportedWhenPlexHasForgottenTheRequest(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('already-connected');

        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(404, [], '{"errors":[]}'),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::Expired, $service->poll());
        // An abandoned sign-in must not disconnect an already-connected install.
        self::assertSame('already-connected', $store->token());
    }

    public function testLocallyExpiredRequestIsNotPolled(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $session = new ArraySession();
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 1])),
        ], $store, $session);

        $service->start();
        $session->set('plex_pin_expires_at', time() - 1);

        // No second response is queued: expiry must be decided without a call.
        self::assertSame(PlexSignInStatus::Expired, $service->poll());
    }

    public function testExpiredRequestStopsBeingPolled(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(404, [], '{}'),
        ], $store);

        $service->start();
        self::assertSame(PlexSignInStatus::Expired, $service->poll());
        // The request is forgotten, so a further poll makes no request at all.
        self::assertSame(PlexSignInStatus::NotStarted, $service->poll());
    }

    public function testUnreachablePlexSurfacesAsAFailure(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new ConnectException('offline', new Request('GET', 'https://plex.tv')),
        ], $store);

        $service->start();

        $this->expectException(PlexSignInException::class);
        $service->poll();
    }

    public function testTheAuthorizationRequestAsksForAStrongCode(): void
    {
        $this->service([
            new Response(200, [], (string) json_encode(['id' => 1, 'code' => 'ABCD', 'expiresIn' => 900])),
        ])->start();

        // Without this Plex issues a short plex.tv/link code, and the
        // app.plex.tv/auth deep link refuses it outright.
        self::assertStringContainsString('strong=true', (string) $this->sent[0]->getUri());
    }

    public function testAnAccountThatDoesNotOwnTheServerIsRefused(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse(),
            $this->accountResponse('someone-else@example.com'),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::NotOwner, $service->poll());
        // Nothing stored: Plex would stop this account changing the library,
        // but not deleting posters here.
        self::assertNull($store->token());
    }

    public function testTheOwnerIsMatchedOnUsernameAsWellAsEmail(): void
    {
        // A Plex server reports one field for its owner and does not say which
        // kind of identifier it holds.
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse('jereme'),
            $this->accountResponse('other@example.com', 'JEREME'),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::Completed, $service->poll());
    }

    /**
     * plex.tv being unreachable is not a fact about the user's account, so it
     * is no longer reported as one. It fails closed all the same: the raised
     * exception leaves poll() long before anything is stored.
     */
    public function testAnUnknownAccountIsRefusedRatherThanAssumedToBeTheOwner(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse(),
            // plex.tv will not say who this token belongs to.
            new Response(500, [], '{}'),
        ], $store);

        $service->start();

        try {
            $service->poll();
            self::fail('Expected the plex.tv failure to be raised.');
        } catch (PlexSignInException $e) {
            self::assertStringNotContainsString('own', $e->getMessage());
        }

        self::assertNull($store->token());
    }

    public function testAPlexTvFailureLeavesAnExistingConnectionAlone(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('already-connected');
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse(),
            new Response(500, [], '{}'),
        ], $store);

        $service->start();

        $this->expectException(PlexSignInException::class);

        try {
            $service->poll();
        } finally {
            self::assertSame('already-connected', $store->token());
        }
    }

    /**
     * The bug this change exists to remove: a server that cannot be reached
     * told the owner that their own account did not own it, sending them to
     * audit the one part of the system that was working.
     */
    public function testAnUnreachableServerIsNotReportedAsAnOwnershipFailure(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            // Nothing is listening at PLEX_SERVER_URL.
            new ConnectException('refused', new Request('GET', 'http://plex:32400/')),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::Unreachable, $service->poll());
        self::assertNull($store->token());
    }

    public function testAnUnreachableServerLeavesAnExistingConnectionAlone(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('already-connected');
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            new ConnectException('refused', new Request('GET', 'http://plex:32400/')),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::Unreachable, $service->poll());
        self::assertSame('already-connected', $store->token());
    }

    /**
     * A server that answers and declines the token has said something about the
     * account. Reporting that as a network fault would be this change's own
     * mistake, made in the other direction.
     */
    public function testAServerThatDeclinesTheTokenIsAnOwnershipFailure(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            new Response(401, [], ''),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::NotOwner, $service->poll());
        self::assertNull($store->token());
    }

    public function testAServerThatWillNotNameItsOwnerRefuses(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            // A root response with no owner in it.
            new Response(200, [], '<MediaContainer friendlyName="Anansi"/>'),
            $this->accountResponse('owner@example.com'),
        ], $store);

        $service->start();

        // Fails closed: a check that passes when it cannot run is not a check.
        self::assertSame(PlexSignInStatus::NotOwner, $service->poll());
        self::assertNull($store->token());
    }

    public function testARefusedSignInLeavesAnExistingConnectionAlone(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('already-connected');
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse(),
            $this->accountResponse('someone-else@example.com'),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::NotOwner, $service->poll());
        self::assertSame('already-connected', $store->token());
    }

    public function testMalformedCreateResponseIsRejected(): void
    {
        $this->expectException(PlexSignInException::class);

        $this->service([new Response(200, [], '{"code":"ABCD"}')])->start();
    }

    public function testSignOutClearsOnlyTheToken(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('granted-token');
        $clientId = $store->clientIdentifier();
        $secret = $store->signingSecret();

        $this->service([], $store)->signOut();

        self::assertNull($store->token());
        self::assertSame($clientId, $store->clientIdentifier());
        self::assertSame($secret, $store->signingSecret());
    }

    public function testIdentifyingHeadersAreSentAndAreStable(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $this->service([
            new Response(200, [], (string) json_encode(['id' => 1, 'code' => 'A', 'expiresIn' => 900])),
        ], $store)->start();

        $this->service([
            new Response(200, [], (string) json_encode(['id' => 2, 'code' => 'B', 'expiresIn' => 900])),
        ], $store)->start();

        self::assertCount(2, $this->sent);
        $first = $this->sent[0]->getHeaderLine('X-Plex-Client-Identifier');
        self::assertNotSame('', $first);
        self::assertSame($first, $this->sent[1]->getHeaderLine('X-Plex-Client-Identifier'));
        self::assertSame('Marquee', $this->sent[0]->getHeaderLine('X-Plex-Product'));
        self::assertSame('Marquee', $this->sent[0]->getHeaderLine('X-Plex-Device'));
    }

    public function testAuthorizationUrlCarriesTheCodeAndClientId(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $url = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
        ], $store)->start();

        self::assertStringStartsWith('https://app.plex.tv/auth#?', $url);
        self::assertStringContainsString('code=ABCD', $url);
        self::assertStringContainsString(rawurlencode($store->clientIdentifier()), $url);
        // No forward URL: nothing has to route back into Marquee.
        self::assertStringNotContainsString('forwardUrl', $url);

    }

    // ---- Signing in establishes the session ----

    public function testACompletedSignInEstablishesASession(): void
    {
        $session = new ArraySession();
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse(),
            $this->accountResponse('owner@example.com'),
        ], null, $session);

        $service->start();
        self::assertSame(PlexSignInStatus::Completed, $service->poll());

        $auth = new SessionAuthenticator(new AuthConfig(sessionDuration: 3600), $session);
        self::assertTrue($auth->isAuthenticated());
    }

    public function testARefusedSignInEstablishesNothing(): void
    {
        $session = new ArraySession();
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'a-guests-token'])),
            $this->ownerResponse(),
            $this->accountResponse('guest@example.com'),
        ], null, $session);

        $service->start();
        self::assertSame(PlexSignInStatus::NotOwner, $service->poll());

        $auth = new SessionAuthenticator(new AuthConfig(sessionDuration: 3600), $session);
        self::assertFalse($auth->isAuthenticated());
    }

    // ---- The recorded owner ----

    public function testAFirstSignInRecordsTheOwnerItVerified(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            $this->ownerResponse(),
            $this->accountResponse('owner@example.com'),
        ], $store);

        $service->start();
        self::assertSame(PlexSignInStatus::Completed, $service->poll());

        self::assertSame('owner@example.com', $store->owner());
    }

    /**
     * The reboot lockout this exists to prevent. Asking the Plex server who owns
     * it is right for a first connection; as a check on every login it would
     * make entering Marquee depend on the user's own hardware answering.
     *
     * The queue holds no response for the server, so a request to it would fail
     * the test rather than pass it quietly.
     */
    public function testALaterSignInDoesNotAskThePlexServer(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeOwner('owner@example.com');

        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'granted-token'])),
            // Straight to plex.tv: the server is never consulted.
            $this->accountResponse('owner@example.com'),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::Completed, $service->poll());
        self::assertSame('granted-token', $store->token());
    }

    public function testANonOwnerIsStillRefusedAgainstTheRecordedOwner(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeOwner('owner@example.com');

        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'a-guests-token'])),
            $this->accountResponse('guest@example.com'),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::NotOwner, $service->poll());
        self::assertNull($store->token());
    }

    /**
     * Disconnecting returns the install to its first-connection state, so the
     * next sign-in must establish ownership against the server again rather than
     * on a name remembered from whoever owned it last.
     */
    public function testDisconnectingForgetsTheRecordedOwner(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('a-token');
        $store->storeOwner('owner@example.com');
        $clientId = $store->clientIdentifier();

        $this->service([], $store)->signOut();

        $after = new PlexConnectionStore($this->dir);
        self::assertNull($after->token());
        self::assertNull($after->owner());
        // The device identity survives, as it always did.
        self::assertSame($clientId, $after->clientIdentifier());
    }

    /**
     * Signing in again is how a user replaces a token they revoked in their Plex
     * account. Keeping the stored one would leave the install unable to reach
     * Plex with no action left to take.
     */
    public function testSigningInAgainReplacesTheStoredToken(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('a-revoked-token');
        $store->storeOwner('owner@example.com');

        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 42, 'authToken' => 'a-fresh-token'])),
            $this->accountResponse('owner@example.com'),
        ], $store);

        $service->start();

        self::assertSame(PlexSignInStatus::Completed, $service->poll());
        self::assertSame('a-fresh-token', $store->token());
    }

    // ---- One outstanding request per session ----

    /**
     * Starting a sign-in is unauthenticated and calls plex.tv, holding a worker
     * for the round trip. Minting a request per call would turn every repeated
     * attempt into another call and another parked worker — and, in ordinary
     * use, would leave the Plex window the user is looking at unpolled.
     *
     * Only one create response is queued: a second call to Plex would throw.
     */
    public function testRepeatedStartsReuseTheOutstandingRequest(): void
    {
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
        ]);

        $first = $service->start();
        $second = $service->start();

        self::assertSame($first, $second);
        self::assertCount(1, $this->sent);
    }

    public function testAnExpiredRequestIsReplaced(): void
    {
        $session = new ArraySession();
        $service = $this->service([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 43, 'code' => 'WXYZ', 'expiresIn' => 900])),
        ], null, $session);

        $first = $service->start();
        $session->set('plex_pin_expires_at', time() - 1);
        $second = $service->start();

        self::assertNotSame($first, $second);
        self::assertCount(2, $this->sent);
    }

    public function testSeparateSessionsGetSeparateRequests(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $stack = $this->stack([
            new Response(200, [], (string) json_encode(['id' => 42, 'code' => 'ABCD', 'expiresIn' => 900])),
            new Response(200, [], (string) json_encode(['id' => 43, 'code' => 'WXYZ', 'expiresIn' => 900])),
        ]);

        $make = function (SessionInterface $session) use ($store, $stack): PlexSignInService {
            return new PlexSignInService(
                new PlexPinClient(new Client(['handler' => $stack]), $store, '1.2.3'),
                $store,
                $session,
                new PlexServerOwner(
                    new Client(['handler' => $stack]),
                    new PlexConfig('http://plex:32400', '', 10, 60),
                ),
                new SessionAuthenticator(new AuthConfig(sessionDuration: 3600), $session),
            );
        };

        $mine = $make(new ArraySession())->start();
        $theirs = $make(new ArraySession())->start();

        self::assertNotSame($mine, $theirs);
        self::assertCount(2, $this->sent);
    }

    /**
     * @param list<Response|ConnectException> $responses
     */
    private function service(
        array $responses,
        ?PlexConnectionStore $store = null,
        ?SessionInterface $session = null,
    ): PlexSignInService {
        $store ??= new PlexConnectionStore($this->dir);
        $stack = $this->stack($responses);

        $session ??= new ArraySession();

        return new PlexSignInService(
            new PlexPinClient(new Client(['handler' => $stack]), $store, '1.2.3'),
            $store,
            $session,
            // Shares the handler stack, so responses are queued in the order the
            // flow makes them: create, poll, the server's root, then plex.tv.
            new PlexServerOwner(
                new Client(['handler' => $stack]),
                new PlexConfig('http://plex:32400', '', 10, 60),
            ),
            new SessionAuthenticator(new AuthConfig(sessionDuration: 3600), $session),
        );
    }

    private function accountResponse(string $email, string $username = 'someone'): Response
    {
        return new Response(200, [], (string) json_encode(['username' => $username, 'email' => $email]));
    }

    /**
     * The server's root response, naming its owner.
     */
    private function ownerResponse(string $owner = 'owner@example.com'): Response
    {
        return new Response(200, [], '<MediaContainer friendlyName="Anansi" myPlexUsername="' . $owner . '"/>');
    }

    /**
     * @param list<Response|ConnectException> $responses
     */
    private function pinClient(array $responses, PlexConnectionStore $store): PlexPinClient
    {
        return new PlexPinClient(new Client(['handler' => $this->stack($responses)]), $store, '1.2.3');
    }

    /**
     * @param list<Response|ConnectException> $responses
     */
    private function stack(array $responses): HandlerStack
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                $this->sent[] = $request;

                return $handler($request, $options);
            };
        });

        return $stack;
    }
}
