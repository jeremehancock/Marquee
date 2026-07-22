<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Plex\PlexMediaType;
use App\Poster\Source\PosteriaApiPosterSource;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PosteriaApiPosterSourceTest extends TestCase
{
    /**
     * @param list<Response|ConnectException> $afterTime responses after the time-sync call
     */
    private function source(array $afterTime): PosteriaApiPosterSource
    {
        $time = new Response(200, [], (string) json_encode(['server_time' => (int) round(microtime(true) * 1000)]));
        $handler = HandlerStack::create(new MockHandler(array_merge([$time], $afterTime)));

        return new PosteriaApiPosterSource(new Client(['handler' => $handler]), 'https://posteria.app');
    }

    public function testExtractsPosterUrlsInPreferenceOrder(): void
    {
        $body = (string) json_encode([
            'success' => true,
            'results' => [
                ['poster' => ['original' => 'https://img/a.jpg', 'large' => 'https://img/a-l.jpg']],
                ['poster' => ['large' => 'https://img/b.jpg']],
                ['poster' => []],
            ],
        ]);

        $result = $this->source([new Response(200, [], $body)])->find('Solaris', PlexMediaType::Movie, null);

        self::assertSame(['https://img/a.jpg', 'https://img/b.jpg'], $result);
    }

    public function testSeasonUsesSeasonPoster(): void
    {
        $body = (string) json_encode([
            'success' => true,
            'results' => [['season' => ['poster' => ['original' => 'https://img/s1.jpg']], 'poster' => ['original' => 'https://img/show.jpg']]],
        ]);

        $result = $this->source([new Response(200, [], $body)])
            ->find('Severance - Season 1', PlexMediaType::Season, null);

        self::assertSame(['https://img/s1.jpg'], $result);
    }

    public function testNoResultsReturnsEmpty(): void
    {
        $body = (string) json_encode(['success' => true, 'results' => []]);

        self::assertSame([], $this->source([new Response(200, [], $body)])->find('Nope', PlexMediaType::Movie, null));
    }

    public function testRequestFailureReturnsEmpty(): void
    {
        $error = new ConnectException('down', new Request('GET', '/api/fetch/posters'));

        self::assertSame([], $this->source([$error])->find('Solaris', PlexMediaType::Movie, null));
    }
}
