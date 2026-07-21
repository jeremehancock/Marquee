<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Config\PlexConfig;
use App\Plex\HttpPlexClient;
use App\Plex\PlexException;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class HttpPlexClientTest extends TestCase
{
    /**
     * @param list<Response|ConnectException> $responses
     */
    private function client(array $responses, bool $configured = true): HttpPlexClient
    {
        $guzzle = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
        $config = $configured
            ? new PlexConfig('http://plex:32400', 'token', 10, 60)
            : new PlexConfig('', '', 10, 60);

        return new HttpPlexClient($guzzle, $config);
    }

    public function testListsMovieAndShowLibrariesOnly(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory key="1" type="movie" title="Movies"/>'
            . '<Directory key="2" type="show" title="TV"/>'
            . '<Directory key="3" type="artist" title="Music"/>'
            . '</MediaContainer>';

        $libraries = $this->client([new Response(200, [], $xml)])->libraries();

        self::assertCount(2, $libraries);
        self::assertSame('Movies', $libraries[0]->title);
        self::assertTrue($libraries[0]->isMovie());
        self::assertTrue($libraries[1]->isShow());
    }

    public function testParsesMovieItems(): void
    {
        $xml = '<MediaContainer>'
            . '<Video ratingKey="10" type="movie" title="Solaris" year="1972" thumb="/t/10"/>'
            . '<Video ratingKey="11" type="movie" title="Dune" year="2021" thumb="/t/11"/>'
            . '</MediaContainer>';

        $items = $this->client([new Response(200, [], $xml)])->items(new PlexLibrary('1', 'Movies', 'movie'));

        self::assertCount(2, $items);
        self::assertSame('Solaris', $items[0]->title);
        self::assertSame(1972, $items[0]->year);
        self::assertSame(PlexMediaType::Movie, $items[0]->mediaType);
        self::assertSame('/t/10', $items[0]->thumb);
    }

    public function testParsesSeasonsWithParentTitle(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="20" type="season" title="Season 1" thumb="/t/20"/>'
            . '<Directory ratingKey="21" type="season" title="Season 2" thumb="/t/21"/>'
            . '</MediaContainer>';

        $show = new PlexItem('2', PlexMediaType::Show, 'Severance', null, '/t/2', 'TV');
        $seasons = $this->client([new Response(200, [], $xml)])->seasons($show);

        self::assertCount(2, $seasons);
        self::assertSame('Severance - Season 1', $seasons[0]->displayTitle());
    }

    public function testDownloadsPosterBytes(): void
    {
        $item = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');

        $bytes = $this->client([new Response(200, [], 'IMAGE-BYTES')])->downloadPoster($item);

        self::assertSame('IMAGE-BYTES', $bytes);
    }

    public function testUnconfiguredServerThrows(): void
    {
        $this->expectException(PlexException::class);
        $this->client([], configured: false)->libraries();
    }

    public function testConnectionErrorThrows(): void
    {
        $error = new ConnectException('down', new Request('GET', '/library/sections'));

        $this->expectException(PlexException::class);
        $this->client([$error])->libraries();
    }
}
