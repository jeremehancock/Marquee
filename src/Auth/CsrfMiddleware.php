<?php

declare(strict_types=1);

namespace App\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Response;

/**
 * Refuses a state-changing request that does not prove it came from a page
 * Marquee rendered.
 *
 * `SameSite=Lax` on the session cookie already stops a cross-*site* request, but
 * "site" ignores the port: every other service on the same host address is
 * same-site with Marquee, and its requests still carry the cookie. A self-hosted
 * machine runs many, so that is the case this closes.
 *
 * Reading is untouched. `GET`, `HEAD`, and `OPTIONS` need no token, so the
 * poster wall, the health endpoint, and every page load are unaffected.
 *
 * The token is accepted from a form field or a header. Forms carry the field,
 * which also serves the scripted requests that build their body from a form;
 * the requests that build a synthetic body or send none have no form to draw
 * from, and use the header. Neither carrier alone covers every call site.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public const FIELD = '_token';
    public const HEADER = 'X-CSRF-Token';

    /**
     * Methods that only read, and so need no proof of origin.
     *
     * @var list<string>
     */
    private const SAFE = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly CsrfGuard $guard)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE, true)) {
            return $handler->handle($request);
        }

        if ($this->guard->matches($this->submitted($request))) {
            return $handler->handle($request);
        }

        // Starting a sign-in is the one refusal that gets explained rather than
        // raised. PHP sessions live in the container's /tmp, so recreating the
        // container discards them all. Everywhere else that is invisible: a
        // stale page posting afterwards is unauthenticated, and AuthMiddleware
        // redirects it long before this check. Starting a sign-in is the only
        // state-changing route reachable with no session behind it, so it is the
        // only place a user meets a dead token — and it is the one route they
        // cannot get past it on, because it is the way in.
        //
        // The explanation is JSON because the caller is a `fetch` that reads
        // `error` out of the body and shows it. An HTML error page here would
        // fail to parse and surface as "Could not start sign-in", which tells
        // the user nothing they can act on.
        //
        // It refuses exactly as hard: the handler is never reached, so no
        // authorization request is started and nobody is authenticated. Only the
        // rendering differs.
        if ($request->getUri()->getPath() === '/plex/connection/sign-in') {
            $response = (new Response())
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode(
                ['error' => 'This page expired before you signed in. Reload the page and try again.'],
                JSON_THROW_ON_ERROR,
            ));

            return $response;
        }

        throw new HttpForbiddenException(
            $request,
            'This request could not be verified as coming from Marquee. Reload the page and try again.',
        );
    }

    /**
     * The token the request offers, from the form field or the header.
     */
    private function submitted(ServerRequestInterface $request): mixed
    {
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body[self::FIELD])) {
            return $body[self::FIELD];
        }

        $header = $request->getHeaderLine(self::HEADER);

        return $header !== '' ? $header : null;
    }
}
