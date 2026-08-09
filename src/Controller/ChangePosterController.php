<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\Export\ExportException;
use App\Plex\PlexException;
use App\Plex\PlexFailureMessage;
use App\Plex\PlexMediaType;
use App\Plex\Poster\PlexPosterCandidate;
use App\Plex\Poster\PlexPosterOutcome;
use App\Plex\Poster\PlexPosterService;
use App\Plex\SignedImagePath;
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
use Throwable;

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
        private readonly PlexFailureMessage $plexMessage,
        private readonly PlexPosterService $plexPosters,
        private readonly SignedImagePath $signedPaths,
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
        } catch (UploadException $e) {
            $this->flash->add('error', $this->plexMessage->for($e));
        } catch (ExportException | PlexException $e) {
            $this->flashChangedButNotPushed($e);
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
        } catch (UploadException $e) {
            $this->flash->add('error', $this->plexMessage->for($e));
        } catch (ExportException | PlexException $e) {
            $this->flashChangedButNotPushed($e);
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
            $this->flash->add('error', $this->plexMessage->for($e));
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
            $this->flash->add('error', $this->plexMessage->for($e));
        }

        return $this->back($response, $category);
    }

    /**
     * The posters Plex already holds for this poster's item, in two groups.
     *
     * Kept apart from findPosters() rather than folded into it: that searches a
     * title and can fail in ways a rating key cannot, and its candidates carry
     * provider attribution these do not.
     *
     * @param array<string, string> $args
     */
    public function plexPosters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = $this->requireCategory($request, $args);
        $params = $request->getQueryParams();
        $filename = isset($params['filename']) && is_string($params['filename']) ? $params['filename'] : '';

        $listing = $this->plexPosters->forPoster($category, $filename);

        return $this->json($response, [
            'uploaded' => $this->candidates($listing->uploaded()),
            // One list, deliberately. Whether Plex already has the bytes decides
            // how applying works, but it is not a distinction a user is choosing
            // between — they are picking a poster. The two are still ordered
            // held-first, so the ones that need no upload come up first.
            'available' => array_merge(
                $this->candidates($listing->server()),
                $this->offered($listing->offered()),
            ),
            'error' => $this->plexPosterMessageFor($listing->outcome),
        ]);
    }

    /**
     * Apply one of those posters, named by the signed path the listing gave out.
     *
     * @param array<string, string> $args
     */
    public function usePlexPoster(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = $this->requireCategory($request, $args);
        $filename = $this->filename($request);
        $body = (array) $request->getParsedBody();
        $token = isset($body['token']) && is_string($body['token']) ? $body['token'] : '';

        $path = $this->signedPaths->pathFor($token);
        if ($path === null) {
            // The only way here is a token this application did not sign, so
            // there is nothing to explain to the user beyond the refusal.
            $this->flash->add('error', 'That poster could not be applied.');

            return $this->back($response, $category);
        }

        try {
            $this->flashChanged($this->change->changeFromPlexPath($category, $filename, $path));
        } catch (UploadException $e) {
            $this->flash->add('error', $this->plexMessage->for($e));
        } catch (ExportException | PlexException $e) {
            $this->flashChangedButNotPushed($e);
        }

        return $this->back($response, $category);
    }

    /**
     * A candidate Plex holds, as the grid needs it: an opaque, signed address
     * for the image and nothing that discloses where on Plex it lives.
     *
     * `ref` is the signed token standing for the full-resolution path, so
     * applying sends back the same opaque value the grid was given.
     *
     * @param list<PlexPosterCandidate> $candidates
     *
     * @return list<array{ref: string, thumb: string, selected: bool, held: bool}>
     */
    private function candidates(array $candidates): array
    {
        return array_map(
            fn (PlexPosterCandidate $c): array => [
                'ref' => $this->signedPaths->sign($c->path),
                'thumb' => '/plex-poster-image/' . $this->signedPaths->sign($c->thumbPath),
                'selected' => $c->selected,
                // Says which of the two ways this candidate is applied, since
                // the grid mixes both. Never shown to the user.
                'held' => true,
            ],
            $candidates,
        );
    }

    /**
     * Artwork Plex has not downloaded, as plain URLs.
     *
     * No token and no proxy: there is nothing on the Plex server to address and
     * no Plex credential involved, so these behave exactly like an address a
     * user pasted into the From URL tab — which is also how applying one works.
     *
     * `ref` is the provider URL itself — the same field a held candidate carries
     * a token in, so one grid renders both and one handler applies either.
     *
     * @param list<PlexPosterCandidate> $candidates
     *
     * @return list<array{ref: string, thumb: string, selected: bool, held: bool}>
     */
    private function offered(array $candidates): array
    {
        return array_map(
            static fn (PlexPosterCandidate $c): array => [
                'ref' => $c->path,
                'thumb' => $c->thumbPath,
                'selected' => $c->selected,
                'held' => false,
            ],
            $candidates,
        );
    }

    private function plexPosterMessageFor(PlexPosterOutcome $outcome): ?string
    {
        return match ($outcome) {
            PlexPosterOutcome::Ok => null,
            PlexPosterOutcome::None => 'Plex has no posters of its own for this item.',
            PlexPosterOutcome::NotLinked => 'This poster is not linked to a Plex item.',
            PlexPosterOutcome::Unavailable => 'Plex could not be reached. Trying again shortly may work.',
        };
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

        $query = new PosterQuery(
            title: $record->title,
            mediaType: $type,
            seasonNumber: $record->seasonNumber,
            year: $record->year,
            tmdbId: $record->tmdbId,
        );

        $result = $this->source->find($query);

        $this->correctStaleTmdbId($record, $query, $result);

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
     * That last argument holds only while the replacement is well-founded, so
     * the search has to have been able to tell works sharing a title apart —
     * which today means it sent a year. Without one the source's title fallback
     * scores two same-titled works identically and popularity picks between
     * them, making the matched id a guess rather than a finding. The two
     * outcomes are not symmetrical: a stale id fails to resolve on every search
     * and so stays visible and repairable, while a wrong-but-valid id resolves
     * cleanly forever and no later mismatch can reveal it. Keeping the stale id
     * costs one wrong result the item was already getting; writing the guess
     * costs the ability to ever notice.
     *
     * The check is on what *we sent*, never on the source telling us it fell
     * back to the title. A correction only ever arises when the id we sent was
     * unrecognised — which is that fallback — so treating the fallback itself
     * as disqualifying would suppress every correction rather than the
     * unfounded ones.
     *
     * This is a repair of a Plex-derived cache, not an override of Plex: a full
     * re-import writes the id from Plex again. If Plex is still wrong the next
     * search detects it and repairs it again, which costs one request and no
     * schema change.
     */
    private function correctStaleTmdbId(PlexItemRecord $record, PosterQuery $query, PosterSearchResult $result): void
    {
        $corrected = $result->correctedTmdbId;
        if ($corrected === null || $record->tmdbId === null || $corrected === $record->tmdbId) {
            return;
        }

        if ($query->year === null) {
            $this->logger->info('declined a TMDB id correction from a search that could not disambiguate the title', [
                'title' => $record->title,
                'ratingKey' => $record->ratingKey,
                'kept' => $record->tmdbId,
                'notWritten' => $corrected,
            ]);

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
            PosterSearchOutcome::NoArtwork => 'This title was found, but no posters are available.',
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
     * The poster was replaced on disk and then could not be pushed to Plex —
     * the item is gone (an orphan), or Plex is unreachable or rejecting the
     * token. Reporting that as a plain error would be false twice over: it says
     * nothing changed when the new poster is already stored, and it leaves the
     * gallery showing the old image, because the client only re-renders a card
     * for an operation that stored something.
     *
     * So it is its own level. The message leads with what happened locally and
     * carries the underlying reason, which is the part that tells the user
     * whether to fix Plex or delete an orphan.
     */
    private function flashChangedButNotPushed(Throwable $e): void
    {
        $this->flash->add('warning', 'Poster updated, but it could not be sent to Plex. ' . $this->plexMessage->for($e));
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
