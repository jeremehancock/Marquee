<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Poster\Poster;
use App\Poster\Wall\NowPlayingService;
use App\Poster\Wall\NowPlayingTile;
use App\Poster\Wall\PosterWallService;
use App\Poster\Wall\StreamToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

/**
 * The Poster Wall: a full-screen page, a random-batch endpoint that feeds its
 * idle rotation, and the now-playing endpoints that take it over while Plex is
 * streaming.
 */
final class PosterWallController
{
    private const BATCH_SIZE = 30;

    /**
     * The Live TV sentinel always resolves to the same bundled art, so it caches
     * for as long as anything here does.
     */
    private const LIVE_TV_CACHE = 'private, max-age=3600';

    /**
     * A placeholder standing in for art that could not be fetched must expire
     * quickly. A poster token is deterministic for a given item, so a long cache
     * would pin that item to the placeholder for the whole cache lifetime, well
     * after Plex recovered, with nothing to trigger a retry.
     *
     * Short rather than uncacheable: one reveal touches the poster URL three
     * times (the preload probe, the img, and the background), so `no-store`
     * would triple the requests on the path that is already failing.
     */
    private const FALLBACK_CACHE = 'private, max-age=10';

    public function __construct(
        private readonly Twig $twig,
        private readonly PosterWallService $wall,
        private readonly NowPlayingService $nowPlaying,
        private readonly PlexClient $plex,
        private readonly StreamToken $token,
        private readonly string $placeholderPath,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'wall.html.twig');
    }

    public function posters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $urls = array_map(
            static fn (Poster $poster): string => $poster->url(),
            $this->wall->randomPosters(self::BATCH_SIZE),
        );

        $response->getBody()->write(
            json_encode(['posters' => $urls], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function streams(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $streams = array_map(
            static fn (NowPlayingTile $tile): array => $tile->toArray(),
            $this->nowPlaying->tiles(),
        );

        $response->getBody()->write(
            json_encode(['streams' => $streams], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, string> $args
     */
    public function streamPoster(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? '';

        if ($this->token->isLive($id)) {
            return $this->placeholder($request, $response, self::LIVE_TV_CACHE);
        }

        // An unresolvable token — unsigned, tampered with, or carrying a path
        // the proxy will not fetch — falls back to the placeholder rather than
        // erroring, so a now-playing tile never renders without a poster.
        $thumb = $this->token->thumbFor($id);
        if ($thumb === null) {
            return $this->placeholder($request, $response, self::FALLBACK_CACHE);
        }

        try {
            $bytes = $this->plex->sessionPoster($thumb);
        } catch (PlexException) {
            // The stream may have ended between the poll and this request; fall
            // back to the placeholder rather than erroring the display.
            return $this->placeholder($request, $response, self::FALLBACK_CACHE);
        }

        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', 'image/jpeg')
            ->withHeader('Cache-Control', 'private, max-age=60');
    }

    /**
     * The bundled placeholder art. The caller sets the cache policy, because
     * whether this response is the intended art for a session or a stand-in for
     * one that could not be fetched is something only the caller knows.
     */
    private function placeholder(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $cacheControl,
    ): ResponseInterface {
        $svg = @file_get_contents($this->placeholderPath);
        if ($svg === false) {
            throw new HttpNotFoundException($request);
        }

        $response->getBody()->write($svg);

        return $response
            ->withHeader('Content-Type', 'image/svg+xml')
            ->withHeader('Cache-Control', $cacheControl);
    }
}
