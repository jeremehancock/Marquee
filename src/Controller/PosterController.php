<?php

declare(strict_types=1);

namespace App\Controller;

use App\Poster\PosterCategory;
use App\Poster\PosterLibrary;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * Poster actions that change the library (currently: delete).
 */
final class PosterController
{
    public function __construct(
        private readonly PosterLibrary $library,
        private readonly Flash $flash,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = PosterCategory::fromSlug($args['category'] ?? '');
        if ($category === null) {
            throw new HttpNotFoundException($request);
        }

        $body = (array) $request->getParsedBody();
        $filename = isset($body['filename']) && is_string($body['filename']) ? $body['filename'] : '';

        if ($this->library->delete($category, $filename)) {
            $this->flash->add('success', 'Poster deleted.');
        } else {
            $this->flash->add('error', 'That poster could not be deleted.');
        }

        return $response->withHeader('Location', '/library/' . $category->value)->withStatus(302);
    }
}
