<?php

declare(strict_types=1);

namespace App\Controller;

use App\Poster\PosterCategory;
use App\Poster\PosterStorage;
use App\Poster\Wall\PosterWallService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Stream;

/**
 * Streams a poster image. Never resolves a path outside the posters directory
 * (the storage layer validates the filename).
 *
 * Two ways in, because the Poster Wall runs on an unattended display with no
 * session. {@see __invoke} is the gallery's, behind the login, and serves any
 * category. {@see wall} is reachable without a session and serves only the
 * categories the wall actually draws, so making its rotation work does not also
 * publish the library's TV Seasons and Collections.
 */
final class PosterImageController
{
    private const CONTENT_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private readonly PosterStorage $storage,
        private readonly PosterWallService $wall,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->serve($request, $response, PosterCategory::fromSlug($args['category'] ?? ''), $args['filename'] ?? '');
    }

    /**
     * The wall's posters, without a session.
     *
     * The wall lists these addresses publicly already and displays the art on a
     * screen anyone in the room can see, so serving the bytes discloses nothing
     * the wall does not. What it must not do is widen past that: a category the
     * wall never shows is refused here even though the file exists and the
     * gallery would serve it.
     *
     * @param array<string, string> $args
     */
    public function wall(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = PosterCategory::fromSlug($args['category'] ?? '');
        if ($category === null || !$this->wall->shows($category)) {
            throw new HttpNotFoundException($request);
        }

        return $this->serve($request, $response, $category, $args['filename'] ?? '');
    }

    private function serve(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?PosterCategory $category,
        string $filename,
    ): ResponseInterface {
        $path = $category !== null ? $this->storage->path($category, $filename) : null;
        if ($path === null) {
            throw new HttpNotFoundException($request);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new HttpNotFoundException($request);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';

        return $response
            ->withBody(new Stream($handle))
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Cache-Control', 'private, max-age=604800');
    }
}
