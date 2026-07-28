<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Plex\PlexSession;
use App\Plex\PlexSessionType;
use App\Poster\Wall\NowPlayingService;
use App\Poster\Wall\StreamToken;
use App\Tests\Support\FakePlexClient;
use PHPUnit\Framework\TestCase;

final class NowPlayingServiceTest extends TestCase
{
    /**
     * @param list<PlexSession> $sessions
     */
    private function service(array $sessions, bool $configured = true): NowPlayingService
    {
        $client = new FakePlexClient(configured: $configured, sessions: $sessions);

        return new NowPlayingService($client, new StreamToken('secret'));
    }

    public function testDropsMusicSessions(): void
    {
        $tiles = $this->service([
            new PlexSession(PlexSessionType::Music, 'A Song', 'dj', false),
            new PlexSession(PlexSessionType::Movie, 'Dune', 'jereme', false, '/t/1', year: 2021),
        ])->tiles();

        self::assertCount(1, $tiles);
        self::assertSame('Dune', $tiles[0]->title);
        self::assertSame('2021 · Movie', $tiles[0]->detail);
        self::assertSame('jereme', $tiles[0]->user);
        self::assertFalse($tiles[0]->live);
    }

    public function testKeepsTwoSessionsOfTheSameTitleAsSeparateTiles(): void
    {
        $tiles = $this->service([
            new PlexSession(PlexSessionType::Movie, 'Dune', 'jereme', false, '/t/1', year: 2021),
            new PlexSession(PlexSessionType::Movie, 'Dune', 'kim', false, '/t/1', year: 2021),
        ])->tiles();

        self::assertCount(2, $tiles);
        self::assertSame('jereme', $tiles[0]->user);
        self::assertSame('kim', $tiles[1]->user);
    }

    public function testEpisodeUsesShowTitleAndEpisodeDetail(): void
    {
        $tiles = $this->service([
            new PlexSession(
                PlexSessionType::Episode,
                'Free Churro',
                'kim',
                false,
                '/t/show',
                grandparentTitle: 'BoJack Horseman',
                seasonNumber: 6,
                episodeNumber: 6,
            ),
        ])->tiles();

        self::assertSame('BoJack Horseman', $tiles[0]->title);
        self::assertSame('S06E06 · Free Churro', $tiles[0]->detail);
    }

    public function testLiveTvTileIsFlaggedAndUsesPlaceholderToken(): void
    {
        $tiles = $this->service([
            new PlexSession(PlexSessionType::LiveTv, 'SportsCenter', 'guest', true, grandparentTitle: 'ESPN'),
        ])->tiles();

        self::assertTrue($tiles[0]->live);
        self::assertSame('SportsCenter', $tiles[0]->title);
        self::assertSame('ESPN · Live TV', $tiles[0]->detail);
        self::assertSame('/wall/stream-poster/' . StreamToken::LIVE, $tiles[0]->posterUrl);
    }

    public function testNoTilesWhenPlexUnconfigured(): void
    {
        self::assertSame([], $this->service([
            new PlexSession(PlexSessionType::Movie, 'Dune', 'jereme', false, '/t/1'),
        ], configured: false)->tiles());
    }
}
