<?php

declare(strict_types=1);

namespace App\Poster\Source;

use App\Plex\PlexMediaType;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Finds posters via the posteria.app fetch API (TMDB / TVDB / Fanart / Mediux),
 * replicating the request the original app made. Any failure yields no results.
 */
final class PosteriaApiPosterSource implements PosterSource
{
    private ?int $timeOffset = null;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $baseUrl,
    ) {
    }

    public function find(string $title, PlexMediaType $mediaType, ?int $season): array
    {
        $query = $this->buildQuery(trim($title), $mediaType, $season);
        if ($query === null) {
            return [];
        }

        try {
            $response = $this->http->request('GET', $this->baseUrl . '/api/fetch/posters?' . $query, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    // The shared API identifies the client via this header.
                    'X-Client-Info' => $this->clientInfo(),
                    'User-Agent' => 'Posteria/1.0',
                ],
                'connect_timeout' => 10,
                'timeout' => 30,
                'http_errors' => true,
            ]);
            $data = json_decode((string) $response->getBody(), true);
        } catch (Throwable) {
            return [];
        }

        if (!is_array($data) || ($data['success'] ?? null) === false) {
            return [];
        }
        $results = $data['results'] ?? null;
        if (!is_array($results)) {
            return [];
        }

        return $this->extractPosters($results, $mediaType);
    }

    private function buildQuery(string $title, PlexMediaType $mediaType, ?int $season): ?string
    {
        if ($title === '') {
            return null;
        }

        $params = match ($mediaType) {
            PlexMediaType::Movie => ['movie' => $title],
            PlexMediaType::Show => ['q' => $title, 'type' => 'tv'],
            PlexMediaType::Collection => ['q' => $title, 'type' => 'collection'],
            PlexMediaType::Season => $this->seasonParams($title, $season),
        };
        $params['original_query'] = $title;

        return http_build_query($params);
    }

    /**
     * @return array<string, string|int>
     */
    private function seasonParams(string $title, ?int $season): array
    {
        $show = $title;
        $number = $season ?? 1;
        if (preg_match('/^(.*?)\s*[-:]\s*Season\s*(\d+)/i', $title, $matches) === 1) {
            $show = trim($matches[1]);
            $number = $season ?? (int) $matches[2];
        }

        return ['q' => $show, 'type' => 'tv', 'season' => $number];
    }

    /**
     * @param array<int|string, mixed> $results
     *
     * @return list<string>
     */
    private function extractPosters(array $results, PlexMediaType $mediaType): array
    {
        $urls = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $poster = null;
            if ($mediaType === PlexMediaType::Season && isset($result['season']) && is_array($result['season'])) {
                $poster = $result['season']['poster'] ?? null;
            }
            $poster ??= $result['poster'] ?? null;

            $url = $this->pickUrl($poster);
            if ($url !== null && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function pickUrl(mixed $poster): ?string
    {
        if (!is_array($poster)) {
            return null;
        }
        foreach (['original', 'large', 'medium', 'small'] as $size) {
            if (isset($poster[$size]) && is_string($poster[$size]) && $poster[$size] !== '') {
                return $poster[$size];
            }
        }

        return null;
    }

    private function clientInfo(): string
    {
        $payload = [
            'name' => 'Posteria',
            'ts' => $this->syncedTimestamp(),
            'v' => '1.0',
            'platform' => 'php',
        ];

        return base64_encode((string) json_encode($payload));
    }

    private function syncedTimestamp(): int
    {
        return (int) round(microtime(true) * 1000) + $this->timeOffset();
    }

    private function timeOffset(): int
    {
        if ($this->timeOffset !== null) {
            return $this->timeOffset;
        }

        $this->timeOffset = 0;
        try {
            $response = $this->http->request('GET', $this->baseUrl . '/api/time.php', [
                'timeout' => 5,
                'http_errors' => true,
            ]);
            $data = json_decode((string) $response->getBody(), true);
            if (is_array($data) && isset($data['server_time']) && is_numeric($data['server_time'])) {
                $this->timeOffset = (int) $data['server_time'] - (int) round(microtime(true) * 1000);
            }
        } catch (Throwable) {
            $this->timeOffset = 0;
        }

        return $this->timeOffset;
    }
}
