<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Config\LibraryExclusions;
use App\Config\PlexConfig;
use App\Plex\HttpPlexClient;
use App\Plex\PlexException;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Plex\PlexSessionType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class HttpPlexClientTest extends TestCase
{
    /**
     * @param list<Response|ConnectException> $responses
     * @param list<string>                    $excluded
     */
    private function client(array $responses, bool $configured = true, array $excluded = []): HttpPlexClient
    {
        $guzzle = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
        $config = $configured
            ? new PlexConfig('http://plex:32400', 'token', 10, 60)
            : new PlexConfig('', '', 10, 60);

        return new HttpPlexClient($guzzle, $config, new LibraryExclusions($excluded));
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

    public function testExcludedLibrariesAreNeverReported(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory key="1" type="movie" title="Movies"/>'
            . '<Directory key="2" type="movie" title="Kids Movies"/>'
            . '<Directory key="3" type="show" title="TV"/>'
            . '</MediaContainer>';

        $libraries = $this->client([new Response(200, [], $xml)], excluded: ['kids movies'])->libraries();

        self::assertSame(['Movies', 'TV'], array_map(static fn ($l) => $l->title, $libraries));
    }

    public function testEveryLibraryCanBeExcluded(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory key="1" type="movie" title="Movies"/>'
            . '<Directory key="2" type="show" title="TV"/>'
            . '</MediaContainer>';

        $libraries = $this->client([new Response(200, [], $xml)], excluded: ['Movies', 'TV'])->libraries();

        self::assertSame([], $libraries);
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

    public function testParsesAddedAtTimestampWhenPresent(): void
    {
        $xml = '<MediaContainer>'
            . '<Video ratingKey="10" type="movie" title="Solaris" thumb="/t/10" addedAt="1700000000"/>'
            . '<Video ratingKey="11" type="movie" title="Dune" thumb="/t/11"/>'
            . '</MediaContainer>';

        $items = $this->client([new Response(200, [], $xml)])->items(new PlexLibrary('1', 'Movies', 'movie'));

        self::assertSame(1700000000, $items[0]->addedAt);
        // Absent (or non-positive) addedAt surfaces as null so the gallery can
        // fall back to the file's modification time.
        self::assertNull($items[1]->addedAt);
    }

    public function testParsesSeasonsWithParentTitle(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="20" type="season" title="Season 1" thumb="/t/20" index="1"/>'
            . '<Directory ratingKey="21" type="season" title="Season 2" thumb="/t/21" index="2"/>'
            . '</MediaContainer>';

        $show = new PlexItem('2', PlexMediaType::Show, 'Severance', null, '/t/2', 'TV');
        $seasons = $this->client([new Response(200, [], $xml)])->seasons($show);

        self::assertCount(2, $seasons);
        self::assertSame('Severance - Season 1', $seasons[0]->displayTitle());
        self::assertSame(1, $seasons[0]->seasonNumber);
        self::assertSame(2, $seasons[1]->seasonNumber);
    }

    /**
     * Plex gives Specials index="0". That has to survive as 0 rather than being
     * read as "no index", which is what the addedAt-style "non-positive means
     * absent" rule would have done.
     */
    public function testParsesSpecialsSeasonIndexAsZero(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="19" type="season" title="Specials" thumb="/t/19" index="0"/>'
            . '</MediaContainer>';

        $show = new PlexItem('2', PlexMediaType::Show, 'Severance', null, '/t/2', 'TV');
        $seasons = $this->client([new Response(200, [], $xml)])->seasons($show);

        self::assertNotNull($seasons[0]->seasonNumber);
        self::assertSame(0, $seasons[0]->seasonNumber);
    }

    public function testSeasonWithNoIndexAttributeHasNoSeasonNumber(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="22" type="season" title="Season 3" thumb="/t/22"/>'
            . '</MediaContainer>';

        $show = new PlexItem('2', PlexMediaType::Show, 'Severance', null, '/t/2', 'TV');
        $seasons = $this->client([new Response(200, [], $xml)])->seasons($show);

        self::assertNull($seasons[0]->seasonNumber);
    }

    public function testReadsTmdbIdFromGuidChildren(): void
    {
        $xml = '<MediaContainer>'
            . '<Video ratingKey="10" type="movie" title="Spider-Noir" year="2026" thumb="/t/10">'
            . '<Guid id="imdb://tt5433138"/>'
            . '<Guid id="tmdb://385128"/>'
            . '<Guid id="tvdb://8856"/>'
            . '</Video>'
            . '</MediaContainer>';

        $items = $this->client([new Response(200, [], $xml)])->items(new PlexLibrary('1', 'Movies', 'movie'));

        // Only the TMDB id is kept; imdb and tvdb are ignored on purpose.
        self::assertSame('385128', $items[0]->tmdbId);
    }

    public function testItemWithoutATmdbGuidHasNoTmdbId(): void
    {
        $xml = '<MediaContainer>'
            . '<Video ratingKey="10" type="movie" title="Home Video" thumb="/t/10">'
            . '<Guid id="tvdb://8856"/>'
            . '</Video>'
            . '<Video ratingKey="11" type="movie" title="Unmatched" thumb="/t/11"/>'
            . '</MediaContainer>';

        $items = $this->client([new Response(200, [], $xml)])->items(new PlexLibrary('1', 'Movies', 'movie'));

        self::assertNull($items[0]->tmdbId);
        self::assertNull($items[1]->tmdbId);
    }

    /**
     * Modern agents put an opaque `plex://` hash in the element's own `guid`
     * attribute. It must never be mistaken for an external id, including when
     * it is the only guid the item has.
     */
    public function testOpaquePlexGuidAttributeIsNotReadAsATmdbId(): void
    {
        $xml = '<MediaContainer>'
            . '<Video ratingKey="10" type="movie" title="Solaris" thumb="/t/10"'
            . ' guid="plex://movie/5d7768264de0ee001fcc87e0"/>'
            . '</MediaContainer>';

        $items = $this->client([new Response(200, [], $xml)])->items(new PlexLibrary('1', 'Movies', 'movie'));

        self::assertNull($items[0]->tmdbId);
    }

    /**
     * A legacy-agent library puts the id back in the attribute. It is left
     * unparsed by design — those items keep using title matching.
     */
    public function testLegacyAgentGuidAttributeIsNotParsed(): void
    {
        $xml = '<MediaContainer>'
            . '<Video ratingKey="10" type="movie" title="Solaris" thumb="/t/10"'
            . ' guid="com.plexapp.agents.themoviedb://1726?lang=en"/>'
            . '</MediaContainer>';

        $items = $this->client([new Response(200, [], $xml)])->items(new PlexLibrary('1', 'Movies', 'movie'));

        self::assertNull($items[0]->tmdbId);
    }

    /**
     * A season is addressed as a show plus a season number, so it carries the
     * show's id rather than one of its own.
     */
    public function testSeasonsInheritTheShowsTmdbId(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="20" type="season" title="Season 1" thumb="/t/20" index="1"/>'
            . '<Directory ratingKey="19" type="season" title="Specials" thumb="/t/19" index="0"/>'
            . '</MediaContainer>';

        $show = new PlexItem('2', PlexMediaType::Show, 'Severance', null, '/t/2', 'TV', tmdbId: '95396');
        $seasons = $this->client([new Response(200, [], $xml)])->seasons($show);

        self::assertSame('95396', $seasons[0]->tmdbId);
        self::assertSame('95396', $seasons[1]->tmdbId);
        self::assertSame(0, $seasons[1]->seasonNumber);
    }

    public function testSeasonsOfAShowWithNoTmdbIdHaveNone(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="20" type="season" title="Season 1" thumb="/t/20" index="1"/>'
            . '</MediaContainer>';

        $show = new PlexItem('2', PlexMediaType::Show, 'Home Movies', null, '/t/2', 'TV');
        $seasons = $this->client([new Response(200, [], $xml)])->seasons($show);

        self::assertNull($seasons[0]->tmdbId);
    }

    /**
     * For the same reason a season carries the show's id: a season search
     * resolves the show first, so the show's year is the one that identifies
     * the work. Without it a title fallback cannot separate two shows sharing
     * a title, and the season's recorded id can be corrected to the wrong one.
     */
    public function testSeasonsInheritTheShowsYear(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="20" type="season" title="Season 1" thumb="/t/20" index="1"/>'
            . '<Directory ratingKey="19" type="season" title="Specials" thumb="/t/19" index="0"/>'
            . '</MediaContainer>';

        $show = new PlexItem('2', PlexMediaType::Show, 'The Office', 2005, '/t/2', 'TV', tmdbId: '2316');
        $seasons = $this->client([new Response(200, [], $xml)])->seasons($show);

        self::assertSame(2005, $seasons[0]->year);
        self::assertSame(2005, $seasons[1]->year);
    }

    /**
     * The season node carries no year of its own, so a show Plex reports no
     * year for yields seasons with none rather than failing the import.
     */
    public function testSeasonsOfAShowWithNoYearHaveNone(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="20" type="season" title="Season 1" thumb="/t/20" index="1"/>'
            . '</MediaContainer>';

        $show = new PlexItem('2', PlexMediaType::Show, 'Home Movies', null, '/t/2', 'TV');
        $seasons = $this->client([new Response(200, [], $xml)])->seasons($show);

        self::assertNull($seasons[0]->year);
    }

    public function testItemListingRequestsGuids(): void
    {
        $mock = new MockHandler([new Response(200, [], '<MediaContainer/>')]);
        $this->recordingClient($mock)->items(new PlexLibrary('1', 'Movies', 'movie'));

        $request = $this->lastRequest($mock);
        self::assertSame('/library/sections/1/all', $request->getUri()->getPath());
        self::assertStringContainsString('includeGuids=1', $request->getUri()->getQuery());
    }

    public function testCollectionListingRequestsGuids(): void
    {
        $mock = new MockHandler([new Response(200, [], '<MediaContainer/>')]);
        $this->recordingClient($mock)->collections(new PlexLibrary('1', 'Movies', 'movie'));

        $request = $this->lastRequest($mock);
        self::assertSame('/library/sections/1/collections', $request->getUri()->getPath());
        self::assertStringContainsString('includeGuids=1', $request->getUri()->getQuery());
    }

    /**
     * A Plex collection is a local grouping with no upstream record, so it has
     * no id even though the listing is asked for guids.
     */
    public function testCollectionsHaveNoTmdbId(): void
    {
        $xml = '<MediaContainer>'
            . '<Directory ratingKey="30" type="collection" title="Christmas Movies" thumb="/t/30"/>'
            . '</MediaContainer>';

        $items = $this->client([new Response(200, [], $xml)])->collections(new PlexLibrary('1', 'Movies', 'movie'));

        self::assertNull($items[0]->tmdbId);
    }

    public function testDownloadsPosterBytes(): void
    {
        $item = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');

        $bytes = $this->client([new Response(200, [], 'IMAGE-BYTES')])->downloadPoster($item);

        self::assertSame('IMAGE-BYTES', $bytes);
    }

    public function testParsesSessionsByType(): void
    {
        $xml = '<MediaContainer>'
            . '<Video type="movie" title="The Matrix" year="1999" thumb="/t/movie">'
            . '<User title="jereme"/></Video>'
            . '<Video type="episode" title="Free Churro" grandparentTitle="BoJack Horseman"'
            . ' grandparentThumb="/t/show" parentIndex="6" index="6"><User title="kim"/></Video>'
            . '<Video type="clip" live="1" title="SportsCenter" grandparentTitle="ESPN">'
            . '<User title="guest"/></Video>'
            . '<Track type="track" title="A Song"><User title="dj"/></Track>'
            . '</MediaContainer>';

        $sessions = $this->client([new Response(200, [], $xml)])->sessions();

        self::assertCount(4, $sessions);

        self::assertSame(PlexSessionType::Movie, $sessions[0]->type);
        self::assertSame('The Matrix', $sessions[0]->title);
        self::assertSame(1999, $sessions[0]->year);
        self::assertSame('/t/movie', $sessions[0]->thumb);
        self::assertSame('jereme', $sessions[0]->user);
        self::assertFalse($sessions[0]->live);

        self::assertSame(PlexSessionType::Episode, $sessions[1]->type);
        self::assertSame('BoJack Horseman', $sessions[1]->grandparentTitle);
        self::assertSame('/t/show', $sessions[1]->thumb);
        self::assertSame('S06E06', $sessions[1]->episodeLabel());
        self::assertSame('kim', $sessions[1]->user);

        self::assertSame(PlexSessionType::LiveTv, $sessions[2]->type);
        self::assertTrue($sessions[2]->live);
        self::assertSame('ESPN', $sessions[2]->grandparentTitle);

        self::assertSame(PlexSessionType::Music, $sessions[3]->type);
    }

    public function testNonLiveClipIsClassifiedAsOther(): void
    {
        $xml = '<MediaContainer>'
            . '<Video type="clip" title="Trailer"><User title="jereme"/></Video>'
            . '</MediaContainer>';

        $sessions = $this->client([new Response(200, [], $xml)])->sessions();

        self::assertSame(PlexSessionType::Other, $sessions[0]->type);
    }

    /**
     * A DVR tuner reports its programmes with a library media type, so the
     * `live` flag is what identifies them.
     */
    public function testLiveEpisodeIsClassifiedAsLiveTv(): void
    {
        $xml = '<MediaContainer>'
            . '<Video type="episode" live="1" title="Evening News" grandparentTitle="Channel 4"'
            . ' grandparentThumb="https://tuner.example/artwork/poster" parentIndex="1" index="3">'
            . '<User title="jereme"/></Video>'
            . '</MediaContainer>';

        $sessions = $this->client([new Response(200, [], $xml)])->sessions();

        self::assertSame(PlexSessionType::LiveTv, $sessions[0]->type);
        self::assertTrue($sessions[0]->live);
        self::assertSame('Evening News', $sessions[0]->title);
        self::assertSame('Channel 4', $sessions[0]->grandparentTitle);
        // The tuner's artwork URL is not a Plex library path, so it is dropped.
        self::assertNull($sessions[0]->thumb);
    }

    public function testLiveMovieIsClassifiedAsLiveTv(): void
    {
        $xml = '<MediaContainer>'
            . '<Video type="movie" live="1" title="Late Show" grandparentTitle="Channel 9"'
            . ' thumb="https://tuner.example/artwork/poster" year="1998">'
            . '<User title="kim"/></Video>'
            . '</MediaContainer>';

        $sessions = $this->client([new Response(200, [], $xml)])->sessions();

        self::assertSame(PlexSessionType::LiveTv, $sessions[0]->type);
        self::assertSame('Late Show', $sessions[0]->title);
        self::assertSame('Channel 9', $sessions[0]->grandparentTitle);
    }

    public function testLiveTrackStaysMusic(): void
    {
        $xml = '<MediaContainer>'
            . '<Track type="track" live="1" title="Radio Stream"><User title="dj"/></Track>'
            . '</MediaContainer>';

        $sessions = $this->client([new Response(200, [], $xml)])->sessions();

        self::assertSame(PlexSessionType::Music, $sessions[0]->type);
        self::assertTrue($sessions[0]->live);
    }

    public function testNonLiveEpisodeIsStillAnEpisode(): void
    {
        $xml = '<MediaContainer>'
            . '<Video type="episode" live="0" title="Free Churro" grandparentTitle="BoJack Horseman"'
            . ' grandparentThumb="/t/show" parentIndex="6" index="6"><User title="kim"/></Video>'
            . '</MediaContainer>';

        $sessions = $this->client([new Response(200, [], $xml)])->sessions();

        self::assertSame(PlexSessionType::Episode, $sessions[0]->type);
        self::assertFalse($sessions[0]->live);
        self::assertSame('/t/show', $sessions[0]->thumb);
        self::assertSame('S06E06', $sessions[0]->episodeLabel());
    }

    public function testSessionWithoutUserYieldsEmptyUser(): void
    {
        $xml = '<MediaContainer><Video type="movie" title="Solaris" thumb="/t/1"/></MediaContainer>';

        $sessions = $this->client([new Response(200, [], $xml)])->sessions();

        self::assertSame('', $sessions[0]->user);
    }

    public function testEmptySessionsWhenNothingPlaying(): void
    {
        $sessions = $this->client([new Response(200, [], '<MediaContainer/>')])->sessions();

        self::assertSame([], $sessions);
    }

    public function testSessionPosterFetchesBytesAtThumbPath(): void
    {
        $bytes = $this->client([new Response(200, [], 'POSTER-BYTES')])->sessionPoster('/t/show');

        self::assertSame('POSTER-BYTES', $bytes);
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

    public function testFetchingAGoneItemReportsItMayBeOrphaned(): void
    {
        $this->expectExceptionMessage('This item no longer exists in Plex, so the poster may be orphaned. Check the Orphans page.');
        $this->client([new Response(404)])->itemPoster('999');
    }

    public function testSendingToAGoneItemReportsItMayBeOrphaned(): void
    {
        $this->expectExceptionMessage('This item no longer exists in Plex, so the poster may be orphaned. Check the Orphans page.');
        $this->client([new Response(404)])->uploadPoster('999', 'IMAGE-BYTES');
    }

    public function testRejectedTokenReportsAnAuthProblem(): void
    {
        // The remedy is no longer baked in here; PlexFailureMessage supplies
        // one that matches the connection source.
        $this->expectExceptionMessage('The Plex server rejected the credential.');
        $this->client([new Response(401)])->libraries();
    }

    public function testTransportFailureStillReportsAConnectionProblem(): void
    {
        $error = new ConnectException('down', new Request('GET', '/library/sections'));

        $this->expectExceptionMessage('Could not connect to the Plex server.');
        $this->client([$error])->libraries();
    }

    public function testServerNameIsReadFromTheRootEndpoint(): void
    {
        // Shaped like a real Plex root response, including the account field
        // that must not be used.
        $xml = '<MediaContainer size="26" friendlyName="Anansi" '
            . 'machineIdentifier="7c85f9bcd13e3aa1df7ac77edc7cfa8934931e5e" '
            . 'myPlexUsername="someone@example.com" myPlexSigninState="ok"/>';

        self::assertSame('Anansi', $this->client([new Response(200, [], $xml)])->serverName());
    }

    public function testServerNameIsNullWhenTheAttributeIsAbsent(): void
    {
        $xml = '<MediaContainer size="26" myPlexUsername="someone@example.com"/>';

        self::assertNull($this->client([new Response(200, [], $xml)])->serverName());
    }

    public function testServerNameIsNullWhenTheRequestFails(): void
    {
        $error = new ConnectException('down', new Request('GET', '/'));

        // A name is decoration; failing to read it must not raise.
        self::assertNull($this->client([$error])->serverName());
    }

    public function testServerNameIsNullWhenPlexIsNotConfigured(): void
    {
        self::assertNull($this->client([], configured: false)->serverName());
    }

    public function testServerNameNeverReportsTheAccountEmail(): void
    {
        $xml = '<MediaContainer friendlyName="Anansi" myPlexUsername="someone@example.com"/>';

        $name = $this->client([new Response(200, [], $xml)])->serverName();

        self::assertNotNull($name);
        self::assertStringNotContainsString('@', $name);
    }

    private function recordingClient(MockHandler $mock): HttpPlexClient
    {
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);

        return new HttpPlexClient($guzzle, new PlexConfig('http://plex:32400', 'token', 10, 60), new LibraryExclusions([]));
    }

    public function testUploadPosterPostsImageBytes(): void
    {
        $mock = new MockHandler([new Response(200), new Response(200), new Response(200)]);
        $this->recordingClient($mock)->uploadPoster('10', 'IMAGE-BYTES');

        $request = $this->lastRequest($mock);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/library/metadata/10/posters', $request->getUri()->getPath());
        self::assertSame('IMAGE-BYTES', (string) $request->getBody());
    }

    public function testLockPosterPutsLockFlag(): void
    {
        $mock = new MockHandler([new Response(200), new Response(200), new Response(200)]);
        $this->recordingClient($mock)->lockPoster('10');

        $request = $this->lastRequest($mock);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/library/metadata/10', $request->getUri()->getPath());
        self::assertStringContainsString('thumb.locked=1', $request->getUri()->getQuery());
    }

    public function testRemoveOverlayLabelPutsLabelEdit(): void
    {
        $mock = new MockHandler([new Response(200), new Response(200), new Response(200)]);
        $this->recordingClient($mock)->removeOverlayLabel('5', 1, '10');

        $request = $this->lastRequest($mock);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/library/sections/5/all', $request->getUri()->getPath());
        $query = urldecode($request->getUri()->getQuery());
        self::assertStringContainsString('type=1', $query);
        self::assertStringContainsString('id=10', $query);
        self::assertStringContainsString('Overlay', $query);
    }

    private function lastRequest(MockHandler $mock): RequestInterface
    {
        $request = $mock->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }
}
