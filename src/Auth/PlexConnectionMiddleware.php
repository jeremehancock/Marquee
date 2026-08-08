<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\PlexConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Requires a connected Plex server before the application may be used,
 * redirecting to the connection screen until there is one.
 *
 * Marquee does nothing without Plex — it imports posters from it and sends them
 * back — so leaving the interface open while disconnected buries the one action
 * a new install needs behind pages that cannot work yet. The gate states the
 * precondition once, and connecting becomes the first thing a new install asks
 * for rather than something to discover.
 *
 * This gate and the authentication gate send a visitor to the same screen,
 * because one sign-in satisfies both. A new install is therefore asked for one
 * thing rather than two in sequence. They differ only in which URL they use for
 * it: authentication sends you to `/login`, and this one — which only ever sees
 * a visitor who already has a session — sends you to `/connect`.
 *
 * It turns on the whole connection, not just the token: a stored credential
 * with no server address cannot reach Plex either, and the connection screen is
 * where that difference gets explained.
 *
 * The check is configuration only and never contacts Plex. This runs on every
 * request, and with a ten-second connect timeout a reachability probe here
 * would stall the entire application whenever the server was down.
 */
final class PlexConnectionMiddleware implements MiddlewareInterface
{
    /**
     * The connection view. This gate runs inside the authentication one, so
     * anyone it turns away already has a session — which is why they are sent
     * here rather than to the sign-in URL.
     */
    public const CONNECTION_PATH = '/connect';

    /**
     * Reachable while Plex is not connected.
     *
     * The connection screen and its actions, for the obvious reason. The public
     * paths, because gating them would make no sense — a health check that
     * failed until Plex was connected would report the container unhealthy on
     * first boot.
     *
     * @var list<string>
     */
    private array $openPaths = [
        AuthMiddleware::SIGN_IN_PATH,
        self::CONNECTION_PATH,
        '/health',
        '/logout',
        '/manifest.webmanifest',
        '/wall',
    ];

    /**
     * The Poster Wall is exempt deliberately, not by oversight. It is specified
     * to run unattended on a display without anyone signing in, and a gate in
     * front of it would break that contract exactly when it matters most — a
     * wall left running while someone reconfigures Plex.
     *
     * @var list<string>
     */
    private array $openPrefixes = ['/assets/', '/wall/', '/plex/connection/'];

    public function __construct(private readonly PlexConfig $plex)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($this->isOpen($path) || $this->plex->isConfigured()) {
            return $handler->handle($request);
        }

        return (new Response())
            ->withHeader('Location', self::CONNECTION_PATH)
            ->withStatus(302);
    }

    private function isOpen(string $path): bool
    {
        if (in_array($path, $this->openPaths, true)) {
            return true;
        }

        foreach ($this->openPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
