<?php

declare(strict_types=1);

namespace App\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Response;
use Slim\Views\Twig;

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
 *
 * This runs while `AUTH_BYPASS` is enabled too. Bypass removes the login, which
 * makes a forged request worth more rather than less.
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

    public function __construct(
        private readonly CsrfGuard $guard,
        private readonly Twig $twig,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE, true)) {
            return $handler->handle($request);
        }

        if ($this->guard->matches($this->submitted($request))) {
            return $handler->handle($request);
        }

        // Logging in is the one refusal that gets explained rather than raised.
        // PHP sessions live in the container's /tmp, so recreating the container
        // discards them all. Everywhere else that is invisible: a stale page
        // posting afterwards is unauthenticated, and AuthMiddleware redirects it
        // to the login page long before this check. Login is the only
        // state-changing route reachable with no session behind it, so it is the
        // only place a user meets a dead token — and an error page there reads
        // as a broken install rather than as a page to submit again. The gap is
        // widest with AUTH_BYPASS on, where no auth redirect absorbs it first.
        //
        // It refuses exactly as hard: the handler is never reached, so nobody is
        // authenticated. Only the rendering differs.
        if ($request->getUri()->getPath() === '/login') {
            // 401, matching the response a wrong password already produces:
            // both mean "you are not signed in, submit the form again".
            return $this->twig->render(
                (new Response())->withStatus(401),
                'login.html.twig',
                ['error' => 'This page expired before it was submitted. Try signing in again.'],
            );
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
