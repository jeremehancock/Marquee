<?php

declare(strict_types=1);

namespace App\Poster\Source;

use App\Plex\PlexMediaType;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Finds posters via the posteria.app Marquee API (TMDB / fanart.tv / TheTVDB).
 *
 * The endpoint resolves the query to a single work and returns only that work's
 * artwork, already de-duplicated and ranked best-first. This class does not
 * filter, re-order or de-duplicate what it receives; it only maps the response
 * onto a PosterSearchResult so the reason for an empty result survives.
 */
final class PosteriaApiPosterSource implements PosterSource
{
    private const PATH = '/marquee/api/v1/posters';

    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $baseUrl,
        private readonly string $version,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function find(PosterQuery $query): PosterSearchResult
    {
        $params = $this->params($query);
        if ($params === null) {
            return PosterSearchResult::failed(PosterSearchOutcome::NoMatch);
        }

        try {
            $response = $this->http->request('GET', $this->baseUrl . self::PATH . '?' . http_build_query($params), [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Client-Info' => $this->clientInfo(),
                ],
                'connect_timeout' => 10,
                'timeout' => 30,
                'http_errors' => true,
            ]);
            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();
            $body = (string) $e->getResponse()->getBody();
        } catch (Throwable $e) {
            $this->log('poster search could not reach the service', ['error' => $e->getMessage()]);

            return PosterSearchResult::failed(PosterSearchOutcome::Unavailable);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->log('poster search returned an unreadable response', ['status' => $status]);

            return PosterSearchResult::failed(PosterSearchOutcome::Unavailable);
        }

        return $this->interpret($query, $status, $data);
    }

    /**
     * Map the response onto an outcome.
     *
     * Branching is on `success` and the HTTP status, never on the presence of
     * `code`: a partial-failure response carries `code: partial` while still
     * being a success with candidates in it.
     *
     * @param array<array-key, mixed> $data
     */
    private function interpret(PosterQuery $query, int $status, array $data): PosterSearchResult
    {
        if (($data['success'] ?? null) !== true) {
            $outcome = $this->failureOutcome($status);
            $this->log('poster search failed', [
                'status' => $status,
                'code' => is_string($data['code'] ?? null) ? $data['code'] : null,
                'error' => is_string($data['error'] ?? null) ? $data['error'] : null,
            ]);

            return PosterSearchResult::failed($outcome);
        }

        $candidates = $this->candidates($data['posters'] ?? null);
        $partial = ($data['code'] ?? null) === 'partial';
        if ($partial || $candidates === []) {
            $this->log($partial ? 'poster search returned partial results' : 'poster search matched a work with no artwork', [
                'providers' => $this->providers($data['providers'] ?? null),
            ]);
        }

        return PosterSearchResult::found($candidates, $partial, $this->correctedTmdbId($query, $data));
    }

    /**
     * The id the endpoint actually matched, when it is not the one we sent.
     *
     * A difference means one thing: the id we sent is not one TMDB knows, so
     * the endpoint fell back to resolving the title and served that work
     * instead. The stored id is stale and this is what it should have been.
     *
     * The sent half comes from `sendableTmdbId()` — the same method that decided
     * what went on the wire — and never from the response. `query.tmdb_id`
     * reports the id the endpoint *resolved*, not the one it was given: a search
     * that sends no id at all still gets one back there. Comparing the response
     * against itself therefore compares a number with itself, which is how this
     * silently never fired in v1.4.0.
     *
     * Asking what was *sent* also settles the collection case for free. A
     * collection's recorded id is withheld, so `sendableTmdbId()` returns null
     * and there is nothing to disagree with — the same rule that keeps the id
     * off the request keeps it out of this comparison.
     *
     * @param array<array-key, mixed> $data
     */
    private function correctedTmdbId(PosterQuery $query, array $data): ?string
    {
        $sent = $this->sendableTmdbId($query);
        $match = $data['match'] ?? null;
        if ($sent === null || !is_array($match)) {
            return null;
        }

        $sent = (string) $sent;
        $matched = $this->tmdbId($match['tmdb_id'] ?? null);
        if ($matched === null || $sent === $matched) {
            return null;
        }

        $this->log('poster search matched a different work than the id sent for it', [
            'sent' => $sent,
            'matched' => $matched,
        ]);

        return $matched;
    }

    /**
     * A TMDB id from the response, normalised to the string form it is stored
     * in. Only a positive whole number is an id; anything else is treated as
     * absent, because a bad value here would be written to the item's mapping.
     */
    private function tmdbId(mixed $value): ?string
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }

        $id = (int) $value;

        return $id >= 1 ? (string) $id : null;
    }

    /**
     * A rejected request (400/401/405) is a defect in Marquee or a change in the
     * service, not something the user can act on, so it reads to them the same
     * as an unreachable service — but it is logged with its real status above.
     */
    private function failureOutcome(int $status): PosterSearchOutcome
    {
        return match ($status) {
            404 => PosterSearchOutcome::NoMatch,
            429 => PosterSearchOutcome::RateLimited,
            default => PosterSearchOutcome::Unavailable,
        };
    }

    /**
     * @return array<string, string|int>|null null when there is nothing to search for
     */
    private function params(PosterQuery $query): ?array
    {
        $title = trim($query->title);
        if ($title === '') {
            return null;
        }

        $params = ['q' => $title, 'type' => $query->mediaType->value];

        if ($query->mediaType === PlexMediaType::Season) {
            $season = $query->seasonNumber ?? $this->seasonFromTitle($title);
            // `!== null` and not a truthiness test: season 0 is Specials.
            if ($season === null) {
                return null;
            }
            $params['season'] = $season;
            $params['q'] = $this->showFromTitle($title);
        }

        // Sent even alongside an id, where the endpoint ignores it: suppressing
        // it would be a branch with no observable effect, and one that would
        // have to be undone the moment the id turns out to be unknown upstream
        // and the title path takes over — which is exactly when year matters.
        if ($query->year !== null) {
            $params['year'] = $query->year;
        }

        $tmdbId = $this->sendableTmdbId($query);
        if ($tmdbId !== null) {
            $params['tmdb_id'] = $tmdbId;
        }

        return $params;
    }

    /**
     * The recorded TMDB id, if it is one the endpoint will accept: an integer
     * of at least 1. Anything else — empty, non-numeric, zero — is treated as
     * absent rather than sent and rejected with a 400 the user would read as
     * "temporarily unavailable".
     *
     * Collections are excluded on their media type, not on the stored value
     * being null. The specs say a Plex collection carries no id, but the import
     * requests `includeGuids=1` and runs the same extraction it runs for
     * movies, so a server that did report one would have stored it — and a
     * collection id is not something `type=collection` accepts.
     */
    private function sendableTmdbId(PosterQuery $query): ?int
    {
        if ($query->mediaType === PlexMediaType::Collection) {
            return null;
        }

        $id = $query->tmdbId;
        if ($id === null || !ctype_digit($id)) {
            return null;
        }

        return (int) $id >= 1 ? (int) $id : null;
    }

    /**
     * Transitional: derive a season number from the poster's display title for
     * items imported before the season number was recorded. Removable once
     * deployed installs have re-imported — see design Decision 7.
     */
    private function seasonFromTitle(string $title): ?int
    {
        if (preg_match('/\bS(?:eason)?\s*(\d+)\b/i', $title, $matches) === 1) {
            return (int) $matches[1];
        }
        if (preg_match('/\bSpecials\b/i', $title) === 1) {
            return 0;
        }

        return null;
    }

    /**
     * Transitional, paired with seasonFromTitle(): recover the show's title from
     * a "Show - Season 2" display title so the query is not searched verbatim.
     */
    private function showFromTitle(string $title): string
    {
        if (preg_match('/^(.*?)\s*[-:]\s*(?:S(?:eason)?\s*\d+|Specials)\b/i', $title, $matches) === 1) {
            $show = trim($matches[1]);
            if ($show !== '') {
                return $show;
            }
        }

        return $title;
    }

    /**
     * @return list<PosterCandidate>
     */
    private function candidates(mixed $posters): array
    {
        if (!is_array($posters)) {
            return [];
        }

        $candidates = [];
        foreach ($posters as $poster) {
            if (!is_array($poster)) {
                continue;
            }
            $url = $this->str($poster['url'] ?? null);
            if ($url === null) {
                continue;
            }
            $candidates[] = new PosterCandidate(
                url: $url,
                thumb: $this->str($poster['thumb'] ?? null),
                source: $this->str($poster['source'] ?? null),
                width: $this->int($poster['width'] ?? null),
                height: $this->int($poster['height'] ?? null),
                language: $this->str($poster['language'] ?? null),
                score: $this->float($poster['score'] ?? null),
            );
        }

        return $candidates;
    }

    /**
     * The providers map, for logging only. Its keys are whichever sources exist
     * rather than a fixed set, so it is read as given and never enumerated.
     *
     * @return array<string, string>
     */
    private function providers(mixed $providers): array
    {
        if (!is_array($providers)) {
            return [];
        }

        $map = [];
        foreach ($providers as $name => $state) {
            if (is_string($name) && is_string($state)) {
                $map[$name] = $state;
            }
        }

        return $map;
    }

    private function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * The endpoint tolerates 24 hours of clock skew in either direction, so the
     * current time is sent as-is — no server time round trip.
     */
    private function clientInfo(): string
    {
        $payload = [
            'name' => 'Marquee',
            'version' => $this->version,
            'ts' => (int) round(microtime(true) * 1000),
        ];

        return base64_encode((string) json_encode($payload));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $message, array $context): void
    {
        $this->logger->warning($message, $context);
    }
}
