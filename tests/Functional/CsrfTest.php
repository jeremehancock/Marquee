<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Auth\CsrfMiddleware;
use App\Plex\PlexClient;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Every state-changing route, proved to refuse a request that cannot show it
 * came from a Marquee page.
 *
 * `SameSite=Lax` already stops a cross-*site* request, but "site" ignores the
 * port, so another service on the same host address is same-site with Marquee
 * and its requests still carry the session cookie. That is what these cover.
 */
final class CsrfTest extends AppTestCase
{
    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function stateChangingRoutes(): array
    {
        return [
            'delete a poster' => ['/library/movies/delete', ['filename' => 'a.jpg']],
            'send to Plex' => ['/library/movies/send-to-plex', ['filename' => 'a.jpg']],
            'fetch from Plex' => ['/library/movies/fetch-from-plex', ['filename' => 'a.jpg']],
            'change by url' => ['/library/movies/change/url', ['filename' => 'a.jpg', 'url' => 'http://x/a.jpg']],
            'import' => ['/plex/import', []],
            'delete an orphan' => ['/orphans/delete', ['category' => 'movies', 'filename' => 'a.jpg']],
            'delete every orphan' => ['/orphans/delete-all', []],
            'connect to Plex' => ['/plex/connection/sign-in', []],
            'disconnect from Plex' => ['/plex/connection/sign-out', []],
        ];
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('stateChangingRoutes')]
    public function testAStateChangingRouteRefusesARequestWithNoToken(string $path, array $data): void
    {
        $response = $this->postFormWithoutToken($this->connectedApp(), $path, $data);

        self::assertSame(403, $response->getStatusCode(), $path);
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('stateChangingRoutes')]
    public function testAStateChangingRouteRefusesAWrongToken(string $path, array $data): void
    {
        $response = $this->postFormWithoutToken(
            $this->connectedApp(),
            $path,
            $data + [CsrfMiddleware::FIELD => str_repeat('a', 64)],
        );

        self::assertSame(403, $response->getStatusCode(), $path);
    }

    /**
     * A token minted for one browser must be worthless in another, or the check
     * proves nothing about where the request came from.
     */
    public function testATokenFromAnotherSessionIsRefused(): void
    {
        $theirs = $this->csrfToken($this->connectedApp());

        $response = $this->postFormWithoutToken(
            $this->connectedApp(),
            '/orphans/delete-all',
            [CsrfMiddleware::FIELD => $theirs],
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testTheTokenIsAcceptedAsAHeader(): void
    {
        $app = $this->connectedApp();

        $response = $this->postWithHeaderToken($app, '/orphans/delete-all');

        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testReadingNeedsNoToken(): void
    {
        $app = $this->connectedApp();

        self::assertSame(200, $this->get($app, '/orphans')->getStatusCode());
        self::assertSame(200, $this->get($app, '/health')->getStatusCode());
    }

    public function testARenderedFormCarriesTheToken(): void
    {
        $app = $this->connectedApp();
        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('name="' . CsrfMiddleware::FIELD . '"', $body);
        self::assertStringContainsString($this->csrfToken($app), $body);
    }

    /**
     * The sign-in screen is reached with no session, and its one action is a
     * scripted request — so the token has to be on the page for the script to
     * find, not in a form.
     */
    public function testTheSignInScreenCarriesTheToken(): void
    {
        $app = $this->makeApp();

        self::assertStringContainsString(
            'name="csrf-token" content="' . $this->csrfToken($app) . '"',
            (string) $this->get($app, '/login')->getBody(),
        );
    }

    /**
     * URLs are logged, shared, and sent as referrers, so the token must never
     * ride in one.
     */
    public function testTheTokenIsNotPlacedInAUrl(): void
    {
        $app = $this->connectedApp();
        $token = $this->csrfToken($app);

        foreach (['/connect', '/orphans', '/'] as $path) {
            $body = (string) $this->get($app, $path)->getBody();
            self::assertStringNotContainsString('?' . CsrfMiddleware::FIELD . '=', $body, $path);
            self::assertStringNotContainsString('&' . CsrfMiddleware::FIELD . '=', $body, $path);
            self::assertStringNotContainsString('href="' . $token, $body, $path);
        }
    }

    /**
     * The one refusal that is explained rather than raised. Recreating the
     * container discards every session, and starting a sign-in is the only
     * state-changing route a user reaches with no session behind it — and the
     * one they cannot get past it on, because it is the way in.
     *
     * The explanation is JSON because the caller is a `fetch` that reads `error`
     * out of the body. An HTML error page would fail to parse and surface as a
     * generic failure, telling the user nothing they can act on.
     */
    public function testASignInWithAStaleTokenIsExplainedRatherThanErrored(): void
    {
        $app = $this->makeApp();

        $response = $this->postFormWithoutToken($app, '/plex/connection/sign-in', [
            CsrfMiddleware::FIELD => str_repeat('a', 64),
        ]);
        $body = (string) $response->getBody();

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $decoded = json_decode($body, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('error', $decoded);
        self::assertIsString($decoded['error']);
        self::assertStringContainsString('expired', $decoded['error']);
        self::assertStringNotContainsString('could not be verified', $body);
    }

    public function testASignInWithAStaleTokenStartsNothingAndAuthenticatesNobody(): void
    {
        $app = $this->makeApp();

        $this->postFormWithoutToken($app, '/plex/connection/sign-in', [
            CsrfMiddleware::FIELD => 'stale',
        ]);

        // No authorization request was started, so there is nothing to poll...
        self::assertStringContainsString(
            'not_started',
            (string) $this->get($app, '/plex/connection/status')->getBody(),
        );

        // ...and nobody is signed in.
        $response = $this->get($app, '/');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, string> $env
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function connectedApp(array $env = []): App
    {
        return $this->makeSignedInApp(
            $env,
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );
    }

    /**
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    private function postWithHeaderToken(App $app, string $path): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withHeader(CsrfMiddleware::HEADER, $this->csrfToken($app));

        return $app->handle($request);
    }
}
