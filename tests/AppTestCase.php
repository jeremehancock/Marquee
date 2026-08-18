<?php

declare(strict_types=1);

namespace App\Tests;

use App\Auth\CsrfGuard;
use App\Auth\CsrfMiddleware;
use App\Auth\SessionAuthenticator;

use function App\buildContainer;
use function App\createApp;

use App\Plex\Connection\PlexConnectionStore;
use App\Settings\SettingKey;
use App\Settings\SettingsSeeder;
use App\Settings\SettingsStore;
use App\Support\Session\ArraySession;
use App\Support\Session\SessionInterface;
use PHPUnit\Framework\Attributes\After;
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

    /** Whether the next makeApp() should start unclaimed. */
    private bool $unclaimedNext = false;

    /**
     * Variables supersede() set, cleared after each test.
     *
     * @var list<string>
     */
    private array $supersededNames = [];

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
            // Cleared rather than set. These are no longer credentials; leaving
            // any of them set would raise the "no longer used" notice on every
            // render of the sign-in screen.
            'AUTH_USERNAME' => '',
            'AUTH_PASSWORD' => '',
            'AUTH_BYPASS' => '',
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

        // Split the given environment in two. Anything the settings store owns
        // is used to seed the store and then taken back out of the environment;
        // everything else is exported as a variable.
        //
        // A test is configuring an install, not its compose file. Leaving these
        // set would also raise the superseded-settings notice on every page of
        // every test — the same reason the AUTH_* variables above are cleared
        // rather than set.
        //
        // Use supersede() where a leftover compose variable is the point.
        $owned = [];
        foreach (SettingKey::all() as $key) {
            $owned[$key->variable()] = true;
        }

        $settings = [];
        foreach ($merged as $key => $value) {
            if (isset($owned[$key])) {
                $settings[$key] = $value;

                continue;
            }
            putenv($key . '=' . $value);
        }

        $this->dataDir = rtrim($merged['DATA_DIR'], '/');

        // The data directory is shared between tests and survives the run, so a
        // token stored by one test would otherwise leave Plex connected for
        // every later one. Start each app with no stored connection; use
        // connectPlex() to give one back.
        @unlink($this->dataDir . '/plex-connection.json');

        // The settings store for the same reason, and a sharper one: it is
        // seeded from the environment exactly once and then never again, so the
        // first test to build an app would otherwise fix the configuration for
        // the whole run and every later test's $env would be silently ignored.
        @unlink($this->dataDir . '/settings.json');

        // Seed the store the way a first boot does — through the real seeder, so
        // a value means here exactly what it would mean in an install — and then
        // clear the variables again.
        foreach ($settings as $key => $value) {
            putenv($key . '=' . $value);
        }
        (new SettingsSeeder(new SettingsStore($this->dataDir)))->seed();
        foreach (array_keys($settings) as $key) {
            putenv($key);
        }

        // Claimed unless a test says otherwise. A test configures an install
        // that already exists; leaving it unclaimed would send every request to
        // the claim screen, which is the first-run state and almost never what
        // is under test. Use makeUnclaimedApp() where it is.
        //
        // Written straight to the connection store rather than through
        // ClaimService, so that this cannot mask a bug in how a real claim is
        // recorded — the tests that exercise claiming build their own.
        @unlink($this->dataDir . '/claim-code.txt');
        @unlink($this->dataDir . '/claim-attempts.json');
        if ($this->unclaimedNext) {
            $this->unclaimedNext = false;
        } else {
            (new PlexConnectionStore($this->dataDir))->markClaimed();
        }

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
     * Build an app that has never been claimed — a genuinely first-run install.
     *
     * @param array<string, string> $env
     * @param array<string, mixed>  $overrides
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    protected function makeUnclaimedApp(array $env = [], array $overrides = []): App
    {
        $this->unclaimedNext = true;

        // PLEX_SERVER_URL would seed the store and be read as an install
        // configured before claiming existed, which is the upgrade path rather
        // than a first run. Cleared here so an unclaimed app is unclaimed.
        return $this->makeApp(array_merge($env, ['PLEX_SERVER_URL' => '']), $overrides);
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
     * Build an app with Plex connected and a session already signed in.
     *
     * What most functional tests want: nearly every route is behind both gates,
     * and the tests that exercise them are not about how a session is obtained.
     * Use makeConnectedApp() instead where being anonymous is the point.
     *
     * @param array<string, string> $env
     * @param array<string, mixed>  $overrides
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    protected function makeSignedInApp(array $env = [], array $overrides = []): App
    {
        $app = $this->makeConnectedApp($env, $overrides);
        $this->signIn($app);

        return $app;
    }

    /**
     * Mark this app's session authenticated, as a verified Plex sign-in does.
     *
     * Goes through SessionAuthenticator rather than writing session keys
     * directly, so the tests establish a session exactly the way the application
     * does — identifier regeneration included — and cannot drift from it.
     *
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    protected function signIn(App $app): void
    {
        $container = $app->getContainer();
        self::assertNotNull($container);
        /** @var SessionAuthenticator $authenticator */
        $authenticator = $container->get(SessionAuthenticator::class);
        $authenticator->establish();
    }

    /**
     * Leave a variable set in the environment after the store has been seeded,
     * standing in for a compose file nobody has cleaned up yet.
     *
     * Call it after building the app. The superseded report reads the
     * environment when the page is rendered, not when the container is built,
     * so a variable set here is seen by the next request and — crucially —
     * cannot have influenced the configuration that request resolves.
     *
     * @param array<string, string> $variables
     */
    protected function supersede(array $variables): void
    {
        foreach ($variables as $name => $value) {
            $this->supersededNames[] = $name;
            putenv($name . '=' . $value);
        }
    }

    /**
     * Clear anything supersede() set.
     *
     * `putenv()` is process-global and PHPUnit runs a single process, so a
     * variable left behind here becomes a superseded-settings notice on a later
     * test that never asked for one. An `#[After]` hook rather than tearDown(),
     * so that subclasses keep their own teardown without chaining to this.
     */
    #[After]
    protected function clearSuperseded(): void
    {
        foreach ($this->supersededNames as $name) {
            putenv($name);
        }

        $this->supersededNames = [];
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
     * A field's value may be a list, because a form field may be: a set of
     * checkboxes sharing one name posts every ticked value, which is how the
     * settings screen submits its library exclusions.
     *
     * @param App<\Psr\Container\ContainerInterface|null> $app
     * @param array<string, string|list<string>>          $data
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
     * @param array<string, string|list<string>>          $data
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
