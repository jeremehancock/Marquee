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

    /**
     * Bypass removes the login, which makes a forged request worth more rather
     * than less. The check stays on.
     */
    public function testBypassDoesNotDisableTheCheck(): void
    {
        $app = $this->connectedApp(['AUTH_BYPASS' => 'true']);

        $response = $this->postFormWithoutToken($app, '/orphans/delete-all', []);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testReadingNeedsNoToken(): void
    {
        $app = $this->connectedApp(['AUTH_BYPASS' => 'true']);

        self::assertSame(200, $this->get($app, '/orphans')->getStatusCode());
        self::assertSame(200, $this->get($app, '/health')->getStatusCode());
    }

    public function testARenderedFormCarriesTheToken(): void
    {
        $app = $this->connectedApp(['AUTH_BYPASS' => 'true']);
        $body = (string) $this->get($app, '/connect')->getBody();

        self::assertStringContainsString('name="' . CsrfMiddleware::FIELD . '"', $body);
        self::assertStringContainsString($this->csrfToken($app), $body);
    }

    public function testTheLoginFormCarriesTheToken(): void
    {
        $app = $this->makeApp();

        self::assertStringContainsString(
            'name="' . CsrfMiddleware::FIELD . '"',
            (string) $this->get($app, '/login')->getBody(),
        );
    }

    /**
     * URLs are logged, shared, and sent as referrers, so the token must never
     * ride in one.
     */
    public function testTheTokenIsNotPlacedInAUrl(): void
    {
        $app = $this->connectedApp(['AUTH_BYPASS' => 'true']);
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
     * container discards every session, and login is the only state-changing
     * route a user reaches with no session behind it — an error page there reads
     * as a broken install rather than as a page to submit again.
     */
    public function testALoginWithAStaleTokenIsExplainedRatherThanErrored(): void
    {
        $app = $this->makeApp();

        $response = $this->postFormWithoutToken($app, '/login', [
            'username' => 'admin',
            'password' => 'secret',
            CsrfMiddleware::FIELD => str_repeat('a', 64),
        ]);
        $body = (string) $response->getBody();

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('expired', $body);
        // The login form, not an error page.
        self::assertStringContainsString('name="password"', $body);
        self::assertStringNotContainsString('could not be verified', $body);
    }

    public function testALoginWithAStaleTokenAuthenticatesNobody(): void
    {
        $app = $this->makeApp();

        $this->postFormWithoutToken($app, '/login', [
            'username' => 'admin',
            'password' => 'secret',
            CsrfMiddleware::FIELD => 'stale',
        ]);

        // Still gated: the credentials were never looked at.
        $response = $this->get($app, '/');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testACorrectTokenLetsTheLoginThrough(): void
    {
        $app = $this->makeApp();

        $response = $this->postForm($app, '/login', ['username' => 'admin', 'password' => 'secret']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, string> $env
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function connectedApp(array $env = []): App
    {
        return $this->makeConnectedApp(
            $env + ['AUTH_BYPASS' => 'true'],
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
