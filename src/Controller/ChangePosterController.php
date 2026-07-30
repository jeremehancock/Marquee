<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\Export\ExportException;
use App\Plex\PlexException;
use App\Plex\PlexMediaType;
use App\Poster\Edit\ChangePosterService;
use App\Poster\PosterCategory;
use App\Poster\Source\PosterCandidate;
use App\Poster\Source\PosterQuery;
use App\Poster\Source\PosterSearchOutcome;
use App\Poster\Source\PosterSearchResult;
use App\Poster\Source\PosterSource;
use App\Poster\Upload\UploadException;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
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
            $this->flash->add('success', 'Sent the current poster to Plex.');
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
        $type = $record === null ? null : PlexMediaType::fromString($record->mediaType);

        if ($record === null || $type === null) {
            return $this->json($response, [
                'posters' => [],
                'error' => 'This poster is not linked to a Plex item.',
                'partial' => false,
            ]);
        }

        $result = $this->source->find(new PosterQuery(
            title: $record->title,
            mediaType: $type,
            seasonNumber: $record->seasonNumber,
            year: $record->year,
            tmdbId: $record->tmdbId,
        ));

        $this->correctStaleTmdbId($record, $result);

        $posters = array_map(
            static fn (PosterCandidate $c): array => [
                'url' => $c->url,
                'thumb' => $c->displayUrl(),
                'source' => $c->source,
            ],
            $result->candidates,
        );

        return $this->json($response, [
            'posters' => $posters,
            'error' => $this->messageFor($result->outcome),
            'partial' => $result->outcome === PosterSearchOutcome::Partial,
        ]);
    }

    /**
     * Replace a stale TMDB id with the one the source actually matched.
     *
     * Only a *wrong* id is replaced, never a missing one. An item with no id is
     * searched by its title every time, and that re-resolves on each search;
     * recording one title-derived guess would pin the item to it permanently,
     * with no later mismatch left to reveal the error. A known-bad id cannot
     * get worse, which is what makes replacing it safe.
     *
     * This is a repair of a Plex-derived cache, not an override of Plex: a full
     * re-import writes the id from Plex again. If Plex is still wrong the next
     * search detects it and repairs it again, which costs one request and no
     * schema change.
     */
    private function correctStaleTmdbId(PlexItemRecord $record, PosterSearchResult $result): void
    {
        $corrected = $result->correctedTmdbId;
        if ($corrected === null || $record->tmdbId === null || $corrected === $record->tmdbId) {
            return;
        }

        $this->items->upsert(new PlexItemRecord(
            ratingKey: $record->ratingKey,
            mediaType: $record->mediaType,
            category: $record->category,
            libraryTitle: $record->libraryTitle,
            title: $record->title,
            filename: $record->filename,
            updatedAt: time(),
            sectionKey: $record->sectionKey,
            thumb: $record->thumb,
            addedAt: $record->addedAt,
            year: $record->year,
            seasonNumber: $record->seasonNumber,
            tmdbId: $corrected,
        ));

        $this->logger->info('corrected a stale TMDB id from a poster search', [
            'title' => $record->title,
            'ratingKey' => $record->ratingKey,
            'was' => $record->tmdbId,
            'now' => $corrected,
        ]);
    }

    /**
     * The user-facing message for an outcome, or null when there is nothing to
     * say. Each one tells the user something different about what to do next:
     * whether to check the title, wait, retry later, or accept that there is no
     * artwork to find.
     */
    private function messageFor(PosterSearchOutcome $outcome): ?string
    {
        return match ($outcome) {
            PosterSearchOutcome::Ok => null,
            PosterSearchOutcome::Partial => 'Some poster sources did not respond, so these results may be incomplete.',
            PosterSearchOutcome::NoMatch => 'No match for this title.',
            PosterSearchOutcome::NoArtwork => 'This title was found, but no posters are available for it.',
            PosterSearchOutcome::RateLimited => 'Too many searches just now. Wait a minute and try again.',
            PosterSearchOutcome::Unavailable => 'Poster search is temporarily unavailable. Trying again shortly may work.',
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(ResponseInterface $response, array $payload): ResponseInterface
    {
        $response->getBody()->write(
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
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
