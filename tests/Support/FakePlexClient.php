<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;

/**
 * In-memory PlexClient for tests: canned libraries/items and generated posters.
 */
final class FakePlexClient implements PlexClient
{
    /** @var list<string> rating keys whose poster was actually downloaded */
    public array $downloads = [];

    /**
     * @param list<PlexLibrary>                $libraries
     * @param array<array-key, list<PlexItem>> $itemsByLibrary   keyed by library key
     * @param array<array-key, list<PlexItem>> $seasonsByShow    keyed by show rating key
     * @param array<array-key, list<PlexItem>> $collectionsByKey keyed by library key
     * @param list<string>                     $failingKeys      rating keys that fail download
     */
    public function __construct(
        private readonly array $libraries = [],
        private readonly array $itemsByLibrary = [],
        private readonly array $seasonsByShow = [],
        private readonly array $collectionsByKey = [],
        private readonly array $failingKeys = [],
        private readonly bool $configured = true,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function libraries(): array
    {
        return $this->libraries;
    }

    public function items(PlexLibrary $library): array
    {
        return $this->itemsByLibrary[$library->key] ?? [];
    }

    public function seasons(PlexItem $show): array
    {
        return $this->seasonsByShow[$show->ratingKey] ?? [];
    }

    public function collections(PlexLibrary $library): array
    {
        return $this->collectionsByKey[$library->key] ?? [];
    }

    public function downloadPoster(PlexItem $item): string
    {
        if (in_array($item->ratingKey, $this->failingKeys, true)) {
            throw PlexException::connectionFailed();
        }
        $this->downloads[] = $item->ratingKey;

        return $this->png();
    }

    public function itemPoster(string $ratingKey): string
    {
        if (in_array($ratingKey, $this->failingKeys, true)) {
            throw PlexException::connectionFailed();
        }

        return $this->png();
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(2, 3);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes === false ? '' : $bytes;
    }
}
