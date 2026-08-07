<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\CsrfGuard;
use App\Auth\CsrfMiddleware;
use App\Support\Session\ArraySession;
use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;

/**
 * The gate itself. The `/login` carve-out renders a template and so is covered
 * functionally, where the Twig environment is the real one; everything here is
 * the part that decides pass or refuse.
 */
final class CsrfMiddlewareTest extends TestCase
{
    private bool $reached = false;

    protected function setUp(): void
    {
        $this->reached = false;
    }

    public function testReadingNeedsNoToken(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS', 'get', 'head'] as $method) {
            $this->reached = false;
            $response = $this->pass($this->request($method, '/orphans'));

            self::assertTrue($this->reached, $method);
            self::assertSame(200, $response->getStatusCode(), $method);
        }
    }

    public function testAPostWithNoTokenIsRefused(): void
    {
        $this->expectException(HttpForbiddenException::class);

        try {
            $this->pass($this->request('POST', '/orphans/delete'));
        } finally {
            self::assertFalse($this->reached, 'the handler must not be reached');
        }
    }

    public function testAPostWithTheWrongTokenIsRefused(): void
    {
        $session = new ArraySession();
        (new CsrfGuard($session))->token();

        $this->expectException(HttpForbiddenException::class);
        $this->pass(
            $this->request('POST', '/orphans/delete')->withParsedBody([CsrfMiddleware::FIELD => 'wrong']),
            $session,
        );
    }

    public function testAPostCarryingAnotherSessionsTokenIsRefused(): void
    {
        $mine = new ArraySession();
        (new CsrfGuard($mine))->token();
        $theirs = (new CsrfGuard(new ArraySession()))->token();

        $this->expectException(HttpForbiddenException::class);
        $this->pass(
            $this->request('POST', '/orphans/delete')->withParsedBody([CsrfMiddleware::FIELD => $theirs]),
            $mine,
        );
    }

    public function testAPostWithTheTokenInTheFieldIsAccepted(): void
    {
        $session = new ArraySession();
        $token = (new CsrfGuard($session))->token();

        $response = $this->pass(
            $this->request('POST', '/orphans/delete')->withParsedBody([CsrfMiddleware::FIELD => $token]),
            $session,
        );

        self::assertTrue($this->reached);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * The carrier for scripted requests that build their own body and so have
     * no form field to draw from.
     */
    public function testAPostWithTheTokenInTheHeaderIsAccepted(): void
    {
        $session = new ArraySession();
        $token = (new CsrfGuard($session))->token();

        $response = $this->pass(
            $this->request('POST', '/orphans/delete-all')->withHeader(CsrfMiddleware::HEADER, $token),
            $session,
        );

        self::assertTrue($this->reached);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * A body without the field falls through to the header rather than refusing
     * outright, so a form post and a scripted post do not need different routes.
     */
    public function testABodyWithoutTheFieldStillConsultsTheHeader(): void
    {
        $session = new ArraySession();
        $token = (new CsrfGuard($session))->token();

        $this->pass(
            $this->request('POST', '/orphans/delete')
                ->withParsedBody(['filename' => 'a.jpg'])
                ->withHeader(CsrfMiddleware::HEADER, $token),
            $session,
        );

        self::assertTrue($this->reached);
    }

    public function testAMethodThatIsNeitherSafeNorPostIsAlsoChecked(): void
    {
        $this->expectException(HttpForbiddenException::class);
        $this->pass($this->request('DELETE', '/orphans/delete'));
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $path);
    }

    private function pass(ServerRequestInterface $request, ?ArraySession $session = null): ResponseInterface
    {
        $middleware = new CsrfMiddleware(
            new CsrfGuard($session ?? new ArraySession()),
            // Never rendered on any path this class exercises.
            Twig::create(dirname(__DIR__, 3) . '/templates', ['cache' => false]),
        );

        return $middleware->process($request, $this->handler());
    }

    private function handler(): RequestHandlerInterface
    {
        $reached = function (): void {
            $this->reached = true;
        };

        return new class ($reached) implements RequestHandlerInterface {
            public function __construct(private readonly Closure $reached)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                ($this->reached)();

                return new Response(200);
            }
        };
    }
}
