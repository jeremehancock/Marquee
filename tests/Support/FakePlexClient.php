<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Config\LibraryExclusions;
use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexSession;
use App\Plex\Poster\PlexPosterList;
use SimpleXMLElement;

/**
 * In-memory PlexClient for tests: canned libraries/items and generated posters.
 */
class FakePlexClient implements PlexClient
{
    /** @var list<string> rating keys whose poster was actually downloaded */
    public array $downloads = [];

    /** @var list<string> collection rating keys whose members were asked for */
    public array $collectionWalks = [];

    /**
     * @param list<PlexLibrary>                $libraries
     * @param array<array-key, list<PlexItem>> $itemsByLibrary   keyed by library key
     * @param array<array-key, list<PlexItem>> $seasonsByShow    keyed by show rating key
     * @param array<array-key, list<PlexItem>> $collectionsByKey keyed by library key
     * @param list<string>                     $failingKeys      rating keys that fail download
     * @param list<PlexSession>                $sessions         active playback sessions
     * @param list<string>                     $failingThumbs    thumb paths whose poster fetch fails
     * @param list<string>                     $excluded         library names the real client would hide
     * @param string|null                      $serverName       the server's friendly name, null when unknown
     * @param array<array-key, string>         $postersByKey     raw posters XML, keyed by rating key
     * @param bool                             $failLibraries    whether listing libraries fails, as an unreachable server does
     * @param array<array-key, list<PlexItem>> $membersByCollection collection members, keyed by collection rating key.
     *        Last so that adding it shifts no existing positional caller.
     */
    public function __construct(
        private readonly array $libraries = [],
        private readonly array $itemsByLibrary = [],
        private readonly array $seasonsByShow = [],
        private readonly array $collectionsByKey = [],
        private readonly array $failingKeys = [],
        private readonly bool $configured = true,
        private readonly array $sessions = [],
        private readonly array $failingThumbs = [],
        private readonly array $excluded = [],
        private readonly ?string $serverName = 'Anansi',
        private readonly array $postersByKey = [],
        private readonly bool $failLibraries = false,
        private readonly array $membersByCollection = [],
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Honors the PlexClient contract that excluded libraries are never
     * reported, so callers under test see exactly what production would.
     */
    public function libraries(): array
    {
        $exclusions = new LibraryExclusions($this->excluded);

        return array_values(array_filter(
            $this->allLibraries(),
            static fn (PlexLibrary $library): bool => !$exclusions->isExcluded($library->title),
        ));
    }

    /**
     * Every library, exclusions ignored — what the exclusions editor sees.
     */
    public function allLibraries(): array
    {
        if ($this->failLibraries) {
            throw PlexException::connectionFailed();
        }

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

    public function collectionChildren(PlexItem $collection): array
    {
        $this->collectionWalks[] = $collection->ratingKey;

        return $this->membersByCollection[$collection->ratingKey] ?? [];
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

    public function sessions(): array
    {
        if (!$this->configured) {
            throw PlexException::notConfigured();
        }

        return $this->sessions;
    }

    /**
     * Parses canned XML rather than building candidates directly, so tests
     * exercise the same filtering production does.
     */
    public function itemPosters(string $ratingKey): PlexPosterList
    {
        if (in_array($ratingKey, $this->failingKeys, true)) {
            throw PlexException::connectionFailed();
        }

        return PlexPosterList::fromXml(new SimpleXMLElement(
            $this->postersByKey[$ratingKey] ?? '<MediaContainer/>'
        ));
    }

    public function imageAt(string $path): string
    {
        if (in_array($path, $this->failingThumbs, true)) {
            throw PlexException::connectionFailed();
        }

        return $this->png();
    }

    public function serverName(): ?string
    {
        return $this->configured ? $this->serverName : null;
    }


    private function png(): string
    {
        $image = imagecreatetruecolor(2, 3);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();

        return $bytes === false ? '' : $bytes;
    }
}
