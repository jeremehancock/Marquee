<?php

declare(strict_types=1);

namespace App\Plex\Orphan;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Poster\PosterCategory;
use App\Poster\PosterStorage;

/**
 * Finds and removes orphaned posters: imported posters whose Plex item is gone.
 */
final class OrphanService
{
    public function __construct(
        private readonly PlexClient $plex,
        private readonly PlexItemRepository $items,
        private readonly PosterStorage $storage,
    ) {
    }

    /**
     * @return list<PlexItemRecord>
     */
    public function findOrphans(): array
    {
        if (!$this->plex->isConfigured()) {
            throw PlexException::notConfigured();
        }

        $current = $this->collectCurrentRatingKeys($this->items->distinctMediaTypes());

        $orphans = [];
        foreach ($this->items->all() as $record) {
            $category = PosterCategory::fromSlug($record->category);
            if ($category === null || !$this->storage->exists($category, $record->filename)) {
                continue;
            }
            if (!isset($current[$record->ratingKey])) {
                $orphans[] = $record;
            }
        }

        return $orphans;
    }

    public function deleteAll(): int
    {
        $count = 0;
        foreach ($this->findOrphans() as $record) {
            $category = PosterCategory::fromSlug($record->category);
            if ($category === null) {
                continue;
            }
            $this->storage->delete($category, $record->filename);
            $this->items->deleteByRatingKey($record->ratingKey);
            $count++;
        }

        return $count;
    }

    /**
     * @param list<string> $presentTypes
     *
     * @return array<string, true>
     */
    private function collectCurrentRatingKeys(array $presentTypes): array
    {
        $has = static fn (string $type): bool => in_array($type, $presentTypes, true);
        if ($presentTypes === []) {
            return [];
        }

        $keys = [];
        foreach ($this->plex->libraries() as $library) {
            if ($library->isMovie() && $has('movie')) {
                foreach ($this->plex->items($library) as $item) {
                    $keys[$item->ratingKey] = true;
                }
            }

            if ($library->isShow() && ($has('show') || $has('season'))) {
                foreach ($this->plex->items($library) as $show) {
                    if ($has('show')) {
                        $keys[$show->ratingKey] = true;
                    }
                    if ($has('season')) {
                        foreach ($this->plex->seasons($show) as $season) {
                            $keys[$season->ratingKey] = true;
                        }
                    }
                }
            }

            if ($has('collection')) {
                foreach ($this->plex->collections($library) as $collection) {
                    $keys[$collection->ratingKey] = true;
                }
            }
        }

        return $keys;
    }
}
