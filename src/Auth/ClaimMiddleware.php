<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Claim\ClaimService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Holds an unclaimed install shut until someone proves they had access to the
 * host.
 *
 * This runs *outside* the authentication gate, which is the whole point of it
 * being a separate middleware rather than a branch inside one of the others.
 * Authentication sends an anonymous visitor to `/login`, and `/login` offers a
 * Plex sign-in — so a gate that ran inside it would never see the visitor this
 * one exists to stop. On an unclaimed install the sign-in screen is exactly what
 * must not be reachable: ownership is verified against the configured server, and
 * on an unclaimed install nobody has configured one yet, so whoever arrives first
 * would name their own and be granted the install.
 *
 * What stays open is what stays open under the connection gate too, minus the
 * sign-in: the health endpoint, so a container is not reported unhealthy before
 * anyone has claimed it; the manifest and static assets, which grant nothing; and
 * the Poster Wall, which is specified to run unattended. The wall is safe here
 * without a special case — posters arrive only through an import, an import needs
 * a connection, and an unclaimed install has none, so there is nothing for it to
 * show.
 */
final class ClaimMiddleware implements MiddlewareInterface
{
    public const CLAIM_PATH = '/claim';

    /**
     * Reachable while the install is unclaimed.
     *
     * Deliberately shorter than the connection gate's list. That gate leaves
     * `/login` and the sign-in routes open because signing in is how its
     * precondition gets satisfied; here, signing in is the thing being prevented.
     *
     * @var list<string>
     */
    private const OPEN_PATHS = [
        self::CLAIM_PATH,
        '/health',
        '/manifest.webmanifest',
        '/wall',
    ];

    /**
     * @var list<string>
     */
    private const OPEN_PREFIXES = ['/assets/', '/wall/'];

    public function __construct(private readonly ClaimService $claim)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->claim->isClaimed()) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        if ($this->isOpen($path)) {
            return $handler->handle($request);
        }

        return (new Response())
            ->withHeader('Location', self::CLAIM_PATH)
            ->withStatus(302);
    }

    private function isOpen(string $path): bool
    {
        if (in_array($path, self::OPEN_PATHS, true)) {
            return true;
        }

        foreach (self::OPEN_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
