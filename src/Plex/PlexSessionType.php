<?php

declare(strict_types=1);

namespace App\Plex;

/**
 * The kind of a Plex playback session, as classified from `/status/sessions`.
 *
 * This is deliberately separate from {@see PlexMediaType}, which maps library
 * items to poster categories. A session carries concerns a library item does
 * not (Live TV, music) and never becomes a poster category, so the two stay
 * apart.
 */
enum PlexSessionType: string
{
    case Movie = 'movie';
    case Episode = 'episode';
    case LiveTv = 'live-tv';
    case Music = 'music';
    case Other = 'other';

    /**
     * Whether the wall shows a poster for this session. Music and anything
     * unrecognised are excluded; Live TV is included with a placeholder.
     */
    public function isVideo(): bool
    {
        return $this === self::Movie || $this === self::Episode || $this === self::LiveTv;
    }
}
