<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\AuthMiddleware;
use App\Auth\SessionAuthenticator;
use App\Config\AuthConfig;
use App\Tests\Support\SpySession;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * The gate, and the invariant that keeps its optimisation from becoming a hole.
 *
 * Two separate questions live here: whether an anonymous visitor may reach a
 * route, and whether serving it needs a session. Conflating them is what let the
 * health check — polled every thirty seconds, reading nothing — become the
 * dominant trigger for the collection sweep that signed real users out. Pulling
 * them apart is safe only while the session-less set stays inside the public
 * set, which is what most of this file is about.
 */
final class AuthMiddlewareTest extends TestCase
{
    private bool $reached = false;

    protected function setUp(): void
    {
        $this->reached = false;
    }

    /**
     * The guardrail. Skipping session startup is nested inside the public
     * check, so a session-less path that is not public cannot skip the gate —
     * but a path listed as session-less and *not* public would still be a
     * mistake worth catching, because it would silently stop being served the
     * way its author intended.
     */
    public function testEverySessionlessPathIsAlsoPublic(): void
    {
        foreach (AuthMiddleware::SESSIONLESS_PATHS as $path) {
            $session = new SpySession();
            $response = $this->gate($path, $session);

            self::assertSame(200, $response->getStatusCode(), $path . ' is session-less but not public');
            self::assertTrue($this->reached, $path);
            $this->reached = false;
        }
    }

    public function testEverySessionlessPrefixIsAlsoPublic(): void
    {
        foreach (AuthMiddleware::SESSIONLESS_PREFIXES as $prefix) {
            $session = new SpySession();
            $response = $this->gate($prefix . 'anything', $session);

            self::assertSame(200, $response->getStatusCode(), $prefix . ' is session-less but not public');
            self::assertTrue($this->reached, $prefix);
            $this->reached = false;
        }
    }

    public function testASessionlessPathStartsNoSession(): void
    {
        foreach (AuthMiddleware::SESSIONLESS_PATHS as $path) {
            $session = new SpySession();
            $this->gate($path, $session);

            self::assertFalse($session->wasStarted(), $path . ' started a session it does not read');
        }
    }

    public function testASessionlessPrefixStartsNoSession(): void
    {
        foreach (AuthMiddleware::SESSIONLESS_PREFIXES as $prefix) {
            $session = new SpySession();
            $this->gate($prefix . 'anything', $session);

            self::assertFalse($session->wasStarted(), $prefix . ' started a session it does not read');
        }
    }

    /**
     * The routes that hold or read a sign-in, render a token, or destroy a
     * session all need one. Skipping startup for these would break the way in.
     */
    public function testThePublicRoutesThatNeedASessionStillGetOne(): void
    {
        foreach (['/login', '/logout', '/plex/connection/sign-in', '/plex/connection/status'] as $path) {
            $session = new SpySession();
            $response = $this->gate($path, $session);

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertTrue($session->wasStarted(), $path . ' needs a session and did not get one');
        }
    }

    public function testAProtectedRouteStillRedirectsWhenAnonymous(): void
    {
        $session = new SpySession();
        $response = $this->gate('/orphans', $session);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertFalse($this->reached);
    }

    /**
     * A protected route must always get a session, because deciding whether it
     * is authenticated requires reading one. This is the assertion that would
     * fail first if the session-less early return ever escaped the public
     * branch it is nested inside.
     */
    public function testAProtectedRouteAlwaysStartsASession(): void
    {
        foreach (['/orphans', '/movies', '/plex/import', '/connect'] as $path) {
            $session = new SpySession();
            $this->gate($path, $session);

            self::assertTrue($session->wasStarted(), $path . ' was gated without reading a session');
        }
    }

    public function testAnAuthenticatedRequestReachesAProtectedRoute(): void
    {
        $session = new SpySession();
        (new SessionAuthenticator(new AuthConfig(sessionDuration: 3600), $session))->establish();

        $response = $this->gate('/orphans', $session);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->reached);
    }

    private function gate(string $path, SpySession $session): ResponseInterface
    {
        $middleware = new AuthMiddleware(
            new SessionAuthenticator(new AuthConfig(sessionDuration: 3600), $session),
            $session,
        );

        $handler = new class () implements RequestHandlerInterface {
            public bool $reached = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->reached = true;

                return new Response();
            }
        };

        $response = $middleware->process(
            (new ServerRequestFactory())->createServerRequest('GET', $path),
            $handler,
        );

        $this->reached = $handler->reached;

        return $response;
    }
}
