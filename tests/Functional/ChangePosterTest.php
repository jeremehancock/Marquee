<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\PlexClient;
use App\Plex\PlexMediaType;
use App\Plex\PlexPosterWriter;
use App\Poster\Source\PosterCandidate;
use App\Poster\Source\PosterQuery;
use App\Poster\Source\PosterSearchOutcome;
use App\Poster\Source\PosterSearchResult;
use App\Poster\Source\PosterSource;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\FakePlexPosterWriter;
use App\Tests\Support\FakePosterSource;
use App\Tests\Support\MakesImages;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

final class ChangePosterTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
        $this->dataDir = $this->makeTempDir();

        file_put_contents($this->postersDir . '/movies/Solaris.jpg', $this->pngBytes(5, 5));
        $repo = new PlexItemRepository(new Database($this->dataDir . '/marquee.sqlite'));
        $repo->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Solaris.jpg',
            time(),
            '1',
            year: 1972,
        ));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
        $this->removeDir($this->dataDir);
    }

    public function testChangeFromUrlReplacesAndPushes(): void
    {
        $writer = new FakePlexPosterWriter();
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], $this->pngBytes(2, 3)));

        $app = $this->makeApp(
            [
                'AUTH_BYPASS' => 'true',
                'POSTERS_DIR' => $this->postersDir,
                'DATA_DIR' => $this->dataDir,
                'PLEX_SERVER_URL' => 'http://plex:32400',
                'PLEX_TOKEN' => 'token',
            ],
            [
                ClientInterface::class => static fn (): ClientInterface => $http,
                PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer,
            ],
        );

        $response = $this->postForm($app, '/library/movies/change/url', [
            'filename' => 'Solaris.jpg',
            'url' => 'https://example.com/p.png',
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->pngBytes(2, 3), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));
        self::assertSame(['10'], $writer->uploaded);
    }

    private function fakeSource(PosterSearchResult $result): FakePosterSource
    {
        return new FakePosterSource($result);
    }

    /**
     * @return array{posters: list<array{url: string, thumb: string, source: string|null}>, error: string|null, partial: bool}
     */
    private function findPosters(FakePosterSource $source): array
    {
        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PosterSource::class => static fn (): PosterSource => $source],
        );

        $response = $this->get($app, '/library/movies/find-posters?filename=Solaris.jpg');
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);

        /** @var array{posters: list<array{url: string, thumb: string, source: string|null}>, error: string|null, partial: bool} $payload */
        return $payload;
    }

    public function testFindPostersReturnsCandidatesAsObjects(): void
    {
        $source = $this->fakeSource(PosterSearchResult::found([
            new PosterCandidate('https://img/a.jpg', thumb: 'https://img/a-t.jpg', source: 'tmdb'),
            new PosterCandidate('https://img/b.jpg', source: 'fanart.tv'),
        ]));

        $payload = $this->findPosters($source);

        self::assertNull($payload['error']);
        self::assertFalse($payload['partial']);
        self::assertSame([
            ['url' => 'https://img/a.jpg', 'thumb' => 'https://img/a-t.jpg', 'source' => 'tmdb'],
            // No thumb from fanart.tv, so the grid image falls back to the full URL.
            ['url' => 'https://img/b.jpg', 'thumb' => 'https://img/b.jpg', 'source' => 'fanart.tv'],
        ], $payload['posters']);
    }

    public function testFindPostersPassesTheStoredYearAndType(): void
    {
        $source = $this->fakeSource(PosterSearchResult::found([new PosterCandidate('https://img/a.jpg')]));
        $this->findPosters($source);

        self::assertInstanceOf(PosterQuery::class, $source->asked);
        self::assertSame('Solaris', $source->asked->title);
        self::assertSame(PlexMediaType::Movie, $source->asked->mediaType);
        self::assertSame(1972, $source->asked->year);
        self::assertNull($source->asked->seasonNumber);
    }

    private function repository(): PlexItemRepository
    {
        return new PlexItemRepository(new Database($this->dataDir . '/marquee.sqlite'));
    }

    /**
     * Re-record the Solaris mapping with a given TMDB id and a fixed, old
     * `updatedAt`, so a later write to the row is visible as a change to it.
     */
    private function storeTmdbId(?string $tmdbId): void
    {
        $this->repository()->upsert(new PlexItemRecord(
            '10',
            'movie',
            'movies',
            'Movies',
            'Solaris',
            'Solaris.jpg',
            1000,
            '1',
            year: 1972,
            tmdbId: $tmdbId,
        ));
    }

    private function storedRecord(): PlexItemRecord
    {
        $record = $this->repository()->findByFilename('movies', 'Solaris.jpg');
        self::assertInstanceOf(PlexItemRecord::class, $record);

        return $record;
    }

    public function testFindPostersSendsTheRecordedTmdbId(): void
    {
        $this->storeTmdbId('603');
        $source = $this->fakeSource(PosterSearchResult::found([new PosterCandidate('https://img/a.jpg')]));
        $this->findPosters($source);

        self::assertInstanceOf(PosterQuery::class, $source->asked);
        self::assertSame('603', $source->asked->tmdbId);
    }

    public function testFindPostersSendsNoIdWhenNoneIsRecorded(): void
    {
        $source = $this->fakeSource(PosterSearchResult::found([new PosterCandidate('https://img/a.jpg')]));
        $this->findPosters($source);

        self::assertInstanceOf(PosterQuery::class, $source->asked);
        self::assertNull($source->asked->tmdbId);
    }

    public function testStaleTmdbIdIsReplacedByTheMatchedOne(): void
    {
        $this->storeTmdbId('111');
        $source = $this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg')],
            correctedTmdbId: '603',
        ));

        $this->findPosters($source);

        $record = $this->storedRecord();
        self::assertSame('603', $record->tmdbId);
        self::assertGreaterThan(1000, $record->updatedAt);
        self::assertSame('Solaris', $record->title, 'only the id changes');
        self::assertSame(1972, $record->year);
    }

    public function testCorrectedItemSendsTheCorrectedIdOnTheNextSearch(): void
    {
        $this->storeTmdbId('111');
        $this->findPosters($this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg')],
            correctedTmdbId: '603',
        )));

        $next = $this->fakeSource(PosterSearchResult::found([new PosterCandidate('https://img/a.jpg')]));
        $this->findPosters($next);

        self::assertInstanceOf(PosterQuery::class, $next->asked);
        self::assertSame('603', $next->asked->tmdbId);
    }

    public function testNoCorrectionLeavesTheRecordUntouched(): void
    {
        $this->storeTmdbId('603');
        $this->findPosters($this->fakeSource(
            PosterSearchResult::found([new PosterCandidate('https://img/a.jpg')]),
        ));

        $record = $this->storedRecord();
        self::assertSame('603', $record->tmdbId);
        self::assertSame(1000, $record->updatedAt, 'the row was not written to');
    }

    public function testAMissingIdIsNeverFilledInFromASearch(): void
    {
        $this->storeTmdbId(null);
        $this->findPosters($this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg')],
            correctedTmdbId: '603',
        )));

        $record = $this->storedRecord();
        self::assertNull(
            $record->tmdbId,
            'an item with no id keeps searching by title, which re-resolves every time',
        );
        self::assertSame(1000, $record->updatedAt);
    }

    public function testCorrectionDoesNotChangeWhatTheUserSees(): void
    {
        $this->storeTmdbId('111');
        $corrected = $this->findPosters($this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg', source: 'tmdb')],
            correctedTmdbId: '603',
        )));

        $this->storeTmdbId('603');
        $plain = $this->findPosters($this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg', source: 'tmdb')],
        )));

        self::assertSame($plain, $corrected, 'a correction is silent; it is a repair, not a message');
    }

    /**
     * Each outcome has to say something different: the old contract collapsed
     * every one of these into "No posters found for this title."
     */
    public function testEachOutcomeProducesItsOwnMessage(): void
    {
        $messages = [];
        foreach (PosterSearchOutcome::cases() as $outcome) {
            $result = $outcome->hasCandidates()
                ? new PosterSearchResult($outcome, [new PosterCandidate('https://img/a.jpg')])
                : PosterSearchResult::failed($outcome);

            $payload = $this->findPosters($this->fakeSource($result));

            if ($outcome === PosterSearchOutcome::Ok) {
                self::assertNull($payload['error'], 'a clean success says nothing');

                continue;
            }

            self::assertIsString($payload['error'], $outcome->name . ' needs a message');
            $messages[$outcome->name] = $payload['error'];
        }

        self::assertCount(
            count($messages),
            array_unique(array_values($messages)),
            'every outcome must be distinguishable to the user',
        );
    }

    public function testPartialShowsCandidatesAlongsideItsWarning(): void
    {
        $result = new PosterSearchResult(
            PosterSearchOutcome::Partial,
            [new PosterCandidate('https://img/a.jpg')],
        );

        $payload = $this->findPosters($this->fakeSource($result));

        self::assertTrue($payload['partial']);
        self::assertIsString($payload['error']);
        self::assertCount(1, $payload['posters'], 'a partial result still has candidates to show');
    }

    public function testFailedSearchLeavesThePosterUnchanged(): void
    {
        $before = (string) file_get_contents($this->postersDir . '/movies/Solaris.jpg');

        foreach ([PosterSearchOutcome::NoMatch, PosterSearchOutcome::Unavailable, PosterSearchOutcome::RateLimited] as $outcome) {
            $payload = $this->findPosters($this->fakeSource(PosterSearchResult::failed($outcome)));

            self::assertSame([], $payload['posters']);
            self::assertSame(
                $before,
                (string) file_get_contents($this->postersDir . '/movies/Solaris.jpg'),
                $outcome->name . ' must not touch the stored poster',
            );
        }
    }

    public function testUnlinkedPosterIsReportedWithoutSearching(): void
    {
        $source = $this->fakeSource(PosterSearchResult::found([new PosterCandidate('https://img/a.jpg')]));

        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PosterSource::class => static fn (): PosterSource => $source],
        );

        $response = $this->get($app, '/library/movies/find-posters?filename=NotLinked.jpg');
        $payload = json_decode((string) $response->getBody(), true);

        self::assertIsArray($payload);
        self::assertSame([], $payload['posters']);
        self::assertSame('This poster is not linked to a Plex item.', $payload['error']);
        self::assertNull($source->asked, 'an unlinked poster has nothing to search for');
    }

    public function testSendToPlexPushesStoredPoster(): void
    {
        $writer = new FakePlexPosterWriter();

        $app = $this->makeApp(
            [
                'AUTH_BYPASS' => 'true',
                'POSTERS_DIR' => $this->postersDir,
                'DATA_DIR' => $this->dataDir,
                'PLEX_SERVER_URL' => 'http://plex:32400',
                'PLEX_TOKEN' => 'token',
            ],
            [PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer],
        );

        $response = $this->postForm($app, '/library/movies/send-to-plex', ['filename' => 'Solaris.jpg']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['10'], $writer->uploaded);
        self::assertSame(['10'], $writer->locked);
    }

    public function testFetchFromPlexReplacesLocal(): void
    {
        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $response = $this->postForm($app, '/library/movies/fetch-from-plex', ['filename' => 'Solaris.jpg']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->pngBytes(2, 3), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));
    }
}
