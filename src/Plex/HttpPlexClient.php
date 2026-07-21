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
final class HttpPlexClient implements PlexClient
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
                $items[] = $this->item($video, PlexMediaType::Movie, $library->title);
            }
        } else {
            foreach ($xml->Directory as $directory) {
                $items[] = $this->item($directory, PlexMediaType::Show, $library->title);
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
            );
        }

        return $items;
    }

    public function collections(PlexLibrary $library): array
    {
        $xml = $this->get(sprintf('/library/sections/%s/collections', rawurlencode($library->key)));

        $items = [];
        foreach ($xml->Directory as $directory) {
            $items[] = $this->item($directory, PlexMediaType::Collection, $library->title);
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

    private function item(SimpleXMLElement $element, PlexMediaType $type, string $libraryTitle): PlexItem
    {
        return new PlexItem(
            ratingKey: (string) $element['ratingKey'],
            mediaType: $type,
            title: (string) $element['title'],
            year: isset($element['year']) ? (int) $element['year'] : null,
            thumb: $this->attr($element, 'thumb'),
            libraryTitle: $libraryTitle,
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
