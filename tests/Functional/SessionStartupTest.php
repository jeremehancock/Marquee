<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Support\Session\SessionInterface;
use App\Tests\AppTestCase;
use App\Tests\Support\SpySession;

/**
 * A route that reads no session state must not start one — asserted through the
 * whole stack, not just the gate.
 *
 * The unit test proves the middleware decides correctly. This proves the routes
 * it decided about really do read nothing: a template, a controller, or a
 * middleware further in could quietly reintroduce the dependency, and no
 * response would look any different. `wall.html.twig` is standalone today
 * rather than extending the layout, so it never asks CsrfGuard for a token; if
 * that ever changes, this is what says so.
 *
 * The stake is not a wasted file. The store collects expired sessions
 * probabilistically during session startup, so these two routes — the health
 * check every thirty seconds, the wall polling and rotating continuously —
 * were the dominant trigger for the sweep that evicted signed-in users.
 */
final class SessionStartupTest extends AppTestCase
{
    public function testTheHealthEndpointStartsNoSession(): void
    {
        $spy = new SpySession();
        $response = $this->get($this->appWith($spy), '/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($spy->wasStarted());
    }

    public function testTheWallStartsNoSession(): void
    {
        $spy = new SpySession();
        $response = $this->get($this->appWith($spy), '/wall');

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($spy->wasStarted(), 'the wall now reads a session it used to do without');
    }

    public function testTheWallsPollingRoutesStartNoSession(): void
    {
        foreach (['/wall/posters', '/wall/streams'] as $path) {
            $spy = new SpySession();
            $response = $this->get($this->appWith($spy), $path);

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertFalse($spy->wasStarted(), $path);
        }
    }

    /**
     * The way in still gets a session. `/login` renders the layout, which asks
     * for a token; without a session there would be nothing to render it into
     * and no way to sign in at all.
     */
    public function testTheSignInScreenStartsASession(): void
    {
        $spy = new SpySession();
        $response = $this->get($this->appWith($spy), '/login');

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($spy->wasStarted());
    }

    /**
     * Reachability is unchanged. This change moved only whether a session is
     * started; a public route that stopped being public, or a protected one
     * that started being reachable, would be the regression that matters.
     */
    public function testThePublicRoutesAreStillReachableAnonymously(): void
    {
        $app = $this->makeConnectedApp();

        foreach (['/health', '/login', '/wall', '/wall/posters', '/wall/streams'] as $path) {
            self::assertSame(200, $this->get($app, $path)->getStatusCode(), $path);
        }
    }

    public function testTheProtectedRoutesStillRedirectAnonymously(): void
    {
        $app = $this->makeConnectedApp();

        foreach (['/library/movies', '/orphans', '/plex', '/connect'] as $path) {
            $response = $this->get($app, $path);

            self::assertSame(302, $response->getStatusCode(), $path);
            self::assertSame('/login', $response->getHeaderLine('Location'), $path);
        }
    }

    /**
     * @return \Slim\App<\Psr\Container\ContainerInterface|null>
     */
    private function appWith(SpySession $spy): \Slim\App
    {
        return $this->makeConnectedApp([], [
            SessionInterface::class => static fn (): SessionInterface => $spy,
        ]);
    }
}
