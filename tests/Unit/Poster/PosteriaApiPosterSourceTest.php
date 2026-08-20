<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Plex\PlexMediaType;
use App\Poster\Source\PosteriaApiPosterSource;
use App\Poster\Source\PosterQuery;
use App\Poster\Source\PosterSearchOutcome;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

final class PosteriaApiPosterSourceTest extends TestCase
{
    /** @var list<RequestInterface> every request the source actually sent */
    private array $sent = [];

    /**
     * @param list<Response|ConnectException> $responses
     */
    private function source(array $responses): PosteriaApiPosterSource
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        // Recording the requests here rather than with Guzzle's history
        // middleware: history() takes its container by reference, which widens
        // the type of whatever is handed to it.
        $stack->push(function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler): mixed {
                $this->sent[] = $request;

                return $handler($request, $options);
            };
        });

        return new PosteriaApiPosterSource(
            new Client(['handler' => $stack]),
            'https://posteria.app',
            '1.2.3',
            new NullLogger(),
        );
    }

    /**
     * @param list<array<string, mixed>> $posters
     * @param array<string, mixed>       $extra
     */
    private function ok(array $posters, array $extra = []): Response
    {
        return new Response(200, [], (string) json_encode($extra + [
            'success' => true,
            'posters' => $posters,
            'total' => count($posters),
        ]));
    }

    private function error(int $status, string $code): Response
    {
        return new Response($status, [], (string) json_encode([
            'success' => false,
            'code' => $code,
            'error' => 'nope',
        ]));
    }

    private function sentRequest(): RequestInterface
    {
        self::assertNotSame([], $this->sent, 'expected a request to have been sent');

        return $this->sent[0];
    }

    private function sentUri(): string
    {
        return (string) $this->sentRequest()->getUri();
    }

    /**
     * @return array<string, string>
     */
    private function sentQuery(): array
    {
        $query = [];
        parse_str($this->sentRequest()->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    // ---- Request shape ----

    public function testRequestsTheMarqueeEndpointWithTitleAndType(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('The Matrix', PlexMediaType::Movie, year: 1999));

        self::assertStringStartsWith('https://posteria.app/marquee/api/v2/posters?', $this->sentUri());
        self::assertSame(
            ['q' => 'The Matrix', 'type' => 'movie', 'year' => '1999'],
            $this->sentQuery(),
        );
    }

    public function testMakesExactlyOneRequestWithNoTimeSync(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertCount(1, $this->sent, 'the server time round trip must be gone');
        self::assertStringNotContainsString('time', $this->sentUri());
    }

    public function testSendsMarqueeClientInfoWithVersionAndNoApiKey(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        $header = $this->sentRequest()->getHeaderLine('X-Client-Info');
        $payload = json_decode((string) base64_decode($header, true), true);

        self::assertIsArray($payload);
        self::assertSame('Marquee', $payload['name']);
        self::assertSame('1.2.3', $payload['version']);
        self::assertIsInt($payload['ts']);
        self::assertGreaterThan(1_600_000_000_000, $payload['ts'], 'ts is milliseconds');

        self::assertStringNotContainsString('key=', $this->sentUri());
        self::assertStringNotContainsString('Posteria', $this->sentRequest()->getHeaderLine('User-Agent'));
    }

    public function testOmitsYearWhenNotKnown(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertArrayNotHasKey('year', $this->sentQuery());
    }

    public function testShowUsesTheShowTypeToken(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad', PlexMediaType::Show));

        self::assertSame('show', $this->sentQuery()['type']);
    }

    // ---- Season, including Specials ----

    public function testSeasonSendsTheStoredNumberAndTheShowTitle(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/s2.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad - Season 2', PlexMediaType::Season, seasonNumber: 2));

        self::assertSame(
            ['q' => 'Breaking Bad', 'type' => 'season', 'season' => '2'],
            $this->sentQuery(),
        );
    }

    public function testSpecialsSendsSeasonZeroRatherThanOmittingIt(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/sp.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad - Specials', PlexMediaType::Season, seasonNumber: 0));

        $query = $this->sentQuery();
        self::assertArrayHasKey('season', $query);
        self::assertSame('0', $query['season'], 'season 0 is Specials, not a missing value');
    }

    public function testTransitionalFallbackDerivesSeasonFromTitle(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/s3.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad - Season 3', PlexMediaType::Season));

        self::assertSame(
            ['q' => 'Breaking Bad', 'type' => 'season', 'season' => '3'],
            $this->sentQuery(),
        );
    }

    public function testTransitionalFallbackMapsSpecialsToZero(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/sp.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad - Specials', PlexMediaType::Season));

        self::assertSame('0', $this->sentQuery()['season']);
    }

    public function testTransitionalFallbackMapsS00ToZero(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/sp.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad - S00', PlexMediaType::Season));

        self::assertSame('0', $this->sentQuery()['season']);
    }

    public function testStoredSeasonNumberWinsOverTheTitle(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/s2.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad - Season 9', PlexMediaType::Season, seasonNumber: 2));

        self::assertSame('2', $this->sentQuery()['season']);
    }

    public function testSeasonWithNoDiscoverableNumberIsNotSearched(): void
    {
        $source = $this->source([]);
        $result = $source->find(new PosterQuery('A Show With No Season Marker', PlexMediaType::Season));

        self::assertSame(PosterSearchOutcome::NoMatch, $result->outcome);
        self::assertCount(0, $this->sent, 'nothing to search for, so no request is made');
    }

    public function testBlankTitleIsNotSearched(): void
    {
        $source = $this->source([]);
        $result = $source->find(new PosterQuery('   ', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::NoMatch, $result->outcome);
        self::assertCount(0, $this->sent);
    }

    // ---- TMDB id on the request ----

    public function testSendsTheRecordedTmdbIdForAMovie(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: '603'));

        self::assertSame('603', $this->sentQuery()['tmdb_id']);
    }

    public function testSendsTheRecordedTmdbIdForAShow(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad', PlexMediaType::Show, tmdbId: '1396'));

        self::assertSame('1396', $this->sentQuery()['tmdb_id']);
    }

    public function testTitleIsStillSentAlongsideAnId(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('Spider-Noir B&W', PlexMediaType::Movie, year: 2026, tmdbId: '603'));

        self::assertSame(
            ['q' => 'Spider-Noir B&W', 'type' => 'movie', 'year' => '2026', 'tmdb_id' => '603'],
            $this->sentQuery(),
            'q stays required and year stays unconditional; the year feeds the title fallback when the id is unknown',
        );
    }

    public function testOmitsTheIdWhenNoneIsRecorded(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertArrayNotHasKey('tmdb_id', $this->sentQuery());
    }

    public function testNeverSendsAnIdForACollectionEvenWhenOneIsRecorded(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('Star Wars', PlexMediaType::Collection, tmdbId: '10'));

        self::assertArrayNotHasKey(
            'tmdb_id',
            $this->sentQuery(),
            'a Plex collection is a local grouping; type=collection takes no id',
        );
        self::assertSame('Star Wars', $this->sentQuery()['q']);
    }

    /**
     * A value the endpoint would reject with a 400 — which the user would read
     * as "temporarily unavailable" — is treated as no id at all.
     */
    #[DataProvider('unusableIds')]
    public function testOmitsAnIdThatIsNotAPositiveWholeNumber(string $id): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/a.jpg']])]);
        $source->find(new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: $id));

        self::assertArrayNotHasKey('tmdb_id', $this->sentQuery());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableIds(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'zero' => ['0'],
            'negative' => ['-5'],
            'decimal' => ['12.5'],
            'non-numeric' => ['tt0133093'],
            'prefixed' => ['tmdb://603'],
        ];
    }

    public function testSeasonSendsTheShowsIdWithTheSeasonNumber(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/s2.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad - Season 2', PlexMediaType::Season, seasonNumber: 2, tmdbId: '1396'));

        self::assertSame(
            ['q' => 'Breaking Bad', 'type' => 'season', 'season' => '2', 'tmdb_id' => '1396'],
            $this->sentQuery(),
            'a season is addressed as its show plus a season number; there is no season-level id',
        );
    }

    public function testSpecialsSendsTheShowsIdWithSeasonZero(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/sp.jpg']])]);
        $source->find(new PosterQuery('Breaking Bad - Specials', PlexMediaType::Season, seasonNumber: 0, tmdbId: '1396'));

        $query = $this->sentQuery();
        self::assertSame('0', $query['season']);
        self::assertSame('1396', $query['tmdb_id']);
    }

    /**
     * The show's year rides along with the show's id, not instead of it. While
     * the id resolves the year does nothing, but that is the only case: when the
     * id is not one the endpoint knows it falls back to resolving the title, and
     * the year is what separates two shows sharing one. The endpoint reads it as
     * the show's first-air year on a season search, which is what Plex reports
     * for a show.
     */
    public function testSeasonSendsTheShowsYearAlongsideItsId(): void
    {
        $source = $this->source([$this->ok([['url' => 'https://img/s2.jpg']])]);
        $source->find(new PosterQuery('The Office - Season 2', PlexMediaType::Season, seasonNumber: 2, year: 2005, tmdbId: '2316'));

        self::assertSame(
            ['q' => 'The Office', 'type' => 'season', 'season' => '2', 'year' => '2005', 'tmdb_id' => '2316'],
            $this->sentQuery(),
            'the year is not suppressed because an id is present; it is what rescues the title fallback',
        );
    }

    // ---- Correcting a stale TMDB id ----

    /**
     * Shaped like the live endpoint, which is the point of these fixtures: it
     * reports the id it *resolved* in `query.tmdb_id`, not the one it was sent,
     * so both ids in the response always agree. Detection cannot come from
     * comparing them — a search that sends no id at all still gets one back
     * there. See design Decision 1 of `fix-stale-tmdb-id-detection`.
     *
     * @param list<array<string, mixed>> $posters
     */
    private function resolved(int $tmdbId, array $posters = [['url' => 'https://img/a.jpg']]): Response
    {
        return $this->ok($posters, [
            'query' => ['tmdb_id' => $tmdbId],
            'match' => ['tmdb_id' => $tmdbId],
        ]);
    }

    public function testDetectsAStaleIdEvenThoughTheResponseAgreesWithItself(): void
    {
        // The case that failed in the field: 111 is unknown upstream, so the
        // endpoint resolved the title to 603 and reported 603 in *both* places.
        $result = $this->source([$this->resolved(603)])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: '111'),
        );

        self::assertSame('603', $result->correctedTmdbId, 'the id we sent was unknown upstream; this is the real one');
        self::assertCount(1, $this->sent, 'a correction is read from the response, not fetched');
    }

    public function testReportsNoCorrectionWhenTheMatchedIdAgrees(): void
    {
        $result = $this->source([$this->resolved(603)])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: '603'),
        );

        self::assertNull($result->correctedTmdbId);
    }

    public function testReportsNoCorrectionWhenNoIdWasSent(): void
    {
        $result = $this->source([$this->resolved(603)])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie),
        );

        self::assertNull(
            $result->correctedTmdbId,
            'a title-resolved id for an item that has none is a guess, not a correction',
        );
    }

    public function testReportsNoCorrectionForACollectionWhoseRecordedIdWasWithheld(): void
    {
        $result = $this->source([$this->resolved(10)])->find(
            new PosterQuery('Star Wars', PlexMediaType::Collection, tmdbId: '999'),
        );

        self::assertNull(
            $result->correctedTmdbId,
            'nothing was sent, so there is nothing for the response to disagree with',
        );
    }

    public function testReportsNoCorrectionWhenTheRecordedIdWasUnusableAndSoWithheld(): void
    {
        $result = $this->source([$this->resolved(603)])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: 'tt0133093'),
        );

        self::assertNull($result->correctedTmdbId);
    }

    public function testASeasonCorrectionUsesTheShowId(): void
    {
        $result = $this->source([$this->resolved(1396)])->find(
            new PosterQuery('Breaking Bad - Season 2', PlexMediaType::Season, seasonNumber: 2, tmdbId: '999'),
        );

        self::assertSame('1396', $result->correctedTmdbId, 'match.tmdb_id on a season search is the show’s id');
    }

    #[DataProvider('unusableMatchedIds')]
    public function testIgnoresAMatchedIdThatIsNotUsable(mixed $matched): void
    {
        $response = $this->ok(
            [['url' => 'https://img/a.jpg']],
            ['query' => ['tmdb_id' => $matched], 'match' => ['tmdb_id' => $matched]],
        );

        $result = $this->source([$response])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: '111'),
        );

        self::assertNull($result->correctedTmdbId, 'a bad value here would be written to the item mapping');
    }

    public function testDoesNotReadTheEchoedQueryIdAtAll(): void
    {
        // A response that still echoes what was sent — the shape the contract
        // described and the endpoint does not emit. The answer must not change.
        $response = $this->ok(
            [['url' => 'https://img/a.jpg']],
            ['query' => ['tmdb_id' => 111], 'match' => ['tmdb_id' => 603]],
        );

        $result = $this->source([$response])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: '111'),
        );

        self::assertSame('603', $result->correctedTmdbId, 'detection holds whichever id the response echoes');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableMatchedIds(): array
    {
        return [
            'null' => [null],
            'zero' => [0],
            'negative' => [-1],
            'non-numeric string' => ['abc'],
            'float' => [12.5],
            'array' => [[603]],
        ];
    }

    public function testMissingMatchObjectIsNotACorrection(): void
    {
        $result = $this->source([$this->ok([['url' => 'https://img/a.jpg']])])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: '111'),
        );

        self::assertNull(
            $result->correctedTmdbId,
            'a service that has not deployed the parameter yet reports no match object',
        );
    }

    public function testAnIdentifiedWorkWithNoArtworkIsNoArtworkAndIsNotRetried(): void
    {
        $response = $this->resolved(603, []);

        $result = $this->source([$response])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: '603'),
        );

        self::assertSame(PosterSearchOutcome::NoArtwork, $result->outcome);
        self::assertCount(1, $this->sent, 'the id was valid; retrying by title would defeat it');
    }

    public function testAFailedSearchCarriesNoCorrection(): void
    {
        $result = $this->source([$this->error(404, 'no_match')])->find(
            new PosterQuery('The Matrix', PlexMediaType::Movie, tmdbId: '111'),
        );

        self::assertSame(PosterSearchOutcome::NoMatch, $result->outcome);
        self::assertNull($result->correctedTmdbId);
    }

    // ---- Outcome mapping ----

    public function testCandidatesPresentIsOk(): void
    {
        $result = $this->source([$this->ok([['url' => 'https://img/a.jpg']])])
            ->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::Ok, $result->outcome);
        self::assertTrue($result->outcome->hasCandidates());
    }

    public function testPartialCodeAtHttp200IsPartialNotFailure(): void
    {
        $response = $this->ok(
            [['url' => 'https://img/a.jpg']],
            ['code' => 'partial', 'providers' => ['tmdb' => 'ok', 'fanart.tv' => 'error']],
        );

        $result = $this->source([$response])->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::Partial, $result->outcome);
        self::assertCount(1, $result->candidates, 'a partial response still carries candidates');
    }

    public function testEmptyPosterListIsNoArtwork(): void
    {
        $result = $this->source([$this->ok([])])->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::NoArtwork, $result->outcome);
        self::assertSame([], $result->candidates);
    }

    public function testHttp404IsNoMatchNotNoArtwork(): void
    {
        $result = $this->source([$this->error(404, 'no_match')])
            ->find(new PosterQuery('Nonexistent Film', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::NoMatch, $result->outcome);
    }

    public function testHttp429IsRateLimited(): void
    {
        $result = $this->source([$this->error(429, 'rate_limited')])
            ->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::RateLimited, $result->outcome);
    }

    public function testHttp503IsUnavailable(): void
    {
        $result = $this->source([$this->error(503, 'upstream_unavailable')])
            ->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::Unavailable, $result->outcome);
    }

    /**
     * 401, 400 and 405 all mean Marquee sent something the endpoint rejected —
     * nothing the user can act on, so they read as "unavailable" while being
     * logged with their real status.
     */
    public function testRejectedRequestsAreUnavailable(): void
    {
        foreach ([[401, 'unauthorized'], [400, 'invalid_request'], [405, 'method_not_allowed']] as [$status, $code]) {
            $result = $this->source([$this->error($status, $code)])
                ->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

            self::assertSame(PosterSearchOutcome::Unavailable, $result->outcome, "HTTP $status");
        }
    }

    public function testTransportFailureIsUnavailable(): void
    {
        $error = new ConnectException('down', new Request('GET', '/marquee/api/v1/posters'));

        $result = $this->source([$error])->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::Unavailable, $result->outcome);
    }

    public function testUnparseableBodyIsUnavailable(): void
    {
        $result = $this->source([new Response(200, [], 'not json at all')])
            ->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertSame(PosterSearchOutcome::Unavailable, $result->outcome);
    }

    // ---- Candidate parsing ----

    public function testParsesEveryFieldWhenPresent(): void
    {
        $response = $this->ok([[
            'url' => 'https://img/a.jpg',
            'thumb' => 'https://img/a-thumb.jpg',
            'source' => 'tmdb',
            'width' => 2000,
            'height' => 3000,
            'language' => 'en',
            'score' => 8.2,
            'page' => 'https://example.test/works/1',
        ]]);

        $result = $this->source([$response])->find(new PosterQuery('The Matrix', PlexMediaType::Movie));
        $candidate = $result->candidates[0];

        self::assertSame('https://img/a.jpg', $candidate->url);
        self::assertSame('https://img/a-thumb.jpg', $candidate->thumb);
        self::assertSame('tmdb', $candidate->source);
        self::assertSame(2000, $candidate->width);
        self::assertSame(3000, $candidate->height);
        self::assertSame('en', $candidate->language);
        self::assertSame(8.2, $candidate->score);
        self::assertSame('https://example.test/works/1', $candidate->page);
        self::assertSame('https://img/a-thumb.jpg', $candidate->displayUrl());
    }

    /**
     * Absence is the common case, not an edge case: fanart.tv supplies no thumb,
     * width or height; TheTVDB no score; and TMDB itself omits score and
     * language on a large share of its own posters. Most candidates carry no
     * page either — it is sent only where a licence requires the link back.
     */
    public function testAbsentFieldsStayNullAndDisplayUrlFallsBackToUrl(): void
    {
        $result = $this->source([$this->ok([['url' => 'https://img/fanart.jpg', 'source' => 'fanart.tv']])])
            ->find(new PosterQuery('The Matrix', PlexMediaType::Movie));
        $candidate = $result->candidates[0];

        self::assertNull($candidate->thumb);
        self::assertNull($candidate->width);
        self::assertNull($candidate->height);
        self::assertNull($candidate->language);
        self::assertNull($candidate->score);
        self::assertNull($candidate->page);
        self::assertSame('https://img/fanart.jpg', $candidate->displayUrl());
    }

    /**
     * The real shape of a TVmaze season poster, taken from the live v2 endpoint:
     * a url, a thumb, a source and a page, and **nothing else**. No dimensions,
     * no language, no score.
     *
     * It is the thinnest candidate any service returns, so it is the one that
     * finds an assumption that a candidate carries more than a url. The page is
     * the season's own address, which is not the show's — the two differ, and a
     * single link cannot stand in for both.
     */
    public function testParsesATvmazeSeasonPosterWhichCarriesOnlyAUrlThumbAndPage(): void
    {
        $response = $this->ok([[
            'url' => 'https://static.tvmaze.com/uploads/images/original_untouched/405/1012792.jpg',
            'thumb' => 'https://static.tvmaze.com/uploads/images/medium_portrait/405/1012792.jpg',
            'source' => 'tvmaze',
            'page' => 'https://www.tvmaze.com/seasons/754/breaking-bad-season-2',
        ]]);

        $result = $this->source([$response])->find(
            new PosterQuery('Breaking Bad', PlexMediaType::Season, seasonNumber: 2),
        );
        $candidate = $result->candidates[0];

        self::assertSame('tvmaze', $candidate->source);
        self::assertSame('https://www.tvmaze.com/seasons/754/breaking-bad-season-2', $candidate->page);
        self::assertNull($candidate->width);
        self::assertNull($candidate->height);
        self::assertNull($candidate->language);
        self::assertNull($candidate->score);
    }

    /**
     * The marking is read as given, with no inspection of who supplied it. A
     * source slug this build has never heard of is still marked as requiring
     * attribution if the service says so.
     *
     * This is the parsing half of the guard; the payload half lives in the
     * controller test. Together they are what fails if the credit is ever
     * narrowed to a provider check — which would be untrue to the contract (the
     * service decides which of its providers are licensed this way) and would
     * need reopening for every provider added after this one.
     */
    public function testAttributionIsMarkedWhoeverSuppliedThePoster(): void
    {
        $response = $this->ok([[
            'url' => 'https://img/new.jpg',
            'source' => 'some-service-added-later',
            'page' => 'https://example.test/credit-me',
            'attribution_required' => true,
        ]]);

        $result = $this->source([$response])->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertTrue($result->candidates[0]->attributionRequired);
        self::assertSame('https://example.test/credit-me', $result->candidates[0]->page);
    }

    /**
     * A page is not a marking. Nearly every candidate carries an address now, and
     * treating that as the obligation would claim a licence condition over
     * artwork that carries none — as well as making the real obligation
     * indistinguishable from the rest.
     *
     * This is the assertion that fails if the two are ever collapsed back into
     * one field or one condition.
     */
    public function testCarryingAPageDoesNotMarkACandidateAsRequiringAttribution(): void
    {
        $response = $this->ok([[
            'url' => 'https://image.tmdb.org/t/p/original/x.jpg',
            'source' => 'tmdb',
            'page' => 'https://www.themoviedb.org/tv/1396',
        ]]);

        $result = $this->source([$response])->find(new PosterQuery('Breaking Bad', PlexMediaType::Show));

        self::assertSame('https://www.themoviedb.org/tv/1396', $result->candidates[0]->page);
        self::assertFalse($result->candidates[0]->attributionRequired);
    }

    /**
     * The flag is read on identity with boolean true, never on truthiness.
     *
     * The service sends it only when it is true and omits it otherwise, so any
     * other value is one this client did not expect — and the safe reading of an
     * unexpected value is "not marked", since the alternative is claiming a
     * licence condition nobody asserted. PHP makes the loose reading actively
     * dangerous here: the string "false" is truthy.
     *
     * @return list<array{mixed, bool}>
     */
    public static function attributionValues(): array
    {
        return [
            [true, true],
            // The wire form for "not marked" is absence, covered by the null case.
            [null, false],
            ['false', false],
            ['true', false],
            [1, false],
            [0, false],
            ['', false],
        ];
    }

    #[DataProvider('attributionValues')]
    public function testTheAttributionFlagIsReadStrictly(mixed $value, bool $expected): void
    {
        $poster = ['url' => 'https://img/a.jpg', 'source' => 'tvmaze'];
        if ($value !== null) {
            $poster['attribution_required'] = $value;
        }

        $result = $this->source([$this->ok([$poster])])->find(new PosterQuery('Breaking Bad', PlexMediaType::Show));

        self::assertSame($expected, $result->candidates[0]->attributionRequired);
    }

    /**
     * An obligation has to have somewhere to point. A marked candidate with no
     * address would render as a link to nothing, so the pairing is asserted here
     * rather than left to the template to cope with.
     *
     * Every marked candidate the live endpoint returns carries a page, so this
     * pins a property of the data at the boundary where it enters the system.
     */
    public function testAMarkedCandidateCarriesAPageToPointAt(): void
    {
        $response = $this->ok([[
            'url' => 'https://static.tvmaze.com/uploads/images/original_untouched/501/1253519.jpg',
            'source' => 'tvmaze',
            'page' => 'https://www.tvmaze.com/shows/169/breaking-bad',
            'attribution_required' => true,
        ]]);

        $result = $this->source([$response])->find(new PosterQuery('Breaking Bad', PlexMediaType::Show));

        foreach ($result->candidates as $candidate) {
            if ($candidate->attributionRequired) {
                self::assertNotNull(
                    $candidate->page,
                    'A candidate marked as requiring attribution must carry the address that credit links to.',
                );
            }
        }
    }

    /**
     * A television-only service answering a movie search reports `no_data`, and
     * the endpoint does **not** mark the response partial — verified against the
     * live v2 endpoint, where a movie comes back `success: true` with no `code`
     * at all and `tvmaze: no_data` in the providers map.
     *
     * That has to read to the user exactly as it did before the service existed:
     * an ordinary result, no warning line. It already does, because nothing here
     * inspects the providers map — it is logged, never branched on. This test
     * exists to keep it that way. **Do not make it pass by adding a case for
     * `no_data`**: that would put a provider name in the client for the first
     * time and make the next partial-coverage service a code change.
     */
    public function testAServiceReportingNoDataDoesNotMakeTheResultPartial(): void
    {
        $response = $this->ok(
            [['url' => 'https://img/a.jpg', 'source' => 'tmdb']],
            ['providers' => [
                'tmdb' => 'ok',
                'tvmaze' => 'no_data',
                'fanart.tv' => 'ok',
                'thetvdb' => 'ok',
            ]],
        );

        $result = $this->source([$response])->find(new PosterQuery('Inception', PlexMediaType::Movie, year: 2010));

        self::assertSame(PosterSearchOutcome::Ok, $result->outcome);
        self::assertNotSame(PosterSearchOutcome::Partial, $result->outcome);
        self::assertCount(1, $result->candidates);
    }

    public function testCandidatesWithoutAUrlAreSkipped(): void
    {
        $result = $this->source([$this->ok([
            ['url' => 'https://img/a.jpg'],
            ['thumb' => 'https://img/orphan-thumb.jpg'],
            ['url' => ''],
        ])])->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertCount(1, $result->candidates);
        self::assertSame('https://img/a.jpg', $result->candidates[0]->url);
    }

    public function testPreservesServerOrderAndKeepsDuplicateUrls(): void
    {
        $result = $this->source([$this->ok([
            ['url' => 'https://img/c.jpg'],
            ['url' => 'https://img/a.jpg'],
            ['url' => 'https://img/c.jpg'],
            ['url' => 'https://img/b.jpg'],
        ])])->find(new PosterQuery('The Matrix', PlexMediaType::Movie));

        self::assertSame(
            ['https://img/c.jpg', 'https://img/a.jpg', 'https://img/c.jpg', 'https://img/b.jpg'],
            array_map(static fn ($c): string => $c->url, $result->candidates),
            'server order is rendered verbatim and de-duplication is the server\'s job',
        );
    }
}
