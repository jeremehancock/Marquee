<?php

declare(strict_types=1);

namespace App\Poster\Wall;

use App\Plex\PlexSession;
use App\Plex\PlexSessionType;

/**
 * One now-playing poster on the wall: the poster URL plus the overlay text.
 * Built from a {@see PlexSession}; the poster is addressed through an opaque
 * {@see StreamToken} so the wall never sees a raw Plex path.
 */
final class NowPlayingTile
{
    public function __construct(
        public readonly string $id,
        public readonly string $posterUrl,
        public readonly string $title,
        public readonly string $detail,
        public readonly string $user,
        public readonly bool $live,
    ) {
    }

    public static function fromSession(PlexSession $session, StreamToken $token): self
    {
        $id = self::tokenFor($session, $token);

        return new self(
            id: $id,
            posterUrl: '/wall/stream-poster/' . $id,
            title: self::title($session),
            detail: self::detail($session),
            user: $session->user,
            live: $session->type === PlexSessionType::LiveTv,
        );
    }

    /**
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'poster' => $this->posterUrl,
            'title' => $this->title,
            'detail' => $this->detail,
            'user' => $this->user,
            'live' => $this->live,
        ];
    }

    /**
     * Live TV (and any video session missing library art) uses the placeholder
     * sentinel; everything else gets a signed token for its Plex poster path.
     *
     * A thumb that is not a Plex-relative path — artwork hosted by a DVR tuner,
     * say — also falls back to the placeholder. Signing it would mint a token
     * that {@see StreamToken::thumbFor()} is bound to refuse, leaving the wall
     * with a poster URL that can never resolve.
     */
    private static function tokenFor(PlexSession $session, StreamToken $token): string
    {
        if ($session->type === PlexSessionType::LiveTv || $session->thumb === null || $session->thumb === '') {
            return StreamToken::LIVE;
        }

        if (!str_starts_with($session->thumb, '/')) {
            return StreamToken::LIVE;
        }

        return $token->forThumb($session->thumb);
    }

    private static function title(PlexSession $session): string
    {
        if ($session->type === PlexSessionType::Episode) {
            return $session->grandparentTitle ?? $session->title;
        }

        if ($session->type === PlexSessionType::LiveTv) {
            return $session->title !== '' ? $session->title : 'Live TV';
        }

        return $session->title;
    }

    private static function detail(PlexSession $session): string
    {
        return match ($session->type) {
            PlexSessionType::Movie => self::joined([
                $session->year !== null ? (string) $session->year : null,
                'Movie',
            ]),
            PlexSessionType::Episode => self::joined([
                $session->episodeLabel(),
                $session->episodeLabel() !== null ? $session->title : null,
            ]) ?: 'Episode',
            PlexSessionType::LiveTv => self::joined([
                $session->grandparentTitle,
                'Live TV',
            ]),
            default => '',
        };
    }

    /**
     * Join the non-empty parts with a middot separator.
     *
     * @param list<string|null> $parts
     */
    private static function joined(array $parts): string
    {
        return implode(' · ', array_filter($parts, static fn (?string $p): bool => $p !== null && $p !== ''));
    }
}
