<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\PlexItemRepository;
use App\Plex\Export\ExportException;
use App\Plex\PlexException;
use App\Plex\PlexMediaType;
use App\Poster\Edit\ChangePosterService;
use App\Poster\PosterCategory;
use App\Poster\Source\PosterSource;
use App\Poster\Upload\UploadException;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * Per-poster editing: change from a file, a URL, a Plex re-pull, or a found
 * poster; and search the poster source for candidates.
 */
final class ChangePosterController
{
    public function __construct(
        private readonly ChangePosterService $change,
        private readonly PlexItemRepository $items,
        private readonly PosterSource $source,
        private readonly Flash $flash,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function upload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = $this->requireCategory($request, $args);
        $filename = $this->filename($request);
        $file = $request->getUploadedFiles()['poster'] ?? null;

        try {
            if (!$file instanceof UploadedFileInterface) {
                throw UploadException::failed();
            }
            $pushed = $this->change->changeFromUploadedFile($category, $filename, $file);
            $this->flashChanged($pushed);
        } catch (UploadException | ExportException | PlexException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $this->back($response, $category);
    }

    /**
     * @param array<string, string> $args
     */
    public function url(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = $this->requireCategory($request, $args);
        $filename = $this->filename($request);
        $body = (array) $request->getParsedBody();
        $url = isset($body['url']) && is_string($body['url']) ? $body['url'] : '';

        try {
            $pushed = $this->change->changeFromUrl($category, $filename, $url);
            $this->flashChanged($pushed);
        } catch (UploadException | ExportException | PlexException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $this->back($response, $category);
    }

    /**
     * @param array<string, string> $args
     */
    public function sendToPlex(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = $this->requireCategory($request, $args);
        $filename = $this->filename($request);

        try {
            $this->change->sendToPlex($category, $filename);
            $this->flash->add('success', 'Sent the current poster to Plex and locked it.');
        } catch (ExportException | PlexException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $this->back($response, $category);
    }

    /**
     * @param array<string, string> $args
     */
    public function fetchFromPlex(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = $this->requireCategory($request, $args);
        $filename = $this->filename($request);

        try {
            $this->change->fetchFromPlex($category, $filename);
            $this->flash->add('success', 'Fetched the current poster from Plex.');
        } catch (ExportException | PlexException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $this->back($response, $category);
    }

    /**
     * @param array<string, string> $args
     */
    public function findPosters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = PosterCategory::fromSlug($args['category'] ?? '');
        if ($category === null) {
            throw new HttpNotFoundException($request);
        }

        $params = $request->getQueryParams();
        $filename = isset($params['filename']) && is_string($params['filename']) ? $params['filename'] : '';
        $record = $this->items->findByFilename($category->value, $filename);

        $posters = [];
        $error = null;
        if ($record === null) {
            $error = 'This poster is not linked to a Plex item.';
        } else {
            $type = PlexMediaType::fromString($record->mediaType);
            $posters = $type !== null ? $this->source->find($record->title, $type, null) : [];
            if ($posters === []) {
                $error = 'No posters found for this title.';
            }
        }

        $response->getBody()->write(
            json_encode(['posters' => $posters, 'error' => $error], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function flashChanged(bool $pushed): void
    {
        $this->flash->add('success', $pushed ? 'Poster updated and sent to Plex.' : 'Poster updated.');
    }

    /**
     * @param array<string, string> $args
     */
    private function requireCategory(ServerRequestInterface $request, array $args): PosterCategory
    {
        $category = PosterCategory::fromSlug($args['category'] ?? '');
        if ($category === null) {
            throw new HttpNotFoundException($request);
        }

        return $category;
    }

    private function filename(ServerRequestInterface $request): string
    {
        $body = (array) $request->getParsedBody();

        return isset($body['filename']) && is_string($body['filename']) ? $body['filename'] : '';
    }

    private function back(ResponseInterface $response, PosterCategory $category): ResponseInterface
    {
        return $response->withHeader('Location', '/library/' . $category->value)->withStatus(302);
    }
}
