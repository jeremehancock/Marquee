<?php

declare(strict_types=1);

namespace App\Controller;

use App\Poster\PosterCategory;
use App\Poster\PosterStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Stream;

/**
 * Streams a poster image to authenticated users. Never resolves a path outside
 * the posters directory (the storage layer validates the filename).
 */
final class PosterImageController
{
    private const CONTENT_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private readonly PosterStorage $storage)
    {
    }

    /**
     * @param array<string, string> $args
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = PosterCategory::fromSlug($args['category'] ?? '');
        $filename = $args['filename'] ?? '';

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
