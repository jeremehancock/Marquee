<?php

declare(strict_types=1);

namespace App\Plex;

use App\Config\PlexConfig;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use SimpleXMLElement;

/**
 * Talks to a Plex Media Server over its XML HTTP API.
 */
final class HttpPlexClient implements PlexClient, PlexPosterWriter
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly PlexConfig $config,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function libraries(): array
    {
        $xml = $this->get('/library/sections');

        $libraries = [];
        foreach ($xml->Directory as $directory) {
            $type = (string) $directory['type'];
            if ($type !== 'movie' && $type !== 'show') {
                continue;
            }
            $libraries[] = new PlexLibrary(
                key: (string) $directory['key'],
                title: (string) $directory['title'],
                type: $type,
            );
        }

        return $libraries;
    }

    public function items(PlexLibrary $library): array
    {
        $xml = $this->get(sprintf('/library/sections/%s/all', rawurlencode($library->key)));

        $items = [];
        if ($library->isMovie()) {
            foreach ($xml->Video as $video) {
                $items[] = $this->item($video, PlexMediaType::Movie, $library);
            }
        } else {
            foreach ($xml->Directory as $directory) {
                $items[] = $this->item($directory, PlexMediaType::Show, $library);
            }
        }

        return $items;
    }

    public function seasons(PlexItem $show): array
    {
        $xml = $this->get(sprintf('/library/metadata/%s/children', rawurlencode($show->ratingKey)));

        $items = [];
        foreach ($xml->Directory as $directory) {
            if ((string) $directory['type'] !== 'season') {
                continue;
            }
            $items[] = new PlexItem(
                ratingKey: (string) $directory['ratingKey'],
                mediaType: PlexMediaType::Season,
                title: (string) $directory['title'],
                year: null,
                thumb: $this->attr($directory, 'thumb'),
                libraryTitle: $show->libraryTitle,
                parentTitle: $show->title,
                sectionKey: $show->sectionKey,
            );
        }

        return $items;
    }

    public function collections(PlexLibrary $library): array
    {
        $xml = $this->get(sprintf('/library/sections/%s/collections', rawurlencode($library->key)));

        $items = [];
        foreach ($xml->Directory as $directory) {
            $items[] = $this->item($directory, PlexMediaType::Collection, $library);
        }

        return $items;
    }

    public function downloadPoster(PlexItem $item): string
    {
        if ($item->thumb === null || $item->thumb === '') {
            throw PlexException::unexpectedResponse();
        }

        try {
            $response = $this->http->request('GET', $this->config->serverUrl . $item->thumb, $this->options());
        } catch (GuzzleException $e) {
            throw PlexException::connectionFailed($e);
        }

        return (string) $response->getBody();
    }

    public function itemPoster(string $ratingKey): string
    {
        $xml = $this->get('/library/metadata/' . rawurlencode($ratingKey));

        $thumb = null;
        foreach ($xml->children() as $child) {
            $candidate = $this->attr($child, 'thumb');
            if ($candidate !== null && $candidate !== '') {
                $thumb = $candidate;
                break;
            }
        }

        if ($thumb === null) {
            throw PlexException::unexpectedResponse();
        }

        try {
            $response = $this->http->request('GET', $this->config->serverUrl . $thumb, $this->options());
        } catch (GuzzleException $e) {
            throw PlexException::connectionFailed($e);
        }

        return (string) $response->getBody();
    }

    public function uploadPoster(string $ratingKey, string $imageBytes): void
    {
        $this->write(
            'POST',
            sprintf('/library/metadata/%s/posters', rawurlencode($ratingKey)),
            ['body' => $imageBytes],
        );
    }

    public function lockPoster(string $ratingKey): void
    {
        $this->write('PUT', sprintf('/library/metadata/%s?thumb.locked=1', rawurlencode($ratingKey)));
    }

    public function removeOverlayLabel(string $sectionKey, int $plexType, string $ratingKey): void
    {
        $query = http_build_query([
            'type' => $plexType,
            'id' => $ratingKey,
            'label[].tag.tag-' => 'Overlay',
        ]);

        $this->write('PUT', sprintf('/library/sections/%s/all?%s', rawurlencode($sectionKey), $query));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function write(string $method, string $path, array $extra = []): void
    {
        if (!$this->config->isConfigured()) {
            throw PlexException::notConfigured();
        }

        try {
            $this->http->request($method, $this->config->serverUrl . $path, $extra + $this->options());
        } catch (GuzzleException $e) {
            throw PlexException::connectionFailed($e);
        }
    }

    private function item(SimpleXMLElement $element, PlexMediaType $type, PlexLibrary $library): PlexItem
    {
        return new PlexItem(
            ratingKey: (string) $element['ratingKey'],
            mediaType: $type,
            title: (string) $element['title'],
            year: isset($element['year']) ? (int) $element['year'] : null,
            thumb: $this->attr($element, 'thumb'),
            libraryTitle: $library->title,
            sectionKey: $library->key,
        );
    }

    private function attr(SimpleXMLElement $element, string $name): ?string
    {
        return isset($element[$name]) ? (string) $element[$name] : null;
    }

    private function get(string $path): SimpleXMLElement
    {
        if (!$this->config->isConfigured()) {
            throw PlexException::notConfigured();
        }

        try {
            $response = $this->http->request('GET', $this->config->serverUrl . $path, $this->options());
            $body = (string) $response->getBody();
        } catch (GuzzleException $e) {
            throw PlexException::connectionFailed($e);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if (!$xml instanceof SimpleXMLElement) {
            throw PlexException::unexpectedResponse();
        }

        return $xml;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'headers' => [
                'X-Plex-Token' => $this->config->token,
                'Accept' => 'application/xml',
            ],
            'connect_timeout' => $this->config->connectTimeout,
            'timeout' => $this->config->requestTimeout,
            'http_errors' => true,
        ];
    }
}
