<?php

declare(strict_types=1);

namespace App\Poster\Wall;

use App\Plex\PlexClient;
use App\Plex\PlexException;

/**
 * Turns active Plex playback into the wall's now-playing tiles: one tile per
 * qualifying session, in Plex's order, with music and unrecognised sessions
 * dropped. Sessions are never merged, so two people watching the same title
 * yield two tiles.
 *
 * Detection must never break the wall: an unconfigured or unreachable Plex
 * server yields no tiles, and the wall falls back to its random rotation.
 */
final class NowPlayingService
{
    public function __construct(
        private readonly PlexClient $plex,
        private readonly StreamToken $token,
    ) {
    }

    /**
     * @return list<NowPlayingTile>
     */
    public function tiles(): array
    {
        if (!$this->plex->isConfigured()) {
            return [];
        }

        try {
            $sessions = $this->plex->sessions();
        } catch (PlexException) {
            return [];
        }

        $tiles = [];
        foreach ($sessions as $session) {
            if (!$session->type->isVideo()) {
                continue;
            }
            $tiles[] = NowPlayingTile::fromSession($session, $this->token);
        }

        return $tiles;
    }
}
