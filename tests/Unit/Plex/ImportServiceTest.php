<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Config\PosterConfig;
use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Database\PlexLibraryRepository;
use App\Plex\Import\ImportService;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Poster\PosterLibrary;
use App\Poster\Search\PosterSearch;
use App\Poster\SortComparator;
use App\Poster\SortOrder;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;

final class ImportServiceTest extends TestCase
{
    use MakesImages;

    private string $dir;
    private PlexItemRepository $items;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function service(FakePlexClient $plex): ImportService
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $this->items = new PlexItemRepository($database);

        return new ImportService($plex, $storage, $this->items, new PlexLibraryRepository($database));
    }

    private function countFiles(string $sub): int
    {
        $dir = $this->dir . '/' . $sub;
        if (!is_dir($dir)) {
            return 0;
        }

        return count(array_filter(scandir($dir) ?: [], static fn (string $f): bool => is_file($dir . '/' . $f)));
    }

    public function testImportsMoviePosters(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $service = $this->service(new FakePlexClient([$library], ['1' => [$movie]]));

        $result = $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->imported());
        self::assertSame(1, $this->countFiles('movies'));
        self::assertNotNull($this->items->findByRatingKey('10'));
    }

    public function testExcludedLibraryImportsNothingEvenWhenItsSectionKeyIsSubmitted(): void
    {
        $kids = new PlexLibrary('3', 'Kids', 'movie');
        $cars = new PlexItem('30', PlexMediaType::Movie, 'Cars', 2006, '/t/30', 'Kids');
        $service = $this->service(new FakePlexClient([$kids], ['3' => [$cars]], excluded: ['Kids']));

        $result = $service->import(['3'], [PlexMediaType::Movie]);

        self::assertSame(0, $result->imported());
        self::assertSame(0, $this->countFiles('movies'));
        self::assertNull($this->items->findByRatingKey('30'));
    }

    public function testMixedSelectionImportsOnlyTheNonExcludedLibrary(): void
    {
        $movies = new PlexLibrary('1', 'Movies', 'movie');
        $kids = new PlexLibrary('3', 'Kids', 'movie');
        $service = $this->service(new FakePlexClient(
            [$movies, $kids],
            [
                '1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')],
                '3' => [new PlexItem('30', PlexMediaType::Movie, 'Cars', 2006, '/t/30', 'Kids')],
            ],
            excluded: ['Kids'],
        ));

        $result = $service->import(['1', '3'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->imported());
        self::assertNotNull($this->items->findByRatingKey('10'));
        self::assertNull($this->items->findByRatingKey('30'));
    }

    public function testImportPersistsPlexAddedAt(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies', addedAt: 1700000000);
        $service = $this->service(new FakePlexClient([$library], ['1' => [$movie]]));

        $service->import(['1'], [PlexMediaType::Movie]);

        $record = $this->items->findByRatingKey('10');
        self::assertNotNull($record);
        self::assertSame(1700000000, $record->addedAt);

        // The stored timestamp is exposed to the date-added sort, keyed by filename.
        $map = $this->items->addedAtForCategory('movies');
        self::assertSame(1700000000, $map[$record->filename] ?? null);
    }

    public function testImportWithoutAddedAtStoresZeroAndOmitsFromLookup(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('11', PlexMediaType::Movie, 'Dune', 2021, '/t/11', 'Movies');
        $service = $this->service(new FakePlexClient([$library], ['1' => [$movie]]));

        $service->import(['1'], [PlexMediaType::Movie]);

        $record = $this->items->findByRatingKey('11');
        self::assertNotNull($record);
        self::assertSame(0, $record->addedAt);
        // added_at = 0 means "unknown" and is excluded so the caller falls back to mtime.
        self::assertSame([], $this->items->addedAtForCategory('movies'));
    }

    public function testImportPersistsTheReleaseYear(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $service = $this->service(new FakePlexClient([$library], ['1' => [$movie]]));

        $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1972, $this->items->findByRatingKey('10')?->year);
    }

    /**
     * The season number reaches the stored record, Specials (0) included, so a
     * poster search never has to parse it back out of the display title.
     */
    public function testImportPersistsSeasonNumbersIncludingSpecials(): void
    {
        $library = new PlexLibrary('2', 'TV', 'show');
        $show = new PlexItem('20', PlexMediaType::Show, 'Severance', null, '/t/20', 'TV');
        $specials = new PlexItem('21', PlexMediaType::Season, 'Specials', null, '/t/21', 'TV', parentTitle: 'Severance', seasonNumber: 0);
        $seasonOne = new PlexItem('22', PlexMediaType::Season, 'Season 1', null, '/t/22', 'TV', parentTitle: 'Severance', seasonNumber: 1);

        $service = $this->service(new FakePlexClient(
            [$library],
            ['2' => [$show]],
            ['20' => [$specials, $seasonOne]],
        ));

        $service->import(['2'], [PlexMediaType::Season]);

        $stored = $this->items->findByRatingKey('21');
        self::assertNotNull($stored);
        self::assertNotNull($stored->seasonNumber, 'Specials must store 0, not null');
        self::assertSame(0, $stored->seasonNumber);
        self::assertSame(1, $this->items->findByRatingKey('22')?->seasonNumber);
    }

    public function testReimportOverwritesWithoutDuplicating(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $service = $this->service(new FakePlexClient([$library], ['1' => [$movie]]));

        $service->import(['1'], [PlexMediaType::Movie]);
        $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testFailedDownloadIsCountedNotFatal(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $ok = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $bad = new PlexItem('11', PlexMediaType::Movie, 'Dune', 2021, '/t/11', 'Movies');
        $service = $this->service(new FakePlexClient([$library], ['1' => [$ok, $bad]], failingKeys: ['11']));

        $result = $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->imported());
        self::assertSame(1, $result->failed());
        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testSkipsUnchangedPostersAndReimportsChanged(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');

        // First import: downloads and stores.
        $v1 = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/1', 'Movies');
        $plex1 = new FakePlexClient([$library], ['1' => [$v1]]);
        $first = (new ImportService($plex1, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $first->imported());
        self::assertSame(['10'], $plex1->downloads);

        // Second import, same thumb version: skipped, no download.
        $again = (new ImportService($plex1, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(0, $again->imported());
        self::assertSame(1, $again->skipped());
        self::assertSame(['10'], $plex1->downloads);

        // Thumb version changed in Plex: re-downloaded.
        $v2 = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/2', 'Movies');
        $plex2 = new FakePlexClient([$library], ['1' => [$v2]]);
        $changed = (new ImportService($plex2, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $changed->imported());
        self::assertSame(0, $changed->skipped());
        self::assertSame(['10'], $plex2->downloads);
    }

    /**
     * An install upgrading into the TMDB id has mappings written without one.
     * Its next import skips almost everything as unchanged, so the skip path
     * has to record the id or those rows would never gain one.
     */
    public function testSkippedItemGainsAMissingTmdbIdWithoutRedownloading(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $thumb = '/library/metadata/10/thumb/1';

        // Imported by a build that did not know about TMDB ids.
        $before = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, $thumb, 'Movies');
        $plexBefore = new FakePlexClient([$library], ['1' => [$before]]);
        (new ImportService($plexBefore, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);
        self::assertNull($items->findByRatingKey('10')?->tmdbId);

        // Same artwork, but Plex now reports an id.
        $after = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, $thumb, 'Movies', tmdbId: '1726');
        $plexAfter = new FakePlexClient([$library], ['1' => [$after]]);
        $result = (new ImportService($plexAfter, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        $backfilled = $items->findByRatingKey('10');
        self::assertNotNull($backfilled);
        self::assertSame('1726', $backfilled->tmdbId);
        // Still a skip: the poster was not re-downloaded.
        self::assertSame(1, $result->skipped());
        self::assertSame(0, $result->imported());
        self::assertSame([], $plexAfter->downloads);
        // The mapping is otherwise untouched.
        self::assertSame(1972, $backfilled->year);
        self::assertSame('Solaris', $backfilled->title);
    }

    public function testSkippedItemWithNoTmdbIdAvailableStaysNull(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/1', 'Movies');
        $plex = new FakePlexClient([$library], ['1' => [$movie]]);
        $service = new ImportService($plex, $storage, $items, $libraryRepo);

        $service->import(['1'], [PlexMediaType::Movie]);
        $result = $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->skipped());
        self::assertNull($items->findByRatingKey('10')?->tmdbId);
    }

    /**
     * A mapping caches what Plex said, and Plex changes its mind: correcting a
     * match rewrites an item's id under the same rating key. The skip path is
     * where a locked poster lands, so it is the only place that correction can
     * happen — and a stale id is what sends Find Posters to the wrong work.
     */
    public function testSkippedItemWithAChangedTmdbIdIsCorrected(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $thumb = '/library/metadata/10/thumb/1';
        $stored = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, $thumb, 'Movies', tmdbId: '1726');
        $plexStored = new FakePlexClient([$library], ['1' => [$stored]]);
        (new ImportService($plexStored, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        // Same artwork, but Plex now reports a different id.
        $changed = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, $thumb, 'Movies', tmdbId: '9999');
        $plexChanged = new FakePlexClient([$library], ['1' => [$changed]]);
        $result = (new ImportService($plexChanged, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->skipped());
        self::assertSame([], $plexChanged->downloads);
        self::assertSame('9999', $items->findByRatingKey('10')?->tmdbId);
    }

    /**
     * The other half of the rule: Plex reporting nothing is not news. A server
     * mid-refresh that stops reporting a guid must not erase what we know —
     * losing a fact is worse than holding a stale one, and the next import that
     * reports a value corrects it anyway.
     */
    public function testSkippedItemKeepsRecordedFactsPlexNoLongerReports(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $thumb = '/library/metadata/10/thumb/1';
        $stored = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, $thumb, 'Movies', tmdbId: '1726');
        $plexStored = new FakePlexClient([$library], ['1' => [$stored]]);
        (new ImportService($plexStored, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        $silent = new PlexItem('10', PlexMediaType::Movie, 'Solaris', null, $thumb, 'Movies', tmdbId: null);
        $plexSilent = new FakePlexClient([$library], ['1' => [$silent]]);
        $result = (new ImportService($plexSilent, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        $record = $items->findByRatingKey('10');
        self::assertNotNull($record);
        self::assertSame(1, $result->skipped());
        self::assertSame('1726', $record->tmdbId);
        self::assertSame(1972, $record->year);
    }

    /**
     * The guard that makes reconciliation affordable: an item whose facts all
     * still match writes nothing. A scheduled import over a populated library
     * is the common case and it must stay free.
     */
    public function testSkippedItemWithUnchangedFactsIsNotRewritten(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $thumb = '/library/metadata/10/thumb/1';
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, $thumb, 'Movies', tmdbId: '1726');
        $plex = new FakePlexClient([$library], ['1' => [$movie]]);
        (new ImportService($plex, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        $before = $items->findByRatingKey('10');
        self::assertNotNull($before);

        // Backdate the row to a timestamp no write could plausibly produce. A
        // second import that touches it would stamp the current time, so the
        // sentinel surviving is the observable proof that no row was written —
        // and it proves it without making the suite sleep.
        $items->upsert(new PlexItemRecord(
            ratingKey: $before->ratingKey,
            mediaType: $before->mediaType,
            category: $before->category,
            libraryTitle: $before->libraryTitle,
            title: $before->title,
            filename: $before->filename,
            updatedAt: 1,
            sectionKey: $before->sectionKey,
            thumb: $before->thumb,
            addedAt: $before->addedAt,
            year: $before->year,
            seasonNumber: $before->seasonNumber,
            tmdbId: $before->tmdbId,
        ));

        $result = (new ImportService($plex, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->skipped());
        self::assertSame(1, $items->findByRatingKey('10')?->updatedAt);
    }

    /**
     * Seasons imported before they carried their show's year are the reason the
     * skip path backfills at all: an established library skips them every time,
     * so without this they would keep a null year — and a null year is what lets
     * a stale season id be corrected to a same-titled show.
     */
    public function testSkippedItemGainsAMissingYearWithoutRedownloading(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'TV', 'show');
        $thumb = '/library/metadata/20/thumb/1';

        $show = new PlexItem('2', PlexMediaType::Show, 'The Office', 2005, '/t/2', 'TV', tmdbId: '2316');

        // Imported by a build whose seasons carried no year.
        $before = new PlexItem('20', PlexMediaType::Season, 'Season 1', null, $thumb, 'TV', parentTitle: 'The Office', seasonNumber: 1, tmdbId: '2316');
        $plexBefore = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$before]]);
        (new ImportService($plexBefore, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);
        self::assertNull($items->findByRatingKey('20')?->year);

        // Same artwork, but the season now carries its show's year.
        $after = new PlexItem('20', PlexMediaType::Season, 'Season 1', 2005, $thumb, 'TV', parentTitle: 'The Office', seasonNumber: 1, tmdbId: '2316');
        $plexAfter = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$after]]);
        $result = (new ImportService($plexAfter, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        $backfilled = $items->findByRatingKey('20');
        self::assertNotNull($backfilled);
        self::assertSame(2005, $backfilled->year);
        // Still a skip: the poster was not re-downloaded.
        self::assertSame(1, $result->skipped());
        self::assertSame(0, $result->imported());
        self::assertSame([], $plexAfter->downloads);
        // The mapping is otherwise untouched.
        self::assertSame('2316', $backfilled->tmdbId);
        self::assertSame(1, $backfilled->seasonNumber);
    }

    /**
     * A row predating both facts gains both, and pays for one write rather than
     * one per fact — the skip path exists to cost almost nothing.
     */
    public function testSkippedItemGainsBothAMissingYearAndIdentifier(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'TV', 'show');
        $thumb = '/library/metadata/20/thumb/1';

        $show = new PlexItem('2', PlexMediaType::Show, 'The Office', 2005, '/t/2', 'TV', tmdbId: '2316');

        $before = new PlexItem('20', PlexMediaType::Season, 'Season 1', null, $thumb, 'TV', parentTitle: 'The Office', seasonNumber: 1);
        $plexBefore = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$before]]);
        (new ImportService($plexBefore, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        $after = new PlexItem('20', PlexMediaType::Season, 'Season 1', 2005, $thumb, 'TV', parentTitle: 'The Office', seasonNumber: 1, tmdbId: '2316');
        $plexAfter = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$after]]);
        $result = (new ImportService($plexAfter, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        $backfilled = $items->findByRatingKey('20');
        self::assertNotNull($backfilled);
        self::assertSame(2005, $backfilled->year);
        self::assertSame('2316', $backfilled->tmdbId);
        self::assertSame(1, $result->skipped());
        self::assertSame([], $plexAfter->downloads);
    }

    /**
     * The year moves for the same reason the id does — a corrected match is a
     * different work — and for a movie the year is part of the filename, so the
     * poster follows it onto a new name.
     */
    public function testSkippedItemWithAChangedYearIsCorrected(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $thumb = '/library/metadata/10/thumb/1';
        $stored = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, $thumb, 'Movies', tmdbId: '1726');
        $plexStored = new FakePlexClient([$library], ['1' => [$stored]]);
        (new ImportService($plexStored, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        // Same artwork, but Plex now reports a different year.
        $changed = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 2002, $thumb, 'Movies', tmdbId: '1726');
        $plexChanged = new FakePlexClient([$library], ['1' => [$changed]]);
        $result = (new ImportService($plexChanged, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        $record = $items->findByRatingKey('10');
        self::assertNotNull($record);
        self::assertSame(1, $result->skipped());
        self::assertSame(2002, $record->year);
        self::assertSame('Solaris_2002_Movies.png', $record->filename);
        self::assertSame(1, $this->countFiles('movies'));
    }

    /**
     * The reported bug. A show matched as the wrong series, corrected in Plex
     * with "Fix Match", keeps its rating key — so the poster is overwritten in
     * place and its filename never moves. The caption follows the new title but
     * the gallery sorts by the filename and search matches it, which files the
     * poster under the old name and makes it unfindable by the new one.
     */
    public function testCorrectedMatchRenamesThePosterToTheNewTitle(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('2', 'TV Shows', 'show');

        $wrong = new PlexItem('100', PlexMediaType::Show, "Marvel's Agents of S.H.I.E.L.D.", 2013, '/t/100/v1', 'TV Shows', sectionKey: '2', tmdbId: '1403');
        $plexWrong = new FakePlexClient([$library], ['2' => [$wrong]]);
        (new ImportService($plexWrong, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);
        self::assertSame('Marvel_s_Agents_of_S.H.I.E.L.D._TV_Shows.png', $items->findByRatingKey('100')?->filename);

        // Fix Match: same rating key, different work, new artwork.
        $fixed = new PlexItem('100', PlexMediaType::Show, 'The Shield', 2002, '/t/100/v2', 'TV Shows', sectionKey: '2', tmdbId: '1826');
        $plexFixed = new FakePlexClient([$library], ['2' => [$fixed]]);
        (new ImportService($plexFixed, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        $record = $items->findByRatingKey('100');
        self::assertSame('The_Shield_TV_Shows.png', $record->filename);
        self::assertSame('The Shield', $record->title);
        self::assertSame('1826', $record->tmdbId);
        self::assertSame(2002, $record->year);
        // Renamed, not copied: the old name is gone and there is still one file.
        self::assertFalse($storage->exists(PosterCategory::TvShows, 'Marvel_s_Agents_of_S.H.I.E.L.D._TV_Shows.png'));
        self::assertSame(1, $this->countFiles('tv-shows'));
    }

    /**
     * The half of the bug that the download path cannot reach. A poster the user
     * customised and locked in Plex keeps its artwork across a corrected match,
     * so the import skips it — and the skip path was returning before it ever
     * compared a title.
     */
    public function testCorrectedMatchRenamesEvenWhenTheArtworkIsUnchanged(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('2', 'TV Shows', 'show');
        $locked = '/t/100/locked';

        $wrong = new PlexItem('100', PlexMediaType::Show, "Marvel's Agents of S.H.I.E.L.D.", 2013, $locked, 'TV Shows', sectionKey: '2', tmdbId: '1403');
        $plexWrong = new FakePlexClient([$library], ['2' => [$wrong]]);
        (new ImportService($plexWrong, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        $before = file_get_contents($this->dir . '/tv-shows/Marvel_s_Agents_of_S.H.I.E.L.D._TV_Shows.png');

        // Same locked artwork, corrected match.
        $fixed = new PlexItem('100', PlexMediaType::Show, 'The Shield', 2002, $locked, 'TV Shows', sectionKey: '2', tmdbId: '1826');
        $plexFixed = new FakePlexClient([$library], ['2' => [$fixed]]);
        $result = (new ImportService($plexFixed, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        $record = $items->findByRatingKey('100');
        self::assertNotNull($record);
        self::assertSame('The_Shield_TV_Shows.png', $record->filename);
        self::assertSame('The Shield', $record->title);
        self::assertSame('1826', $record->tmdbId);
        // Still a skip, and the image itself was never re-fetched or rewritten.
        self::assertSame(1, $result->skipped());
        self::assertSame(0, $result->imported());
        self::assertSame([], $plexFixed->downloads);
        self::assertSame($before, file_get_contents($this->dir . '/tv-shows/The_Shield_TV_Shows.png'));
    }

    /**
     * A failed download must not leave the poster renamed. The mapping is only
     * updated after the download succeeds, so renaming before it strands the
     * file under a name no mapping knows — the gallery then captions it from the
     * filename ("The Shield TV Shows"), Find Posters and Send to Plex report it
     * as unlinked, and no later import reconciles the two, because the mapping
     * still addresses a name that is gone.
     *
     * Plex makes this ordinary rather than exotic: right after a corrected match
     * it regenerates artwork, so the thumb path read from the library listing
     * can 404 by the time the poster is fetched.
     */
    public function testAFailedDownloadLeavesTheFileAndTheMappingAgreeing(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('2', 'TV Shows', 'show');

        $wrong = new PlexItem('100', PlexMediaType::Show, "Marvel's Agents of S.H.I.E.L.D.", 2013, '/t/100/v1', 'TV Shows', sectionKey: '2', tmdbId: '1403');
        $plexWrong = new FakePlexClient([$library], ['2' => [$wrong]]);
        (new ImportService($plexWrong, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        // Corrected match, new artwork — but fetching the poster fails.
        $fixed = new PlexItem('100', PlexMediaType::Show, 'The Shield', 2002, '/t/100/v2', 'TV Shows', sectionKey: '2', tmdbId: '1826');
        $plexFailing = new FakePlexClient([$library], ['2' => [$fixed]], failingKeys: ['100']);
        $result = (new ImportService($plexFailing, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        self::assertSame(1, $result->failed());

        // Whatever the import managed, the file on disk and the mapping must
        // still describe each other.
        $record = $items->findByRatingKey('100');
        self::assertNotNull($record);
        self::assertTrue(
            $storage->exists(PosterCategory::TvShows, $record->filename),
            'the mapping must address a file that exists',
        );
        self::assertSame(1, $this->countFiles('tv-shows'));

        // The identity came from the library listing, not from the artwork, so
        // it is corrected even though the poster could not be fetched.
        self::assertSame('The Shield', $record->title);
        self::assertSame(2002, $record->year);
        self::assertSame('1826', $record->tmdbId);
        self::assertSame('The_Shield_TV_Shows.png', $record->filename);
        // The recorded thumb is untouched, so the next import retries the fetch.
        self::assertSame('/t/100/v1', $record->thumb);

        // And a later import, once Plex is answering again, still heals it.
        $plexOk = new FakePlexClient([$library], ['2' => [$fixed]]);
        (new ImportService($plexOk, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        $healed = $items->findByRatingKey('100');
        self::assertNotNull($healed);
        self::assertSame('The_Shield_TV_Shows.png', $healed->filename);
        self::assertSame('The Shield', $healed->title);
        self::assertSame(1, $this->countFiles('tv-shows'));
    }

    /**
     * A rename obeys the same uniqueness rule a first import does: it may never
     * take a name that belongs to another item's poster.
     */
    public function testRenameDoesNotOverwriteAnUnrelatedPoster(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('2', 'TV Shows', 'show');

        $other = new PlexItem('200', PlexMediaType::Show, 'The Shield', 2002, '/t/200/v1', 'TV Shows', sectionKey: '2', tmdbId: '1826');
        $wrong = new PlexItem('100', PlexMediaType::Show, 'Wrong Match', 2013, '/t/100/v1', 'TV Shows', sectionKey: '2', tmdbId: '1403');
        $plexBefore = new FakePlexClient([$library], ['2' => [$other, $wrong]]);
        (new ImportService($plexBefore, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);
        self::assertSame('The_Shield_TV_Shows.png', $items->findByRatingKey('200')?->filename);

        // Item 100 is corrected to a title item 200 already occupies.
        $fixed = new PlexItem('100', PlexMediaType::Show, 'The Shield', 2002, '/t/100/v2', 'TV Shows', sectionKey: '2', tmdbId: '1826');
        $plexAfter = new FakePlexClient([$library], ['2' => [$other, $fixed]]);
        (new ImportService($plexAfter, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        $renamed = $items->findByRatingKey('100');
        $untouched = $items->findByRatingKey('200');
        self::assertNotNull($renamed);
        self::assertSame('The_Shield_TV_Shows-1.png', $renamed->filename);
        self::assertSame('The_Shield_TV_Shows.png', $untouched->filename);
        self::assertSame(2, $this->countFiles('tv-shows'));
    }

    /**
     * The symptom as the user met it, asserted where they met it. A corrected
     * match left the poster captioned with its new title but sorted and searched
     * under the old one, so it sat under the wrong letter and no query for its
     * real name found it — which reads, in a library of any size, as the show
     * having vanished. Note "shield" alone: search normalises S.H.I.E.L.D. to
     * "s h i e l d", so not even a one-word query reached it.
     */
    public function testACorrectedMatchIsSortedAndFoundUnderItsNewTitle(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $section = new PlexLibrary('2', 'TV Shows', 'show');

        $wrong = new PlexItem('100', PlexMediaType::Show, "Marvel's Agents of S.H.I.E.L.D.", 2013, '/t/100/v1', 'TV Shows', sectionKey: '2', tmdbId: '1403');
        $plexWrong = new FakePlexClient([$section], ['2' => [$wrong]]);
        (new ImportService($plexWrong, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        $fixed = new PlexItem('100', PlexMediaType::Show, 'The Shield', 2002, '/t/100/v2', 'TV Shows', sectionKey: '2', tmdbId: '1826');
        $plexFixed = new FakePlexClient([$section], ['2' => [$fixed]]);
        (new ImportService($plexFixed, $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);

        $config = new PosterConfig(24, 5_000_000, ['jpg', 'jpeg', 'png', 'webp'], true, SortOrder::Alphabetical);
        $comparator = new SortComparator($config);
        $gallery = new PosterLibrary($storage, new PosterSearch(), $config, $items, $comparator);

        $listed = $gallery->browse(PosterCategory::TvShows, null, 1)->items;
        self::assertCount(1, $listed);
        // Article-aware sort drops "The", so the poster files under S, not M.
        self::assertSame('shield tv shows', $listed[0]->sortKey(true));

        foreach (['The Shield', 'shield'] as $query) {
            self::assertCount(1, $gallery->browse(PosterCategory::TvShows, $query, 1)->items, sprintf('search for "%s"', $query));
        }
        self::assertCount(0, $gallery->browse(PosterCategory::TvShows, 'agents', 1)->items);
    }

    public function testForceReimportsUnchangedPosters(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/1', 'Movies');
        $plex = new FakePlexClient([$library], ['1' => [$movie]]);
        $service = new ImportService($plex, $storage, $items, $libraryRepo);

        $service->import(['1'], [PlexMediaType::Movie]);
        $forced = $service->import(['1'], [PlexMediaType::Movie], force: true);

        self::assertSame(1, $forced->imported());
        self::assertSame(0, $forced->skipped());
        self::assertSame(['10', '10'], $plex->downloads);
    }

    public function testReimportsWhenLocalFileIsMissing(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/library/metadata/10/thumb/1', 'Movies');
        $plex = new FakePlexClient([$library], ['1' => [$movie]]);
        $service = new ImportService($plex, $storage, $items, $libraryRepo);

        $service->import(['1'], [PlexMediaType::Movie]);
        array_map('unlink', glob($this->dir . '/movies/*') ?: []);

        // Same thumb, but the file is gone: it must be pulled again.
        $result = $service->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->imported());
        self::assertSame(0, $result->skipped());
        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testImportPersistsTmdbIdForMoviesShowsAndSeasons(): void
    {
        $movieLibrary = new PlexLibrary('1', 'Movies', 'movie');
        $showLibrary = new PlexLibrary('2', 'TV', 'show');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies', tmdbId: '1726');
        $show = new PlexItem('20', PlexMediaType::Show, 'Severance', null, '/t/20', 'TV', tmdbId: '95396');
        $season = new PlexItem(
            '200',
            PlexMediaType::Season,
            'Season 1',
            null,
            '/t/200',
            'TV',
            'Severance',
            seasonNumber: 1,
            tmdbId: '95396',
        );
        $service = $this->service(new FakePlexClient(
            [$movieLibrary, $showLibrary],
            ['1' => [$movie], '2' => [$show]],
            ['20' => [$season]],
        ));

        $service->import(['1', '2'], [PlexMediaType::Movie, PlexMediaType::Show, PlexMediaType::Season]);

        self::assertSame('1726', $this->items->findByRatingKey('10')?->tmdbId);
        self::assertSame('95396', $this->items->findByRatingKey('20')?->tmdbId);
        // A season stores its show's id paired with its own season number.
        $storedSeason = $this->items->findByRatingKey('200');
        self::assertSame('95396', $storedSeason?->tmdbId);
        self::assertSame(1, $storedSeason->seasonNumber);
    }

    /**
     * A server that reports no ids at all — an old Plex, or a library on a
     * legacy agent — imports exactly as before. Nothing fails.
     */
    public function testImportWithNoTmdbIdsReportsNoFailures(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $movie = new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies');
        $collection = new PlexItem('30', PlexMediaType::Collection, 'Christmas Movies', null, '/t/30', 'Movies');
        $service = $this->service(new FakePlexClient(
            [$library],
            ['1' => [$movie]],
            collectionsByKey: ['1' => [$collection]],
        ));

        $result = $service->import(['1'], [PlexMediaType::Movie, PlexMediaType::Collection]);

        self::assertSame(2, $result->imported());
        self::assertSame(0, $result->failed());
        self::assertNull($this->items->findByRatingKey('10')?->tmdbId);
        self::assertNull($this->items->findByRatingKey('30')?->tmdbId);
    }

    public function testOnlySelectedMediaTypesAreImported(): void
    {
        $library = new PlexLibrary('2', 'TV', 'show');
        $show = new PlexItem('20', PlexMediaType::Show, 'Severance', null, '/t/20', 'TV');
        $season = new PlexItem('200', PlexMediaType::Season, 'Season 1', null, '/t/200', 'TV', 'Severance');
        $service = $this->service(new FakePlexClient([$library], ['2' => [$show]], ['20' => [$season]]));

        $service->import(['2'], [PlexMediaType::Show]);

        self::assertSame(1, $this->countFiles('tv-shows'));
        self::assertSame(0, $this->countFiles('tv-seasons'));
    }
}
