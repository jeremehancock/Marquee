<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Plex\PlexException;
use App\Plex\PlexItem;

/**
 * A Plex client whose collections list fine but whose members cannot be read.
 *
 * Membership is an enrichment rather than part of an import's contract, so this
 * exists to prove a collection Plex will not list costs its films their set and
 * nothing else — no failed item, no missing poster.
 */
final class FailingCollectionWalkClient extends FakePlexClient
{
    public function collectionChildren(PlexItem $collection): array
    {
        throw PlexException::connectionFailed();
    }
}
