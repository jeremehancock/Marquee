<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\PlexClient;
use App\Plex\PlexException;
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

        $app = $this->makeSignedInApp(
            [
                'POSTERS_DIR' => $this->postersDir,
                'DATA_DIR' => $this->dataDir,
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

    /**
     * A change writes the file first and pushes to Plex second, so a poster
     * whose Plex item is gone is stored locally and then rejected remotely. That
     * is not a failed change — the new image is on disk — and reporting it as one
     * left the gallery showing the old poster under a message about the new one
     * until the user reloaded by hand.
     *
     * The level is what the client reads to decide whether it has a card to
     * re-render, so the distinction is load-bearing, not cosmetic.
     */
    public function testChangeThatCannotReachPlexStillStoresThePoster(): void
    {
        $writer = new class () implements PlexPosterWriter {
            public function uploadPoster(string $ratingKey, string $imageBytes): void
            {
                throw PlexException::itemNotFound();
            }

            public function selectPoster(string $ratingKey, string $posterKey): void
            {
                throw PlexException::itemNotFound();
            }

            public function lockPoster(string $ratingKey): void
            {
            }

            public function removeOverlayLabel(string $sectionKey, int $plexType, string $ratingKey): void
            {
            }
        };
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], $this->pngBytes(2, 3)));

        $app = $this->makeSignedInApp(
            [
                'POSTERS_DIR' => $this->postersDir,
                'DATA_DIR' => $this->dataDir,
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
        // The whole point: the new image really is stored.
        self::assertSame($this->pngBytes(2, 3), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));

        // Reported as a warning, not an error — and the Plex reason survives,
        // because that is what tells the user this is an orphan rather than a
        // server they should go and fix.
        $body = (string) $this->get($app, '/library/movies')->getBody();
        self::assertStringContainsString('alert--warning', $body);
        self::assertStringContainsString('Poster updated, but it could not be sent to Plex.', $body);
        self::assertStringContainsString('the poster may be orphaned', $body);
        self::assertStringNotContainsString('alert--error', $body);
    }

    private function fakeSource(PosterSearchResult $result): FakePosterSource
    {
        return new FakePosterSource($result);
    }

    /**
     * @return array{sections: list<array{label: string, posters: list<array{url: string, thumb: string}>}>, error: string|null, partial: bool}
     */
    private function findPosters(FakePosterSource $source): array
    {
        $app = $this->makeSignedInApp(
            ['POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PosterSource::class => static fn (): PosterSource => $source],
        );

        $response = $this->get($app, '/library/movies/find-posters?filename=Solaris.jpg');
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);

        /** @var array{sections: list<array{label: string, posters: list<array{url: string, thumb: string}>}>, error: string|null, partial: bool} $payload */
        return $payload;
    }

    /**
     * Every candidate across every section, in the order the page will show
     * them.
     *
     * @param array{sections: list<array{label: string, posters: list<array{url: string, thumb: string}>}>, error: string|null, partial: bool} $payload
     *
     * @return list<array{url: string, thumb: string}>
     */
    private function allPosters(array $payload): array
    {
        $posters = [];
        foreach ($payload['sections'] as $section) {
            foreach ($section['posters'] as $poster) {
                $posters[] = $poster;
            }
        }

        return $posters;
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
            [
                'label' => 'TMDB',
                'posters' => [['url' => 'https://img/a.jpg', 'thumb' => 'https://img/a-t.jpg']],
            ],
            [
                'label' => 'fanart.tv',
                // No thumb from fanart.tv, so the grid image falls back to the full URL.
                'posters' => [['url' => 'https://img/b.jpg', 'thumb' => 'https://img/b.jpg']],
            ],
        ], $payload['sections']);
    }

    /**
     * The section order is what makes the tab's shape the same from one poster to
     * the next, so it is pinned against a source that returns them in another
     * order entirely.
     */
    public function testFindPostersSectionsAreOrderedTmdbThenTheTvdbThenFanart(): void
    {
        $source = $this->fakeSource(PosterSearchResult::found([
            new PosterCandidate('https://img/fanart.jpg', source: 'fanart.tv'),
            new PosterCandidate('https://img/tvdb.jpg', source: 'thetvdb'),
            new PosterCandidate('https://img/tmdb.jpg', source: 'tmdb'),
        ]));

        $payload = $this->findPosters($source);

        self::assertSame(
            ['TMDB', 'TVDB', 'fanart.tv'],
            array_column($payload['sections'], 'label'),
        );
    }

    /**
     * The label is resolved server-side so the page never has to know a provider
     * name — and the slug never reaches it.
     */
    public function testFindPostersDoesNotPublishTheSourceSlug(): void
    {
        $source = $this->fakeSource(PosterSearchResult::found([
            new PosterCandidate('https://img/a.jpg', source: 'tmdb'),
        ]));

        $payload = $this->findPosters($source);

        self::assertSame(['url' => 'https://img/a.jpg', 'thumb' => 'https://img/a.jpg'], $payload['sections'][0]['posters'][0]);
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
     *
     * The year is a parameter because it decides whether a correction is
     * well-founded: a search that sends one can separate works sharing a title,
     * and a search that does not cannot.
     */
    private function storeTmdbId(?string $tmdbId, ?int $year = 1972): void
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
            year: $year,
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

    /**
     * Replacing a stale id is safe only while the replacement is well-founded.
     * With no year the source's title fallback scores two same-titled works
     * identically and popularity picks between them, so the matched id is a
     * guess — and writing a guess is the one move that cannot be undone, since
     * a wrong-but-valid id resolves cleanly forever.
     */
    public function testCorrectionIsRefusedWhenTheSearchCouldNotDisambiguate(): void
    {
        $this->storeTmdbId('111', year: null);
        $this->findPosters($this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg')],
            correctedTmdbId: '603',
        )));

        $record = $this->storedRecord();
        self::assertSame('111', $record->tmdbId, 'the stale id is kept rather than replaced by a guess');
        self::assertSame(1000, $record->updatedAt, 'the row was not written to');
    }

    /**
     * The point of refusing: a stale id keeps failing to resolve, so the
     * mismatch is detected again and the item stays repairable. Writing the
     * guess is what would have made it permanent.
     */
    public function testARefusedCorrectionLeavesTheItemDetectable(): void
    {
        $this->storeTmdbId('111', year: null);
        $this->findPosters($this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg')],
            correctedTmdbId: '603',
        )));

        $next = $this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg')],
            correctedTmdbId: '603',
        ));
        $this->findPosters($next);

        self::assertInstanceOf(PosterQuery::class, $next->asked);
        self::assertSame('111', $next->asked->tmdbId, 'the same stale id goes out again, so the mismatch recurs');
    }

    /**
     * A refusal is as silent as a correction: it is a decision about a cached
     * fact, not something the user asked about or can act on.
     */
    public function testARefusedCorrectionDoesNotChangeWhatTheUserSees(): void
    {
        $this->storeTmdbId('111', year: null);
        $refused = $this->findPosters($this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg', source: 'tmdb')],
            correctedTmdbId: '603',
        )));

        $this->storeTmdbId('111', year: null);
        $plain = $this->findPosters($this->fakeSource(PosterSearchResult::found(
            [new PosterCandidate('https://img/a.jpg', source: 'tmdb')],
        )));

        self::assertSame($plain, $refused, 'a refusal is silent; nothing about it reaches the user');
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

    /**
     * A found-but-empty title reads as a complete sentence: the trailing "for it"
     * dangled off a subject the reader has to reconstruct.
     */
    public function testNoArtworkMessageIsSelfContained(): void
    {
        $payload = $this->findPosters(
            $this->fakeSource(PosterSearchResult::failed(PosterSearchOutcome::NoArtwork)),
        );

        self::assertSame('This title was found, but no posters are available.', $payload['error']);
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
        self::assertCount(1, $this->allPosters($payload), 'a partial result still has candidates to show');
    }

    public function testFailedSearchLeavesThePosterUnchanged(): void
    {
        $before = (string) file_get_contents($this->postersDir . '/movies/Solaris.jpg');

        foreach ([PosterSearchOutcome::NoMatch, PosterSearchOutcome::Unavailable, PosterSearchOutcome::RateLimited] as $outcome) {
            $payload = $this->findPosters($this->fakeSource(PosterSearchResult::failed($outcome)));

            self::assertSame([], $payload['sections']);
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

        $app = $this->makeSignedInApp(
            ['POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PosterSource::class => static fn (): PosterSource => $source],
        );

        $response = $this->get($app, '/library/movies/find-posters?filename=NotLinked.jpg');
        $payload = json_decode((string) $response->getBody(), true);

        self::assertIsArray($payload);
        self::assertSame([], $payload['sections']);
        self::assertSame('This poster is not linked to a Plex item.', $payload['error']);
        self::assertNull($source->asked, 'an unlinked poster has nothing to search for');
    }

    public function testSendToPlexPushesStoredPoster(): void
    {
        $writer = new FakePlexPosterWriter();

        $app = $this->makeSignedInApp(
            [
                'POSTERS_DIR' => $this->postersDir,
                'DATA_DIR' => $this->dataDir,
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
        $app = $this->makeSignedInApp(
            ['POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexClient::class => static fn (): PlexClient => new FakePlexClient()],
        );

        $response = $this->postForm($app, '/library/movies/fetch-from-plex', ['filename' => 'Solaris.jpg']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->pngBytes(2, 3), file_get_contents($this->postersDir . '/movies/Solaris.jpg'));
    }
}
