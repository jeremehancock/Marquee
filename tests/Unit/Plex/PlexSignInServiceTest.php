<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Plex\Connection\PlexConnectionStore;
use App\Plex\Connection\PlexPinClient;
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
        ], $store, $starter);

        $service->start();

        // A different browser session shares the store but not the session, so
        // it has no outstanding request and nothing it can supply to claim one.
        $other = new PlexSignInService(
            $this->pinClient([], $store),
            $store,
            new ArraySession(),
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

    /**
     * @param list<Response|ConnectException> $responses
     */
    private function service(
        array $responses,
        ?PlexConnectionStore $store = null,
        ?SessionInterface $session = null,
    ): PlexSignInService {
        $store ??= new PlexConnectionStore($this->dir);

        return new PlexSignInService(
            $this->pinClient($responses, $store),
            $store,
            $session ?? new ArraySession(),
        );
    }

    /**
     * @param list<Response|ConnectException> $responses
     */
    private function pinClient(array $responses, PlexConnectionStore $store): PlexPinClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                $this->sent[] = $request;

                return $handler($request, $options);
            };
        });

        return new PlexPinClient(new Client(['handler' => $stack]), $store, '1.2.3');
    }
}
