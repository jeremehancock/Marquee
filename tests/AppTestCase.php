<?php

declare(strict_types=1);

namespace App\Tests;

use App\Auth\CsrfGuard;
use App\Auth\CsrfMiddleware;

use function App\buildContainer;
use function App\createApp;

use App\Plex\Connection\PlexConnectionStore;
use App\Support\Session\ArraySession;
use App\Support\Session\SessionInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class AppTestCase extends TestCase
{
    /** Data directory of the most recently built app, for the Plex helpers. */
    private string $dataDir = '';

    /** Whether the next makeApp() should start with Plex already connected. */
    private bool $connectNext = false;

    /**
     * @param array<string, string> $env
     * @param array<string, mixed>  $overrides
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    protected function makeApp(array $env = [], array $overrides = []): App
    {
        $defaults = [
            'SITE_TITLE' => 'Marquee',
            'AUTH_USERNAME' => 'admin',
            'AUTH_PASSWORD' => 'secret',
            'AUTH_BYPASS' => 'false',
            'SESSION_DURATION' => '3600',
            'DATA_DIR' => sys_get_temp_dir() . '/marquee-test-data',
            'DISPLAY_ERRORS' => 'false',
            // Reset Plex vars each time so one test's config cannot leak into another.
            // PLEX_TOKEN is cleared rather than set: it is no longer a credential,
            // and leaving it set would raise the "no longer used" notice everywhere.
            'PLEX_SERVER_URL' => '',
            'PLEX_TOKEN' => '',
            'PLEX_REMOVE_OVERLAY_LABEL' => 'false',
            'EXCLUDED_LIBRARIES' => '',
            'UPDATE_CHECK_ENABLED' => 'false',
        ];
        $merged = array_merge($defaults, $env);
        foreach ($merged as $key => $value) {
            putenv($key . '=' . $value);
        }

        $this->dataDir = rtrim($merged['DATA_DIR'], '/');

        // The data directory is shared between tests and survives the run, so a
        // token stored by one test would otherwise leave Plex connected for
        // every later one. Start each app with no stored connection; use
        // connectPlex() to give one back.
        @unlink($this->dataDir . '/plex-connection.json');

        // Before createApp(), not after: building the app resolves the gate
        // middleware, which resolves PlexConfig, which reads the store. A token
        // written later would be invisible for the whole life of this app.
        if ($this->connectNext) {
            $this->connectNext = false;
            $this->connectPlex();
        }

        // Use an in-memory session so the auth flow never touches PHP globals.
        $overrides = array_merge(
            [SessionInterface::class => static fn (): SessionInterface => new ArraySession()],
            $overrides,
        );

        return createApp(buildContainer($overrides));
    }

    /**
     * Build an app with Plex already connected.
     *
     * Connecting is a precondition for reaching almost any route, so most
     * functional tests want this rather than makeApp(). The token is written
     * after the container is built, which works because configuration resolves
     * lazily on the first request.
     *
     * @param array<string, string> $env
     * @param array<string, mixed>  $overrides
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    protected function makeConnectedApp(array $env = [], array $overrides = []): App
    {
        $this->connectNext = true;

        return $this->makeApp(array_merge(['PLEX_SERVER_URL' => 'http://plex:32400'], $env), $overrides);
    }

    /**
     * Store a Plex token, so the connection gate lets requests through.
     */
    protected function connectPlex(string $token = 'test-plex-token'): void
    {
        (new PlexConnectionStore($this->dataDir))->storeToken($token);
    }

    /**
     * Forget the stored Plex token, putting the app back behind the gate.
     */
    protected function disconnectPlex(): void
    {
        (new PlexConnectionStore($this->dataDir))->clearToken();
    }

    /**
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    protected function get(App $app, string $path): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path);

        return $app->handle($request);
    }

    /**
     * Post a form the way a browser would — carrying the CSRF token that
     * Marquee rendered into the form it came from.
     *
     * @param App<\Psr\Container\ContainerInterface|null> $app
     * @param array<string, string>                       $data
     */
    protected function postForm(App $app, string $path, array $data): ResponseInterface
    {
        return $this->postFormWithoutToken(
            $app,
            $path,
            $data + [CsrfMiddleware::FIELD => $this->csrfToken($app)],
        );
    }

    /**
     * Post with nothing but the given fields, so a missing or wrong token can be
     * exercised deliberately.
     *
     * @param App<\Psr\Container\ContainerInterface|null> $app
     * @param array<string, string>                       $data
     */
    protected function postFormWithoutToken(App $app, string $path, array $data): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query($data));
        $request->getBody()->rewind();

        return $app->handle($request);
    }

    /**
     * The token this app's session holds, as a rendered page would carry it.
     *
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    protected function csrfToken(App $app): string
    {
        $container = $app->getContainer();
        self::assertNotNull($container);
        /** @var CsrfGuard $guard */
        $guard = $container->get(CsrfGuard::class);

        return $guard->token();
    }
}
