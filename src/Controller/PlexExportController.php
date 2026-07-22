<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plex\Export\ExportException;
use App\Plex\Export\PlexExportService;
use App\Plex\PlexException;
use App\Poster\PosterCategory;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * Sends a poster to its linked Plex item.
 */
final class PlexExportController
{
    public function __construct(
        private readonly PlexExportService $export,
        private readonly Flash $flash,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function send(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = PosterCategory::fromSlug($args['category'] ?? '');
        if ($category === null) {
            throw new HttpNotFoundException($request);
        }

        $body = (array) $request->getParsedBody();
        $filename = isset($body['filename']) && is_string($body['filename']) ? $body['filename'] : '';

        try {
            $this->export->sendToPlex($category, $filename);
            $this->flash->add('success', 'Sent to Plex and locked.');
        } catch (ExportException | PlexException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $response->withHeader('Location', '/library/' . $category->value)->withStatus(302);
    }
}
