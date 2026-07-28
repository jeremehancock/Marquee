<?php

declare(strict_types=1);

namespace App\Plex;

/**
 * One active Plex playback session, parsed from `/status/sessions`.
 *
 * A faithful, presentation-free view of what a session carries: the item being
 * played, the person playing it, whether it is Live TV, and the Plex image path
 * of the poster to show. Turning this into a wall tile (titles, detail line,
 * poster URL) is the wall layer's job, not this value object's.
 */
final class PlexSession
{
    public function __construct(
        public readonly PlexSessionType $type,
        public readonly string $title,
        public readonly string $user,
        public readonly bool $live,
        public readonly ?string $thumb = null,
        public readonly ?string $grandparentTitle = null,
        public readonly ?int $seasonNumber = null,
        public readonly ?int $episodeNumber = null,
        public readonly ?int $year = null,
    ) {
    }

    /**
     * The episode's `SxxEyy` label, or null when the season/episode numbers are
     * not both present.
     */
    public function episodeLabel(): ?string
    {
        if ($this->seasonNumber === null || $this->episodeNumber === null) {
            return null;
        }

        return sprintf('S%02dE%02d', $this->seasonNumber, $this->episodeNumber);
    }
}
