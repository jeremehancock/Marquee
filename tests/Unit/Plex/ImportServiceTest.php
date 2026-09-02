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
use App\Tests\Support\FailingCollectionWalkClient;
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

    /**
     * A season records the title of the show it belongs to, as a fact of its own.
     * The display title it is stored under joins the two ("The Office - Season 1")
     * and that join cannot be undone by inspecting the result — a show whose own
     * name contains " - " and a season whose name does are both real — so the
     * parent is recorded rather than parsed back out.
     */
    public function testImportPersistsASeasonsShowTitle(): void
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

        $specialsRow = $this->items->findByRatingKey('21');
        $seasonOneRow = $this->items->findByRatingKey('22');
        self::assertNotNull($specialsRow);
        self::assertNotNull($seasonOneRow);

        self::assertSame('Severance', $specialsRow->parentTitle);
        self::assertSame('Severance', $seasonOneRow->parentTitle);
        // The display title still joins the two; the parent is recorded beside it,
        // not instead of it.
        self::assertSame('Severance - Season 1', $seasonOneRow->title);
    }

    /**
     * Only a season has a parent to name. A movie, show or collection records the
     * empty string, and that is a settled state rather than missing information.
     */
    public function testAnItemThatIsNotASeasonRecordsNoShowTitle(): void
    {
        $library = new PlexLibrary('2', 'TV', 'show');
        $show = new PlexItem('20', PlexMediaType::Show, 'Severance', 2022, '/t/20', 'TV');

        $service = $this->service(new FakePlexClient([$library], ['2' => [$show]], []));
        $service->import(['2'], [PlexMediaType::Show]);

        self::assertSame('', $this->items->findByRatingKey('20')?->parentTitle);
    }

    /**
     * The reason the skip path reconciles at all, for this fact as for the others:
     * an established library skips every season on every import, so a mapping
     * written before show titles were recorded would never gain one. It backfills
     * without re-downloading a poster and without the user forcing anything.
     */
    public function testSkippedSeasonGainsAMissingShowTitleWithoutRedownloading(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'TV', 'show');
        $thumb = '/library/metadata/20/thumb/1';

        $show = new PlexItem('2', PlexMediaType::Show, 'The Office', 2005, '/t/2', 'TV');
        $season = new PlexItem('20', PlexMediaType::Season, 'Season 1', 2005, $thumb, 'TV', parentTitle: 'The Office', seasonNumber: 1);

        $plex = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$season]]);
        (new ImportService($plex, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        // Rewind the row to the shape a build without the column left behind,
        // keeping everything else exactly as imported.
        $stored = $items->findByRatingKey('20');
        self::assertNotNull($stored);
        $items->upsert(new PlexItemRecord(
            ratingKey: $stored->ratingKey,
            mediaType: $stored->mediaType,
            category: $stored->category,
            libraryTitle: $stored->libraryTitle,
            title: $stored->title,
            filename: $stored->filename,
            updatedAt: $stored->updatedAt,
            sectionKey: $stored->sectionKey,
            thumb: $stored->thumb,
            addedAt: $stored->addedAt,
            year: $stored->year,
            seasonNumber: $stored->seasonNumber,
            tmdbId: $stored->tmdbId,
            parentTitle: '',
            setKeys: [],
        ));
        $rewound = $items->findByRatingKey('20');
        self::assertNotNull($rewound);
        self::assertSame('', $rewound->parentTitle);

        $plexAgain = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$season]]);
        $result = (new ImportService($plexAgain, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        $backfilled = $items->findByRatingKey('20');
        self::assertNotNull($backfilled);
        self::assertSame('The Office', $backfilled->parentTitle);
        // Still a skip: the poster was not fetched again.
        self::assertSame(1, $result->skipped());
        self::assertSame(0, $result->imported());
        self::assertSame([], $plexAgain->downloads);
        // Nothing else moved.
        self::assertSame('The Office - Season 1', $backfilled->title);
        self::assertSame(1, $backfilled->seasonNumber);
    }

    /**
     * And once backfilled it costs nothing. A season whose recorded show title
     * already matches writes no row, so a scheduled import over a settled library
     * is no more expensive than it was before the column existed.
     */
    public function testASeasonWhoseShowTitleAlreadyMatchesIsNotRewritten(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'TV', 'show');
        $thumb = '/library/metadata/20/thumb/1';

        $show = new PlexItem('2', PlexMediaType::Show, 'The Office', 2005, '/t/2', 'TV');
        $season = new PlexItem('20', PlexMediaType::Season, 'Season 1', 2005, $thumb, 'TV', parentTitle: 'The Office', seasonNumber: 1);

        $plex = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$season]]);
        (new ImportService($plex, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        // A sentinel timestamp no write would leave standing: any upsert stamps
        // the row with time(). Its survival is the observable proof of no write.
        $stored = $items->findByRatingKey('20');
        self::assertNotNull($stored);
        $items->upsert(new PlexItemRecord(
            ratingKey: $stored->ratingKey,
            mediaType: $stored->mediaType,
            category: $stored->category,
            libraryTitle: $stored->libraryTitle,
            title: $stored->title,
            filename: $stored->filename,
            updatedAt: 1,
            sectionKey: $stored->sectionKey,
            thumb: $stored->thumb,
            addedAt: $stored->addedAt,
            year: $stored->year,
            seasonNumber: $stored->seasonNumber,
            tmdbId: $stored->tmdbId,
            parentTitle: $stored->parentTitle,
            setKeys: $stored->setKeys,
        ));

        $plexAgain = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$season]]);
        $result = (new ImportService($plexAgain, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        self::assertSame(1, $result->skipped());
        self::assertSame(1, $items->findByRatingKey('20')?->updatedAt);
    }

    /**
     * A show and a collection are their own set, and a season takes its show's.
     * That shared key is what lets any member open the whole set.
     */
    public function testShowsAndSeasonsShareOneSet(): void
    {
        $library = new PlexLibrary('2', 'TV', 'show');
        $show = new PlexItem('20', PlexMediaType::Show, 'Severance', null, '/t/20', 'TV');
        $seasonOne = new PlexItem('22', PlexMediaType::Season, 'Season 1', null, '/t/22', 'TV', parentTitle: 'Severance', seasonNumber: 1);
        $seasonTwo = new PlexItem('23', PlexMediaType::Season, 'Season 2', null, '/t/23', 'TV', parentTitle: 'Severance', seasonNumber: 2);

        $service = $this->service(new FakePlexClient(
            [$library],
            ['2' => [$show]],
            ['20' => [$seasonOne, $seasonTwo]],
        ));

        $service->import(['2'], [PlexMediaType::Show, PlexMediaType::Season]);

        self::assertSame(['20'], $this->items->findByRatingKey('20')?->setKeys);
        self::assertSame(['20'], $this->items->findByRatingKey('22')?->setKeys);
        self::assertSame(['20'], $this->items->findByRatingKey('23')?->setKeys);
    }

    /**
     * The case a title search could never reach: films whose names have nothing
     * in common, held together only by the collection they are in.
     */
    public function testMoviesRecordTheCollectionTheyBelongTo(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $ironMan = new PlexItem('10', PlexMediaType::Movie, 'Iron Man', 2008, '/t/10', 'Movies');
        $thor = new PlexItem('11', PlexMediaType::Movie, 'Thor', 2011, '/t/11', 'Movies');
        $solaris = new PlexItem('12', PlexMediaType::Movie, 'Solaris', 1972, '/t/12', 'Movies');
        $mcu = new PlexItem('90', PlexMediaType::Collection, 'Marvel Cinematic Universe', null, '/t/90', 'Movies');

        $plex = new FakePlexClient(
            [$library],
            ['1' => [$ironMan, $thor, $solaris]],
            [],
            ['1' => [$mcu]],
            membersByCollection: ['90' => [$ironMan, $thor]],
        );

        $this->service($plex)->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(['90'], $this->items->findByRatingKey('10')?->setKeys);
        self::assertSame(['90'], $this->items->findByRatingKey('11')?->setKeys);
        // In no collection, so no set — the ordinary case, not an error.
        self::assertSame([], $this->items->findByRatingKey('12')?->setKeys);
    }

    /**
     * Membership is a fact about the movie's row, not about the collection's
     * poster, so it is recorded whether or not collection posters were asked for.
     * A user who never imports collection artwork still expects a film to open
     * alongside the rest of its collection.
     */
    public function testMembershipIsRecordedWhenCollectionPostersWereNotRequested(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $ironMan = new PlexItem('10', PlexMediaType::Movie, 'Iron Man', 2008, '/t/10', 'Movies');
        $mcu = new PlexItem('90', PlexMediaType::Collection, 'Marvel Cinematic Universe', null, '/t/90', 'Movies');

        $plex = new FakePlexClient(
            [$library],
            ['1' => [$ironMan]],
            [],
            ['1' => [$mcu]],
            membersByCollection: ['90' => [$ironMan]],
        );

        $this->service($plex)->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(['90'], $this->items->findByRatingKey('10')?->setKeys);
        // The collection's own poster was not among the requested types.
        self::assertNull($this->items->findByRatingKey('90'));
    }

    /**
     * The walk is bounded by how many collections a library has, so a library
     * with none costs nothing extra.
     */
    public function testALibraryWithNoCollectionsIsNotWalked(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $solaris = new PlexItem('12', PlexMediaType::Movie, 'Solaris', 1972, '/t/12', 'Movies');

        $plex = new FakePlexClient([$library], ['1' => [$solaris]]);
        $this->service($plex)->import(['1'], [PlexMediaType::Movie]);

        self::assertSame([], $plex->collectionWalks);
    }

    /**
     * Membership is an enrichment. A collection Plex cannot list costs that
     * collection's films their set until the next import; it does not fail the
     * import or affect any poster.
     */
    public function testACollectionThatCannotBeListedDoesNotFailTheImport(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $ironMan = new PlexItem('10', PlexMediaType::Movie, 'Iron Man', 2008, '/t/10', 'Movies');
        $mcu = new PlexItem('90', PlexMediaType::Collection, 'Marvel Cinematic Universe', null, '/t/90', 'Movies');

        // No members registered for '90', and the client is told the walk fails.
        $plex = new FailingCollectionWalkClient(
            [$library],
            ['1' => [$ironMan]],
            [],
            ['1' => [$mcu]],
        );

        $result = $this->service($plex)->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(1, $result->imported());
        self::assertSame(0, $result->failed());
        self::assertSame([], $this->items->findByRatingKey('10')?->setKeys);
    }

    /**
     * The backfill the established library depends on: every poster is skipped,
     * nothing is downloaded, and the sets still land.
     */
    public function testSkippedItemsStillGainTheirSets(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);
        $library = new PlexLibrary('1', 'TV', 'show');
        $thumb = '/library/metadata/20/thumb/1';

        $show = new PlexItem('2', PlexMediaType::Show, 'Severance', 2022, '/t/2', 'TV');
        $season = new PlexItem('20', PlexMediaType::Season, 'Season 1', 2022, $thumb, 'TV', parentTitle: 'Severance', seasonNumber: 1);

        $plex = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$season]]);
        (new ImportService($plex, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        // Rewind to the shape a build without the column left behind.
        $stored = $items->findByRatingKey('20');
        self::assertNotNull($stored);
        $items->upsert(new PlexItemRecord(
            ratingKey: $stored->ratingKey,
            mediaType: $stored->mediaType,
            category: $stored->category,
            libraryTitle: $stored->libraryTitle,
            title: $stored->title,
            filename: $stored->filename,
            updatedAt: $stored->updatedAt,
            sectionKey: $stored->sectionKey,
            thumb: $stored->thumb,
            addedAt: $stored->addedAt,
            year: $stored->year,
            seasonNumber: $stored->seasonNumber,
            tmdbId: $stored->tmdbId,
            parentTitle: $stored->parentTitle,
            setKeys: [],
        ));

        $plexAgain = new FakePlexClient([$library], ['1' => [$show]], ['2' => [$season]]);
        $result = (new ImportService($plexAgain, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Season]);

        self::assertSame(['2'], $items->findByRatingKey('20')?->setKeys);
        self::assertSame(1, $result->skipped());
        self::assertSame(0, $result->imported());
        self::assertSame([], $plexAgain->downloads);
    }

    /**
     * A movies-only import must still leave the collection's own poster inside
     * the set its films point at. Without this the set is right except for the
     * poster that names it, which is the one a user is most likely to open it
     * from.
     */
    public function testAMoviesOnlyImportStillSetsTheCollectionsOwnPoster(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);

        $library = new PlexLibrary('1', 'Movies', 'movie');
        $ironMan = new PlexItem('10', PlexMediaType::Movie, 'Iron Man', 2008, '/t/10', 'Movies');
        $mcu = new PlexItem('90', PlexMediaType::Collection, 'Marvel Cinematic Universe', null, '/t/90', 'Movies');

        $client = fn (): FakePlexClient => new FakePlexClient(
            [$library],
            ['1' => [$ironMan]],
            [],
            ['1' => [$mcu]],
            membersByCollection: ['90' => [$ironMan]],
        );

        // The collection's poster exists from an earlier import that predates sets.
        (new ImportService($client(), $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Collection]);
        $stored = $items->findByRatingKey('90');
        self::assertNotNull($stored);
        $items->upsert(new PlexItemRecord(
            ratingKey: '90',
            mediaType: $stored->mediaType,
            category: $stored->category,
            libraryTitle: $stored->libraryTitle,
            title: $stored->title,
            filename: $stored->filename,
            updatedAt: 1,
            thumb: $stored->thumb,
            setKeys: [],
        ));

        // Now a movies-only import, which never reaches the collection branch.
        (new ImportService($client(), $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(['90'], $items->findByRatingKey('10')?->setKeys, 'The film records its collection.');
        self::assertSame(['90'], $items->findByRatingKey('90')?->setKeys, 'So does the collection itself.');
    }

    /**
     * The same for a seasons-only import and the show's own poster.
     */
    public function testASeasonsOnlyImportStillSetsTheShowsOwnPoster(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);

        $library = new PlexLibrary('2', 'TV', 'show');
        $show = new PlexItem('20', PlexMediaType::Show, 'Severance', null, '/t/20', 'TV');
        $season = new PlexItem('22', PlexMediaType::Season, 'Season 1', null, '/t/22', 'TV', parentTitle: 'Severance', seasonNumber: 1);

        $client = fn (): FakePlexClient => new FakePlexClient([$library], ['2' => [$show]], ['20' => [$season]]);

        (new ImportService($client(), $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Show]);
        $stored = $items->findByRatingKey('20');
        self::assertNotNull($stored);
        $items->upsert(new PlexItemRecord(
            ratingKey: '20',
            mediaType: $stored->mediaType,
            category: $stored->category,
            libraryTitle: $stored->libraryTitle,
            title: $stored->title,
            filename: $stored->filename,
            updatedAt: 1,
            thumb: $stored->thumb,
            setKeys: [],
        ));

        (new ImportService($client(), $storage, $items, $libraryRepo))->import(['2'], [PlexMediaType::Season]);

        self::assertSame(['20'], $items->findByRatingKey('22')?->setKeys, 'The season records its show.');
        self::assertSame(['20'], $items->findByRatingKey('20')?->setKeys, 'So does the show itself.');
    }

    /**
     * It fills a blank and never overwrites: the import path owns changing a
     * recorded set, and this must not race it.
     */
    public function testFillingASetNeverOverwritesOne(): void
    {
        $items = new PlexItemRepository(new Database(':memory:'));
        $items->upsert(new PlexItemRecord(
            ratingKey: '90',
            mediaType: 'collection',
            category: 'collections',
            libraryTitle: 'Movies',
            title: 'Marvel Cinematic Universe',
            filename: 'MCU.png',
            updatedAt: 1,
            setKeys: ['already-set'],
        ));

        $items->fillMissingSetKey('90', '90');

        $after = $items->findByRatingKey('90');
        self::assertNotNull($after);
        self::assertSame(['already-set'], $after->setKeys);
        self::assertSame(1, $after->updatedAt, 'No write, so no new timestamp.');
    }

    /**
     * A film can be in more than one collection — "Godzilla vs. Kong" is in both
     * King Kong and MonsterVerse — and it belongs to both.
     *
     * Recording one meant the collection read first took the film, and every
     * other collection sharing it was left holding nothing but its own poster.
     */
    public function testAFilmInTwoCollectionsBelongsToBoth(): void
    {
        $library = new PlexLibrary('1', 'Movies', 'movie');
        $godzilla = new PlexItem('10', PlexMediaType::Movie, 'Godzilla vs. Kong', 2021, '/t/10', 'Movies');
        $skull = new PlexItem('11', PlexMediaType::Movie, 'Kong: Skull Island', 2017, '/t/11', 'Movies');
        $kong = new PlexItem('15512', PlexMediaType::Collection, 'King Kong', null, '/t/15512', 'Movies');
        $verse = new PlexItem('15553', PlexMediaType::Collection, 'MonsterVerse', null, '/t/15553', 'Movies');

        $plex = new FakePlexClient(
            [$library],
            ['1' => [$godzilla, $skull]],
            [],
            ['1' => [$kong, $verse]],
            membersByCollection: [
                '15512' => [$godzilla],
                '15553' => [$godzilla, $skull],
            ],
        );

        $this->service($plex)->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(['15512', '15553'], $this->items->findByRatingKey('10')?->setKeys);
        self::assertSame(['15553'], $this->items->findByRatingKey('11')?->setKeys);
    }

    /**
     * A collection is a relationship a user removes on purpose, and Plex says so
     * by omission rather than by reporting anything. Holding the old membership
     * kept showing a film in a collection it had left.
     *
     * This is where set reconciliation departs from every other recorded fact: a
     * release year Plex has momentarily stopped reporting is better held stale
     * than lost, but an absent membership is an answer.
     */
    public function testAFilmTakenOffACollectionLosesThatSet(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);

        $library = new PlexLibrary('1', 'Movies', 'movie');
        $thumb = '/library/metadata/10/thumb/1';
        $film = new PlexItem('10', PlexMediaType::Movie, 'Jackass 2.5', 2007, $thumb, 'Movies');
        $collection = new PlexItem('80', PlexMediaType::Collection, 'Jackass', null, '/t/80', 'Movies');

        // Imported while it was in the collection.
        $before = new FakePlexClient(
            [$library],
            ['1' => [$film]],
            [],
            ['1' => [$collection]],
            membersByCollection: ['80' => [$film]],
        );
        (new ImportService($before, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);
        self::assertSame(['80'], $items->findByRatingKey('10')?->setKeys);

        // Taken off the collection in Plex. The poster has not changed, so this
        // is the skip path — the one an established library actually takes.
        $after = new FakePlexClient(
            [$library],
            ['1' => [$film]],
            [],
            ['1' => [$collection]],
            membersByCollection: ['80' => []],
        );
        $result = (new ImportService($after, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame([], $items->findByRatingKey('10')->setKeys, 'It has left the collection.');
        self::assertSame(1, $result->skipped());
        self::assertSame([], $after->downloads, 'Nothing was re-downloaded to notice.');
    }

    /**
     * The other half, and the reason removal cannot simply act on an empty
     * result: a collection that will not list produces the same emptiness. One
     * failed request must not take every film out of every set.
     */
    public function testAFailedMembershipReadLeavesRecordedSetsAlone(): void
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $items = new PlexItemRepository($database);
        $libraryRepo = new PlexLibraryRepository($database);

        $library = new PlexLibrary('1', 'Movies', 'movie');
        $thumb = '/library/metadata/10/thumb/1';
        $film = new PlexItem('10', PlexMediaType::Movie, 'Jackass 2.5', 2007, $thumb, 'Movies');
        $collection = new PlexItem('80', PlexMediaType::Collection, 'Jackass', null, '/t/80', 'Movies');

        $before = new FakePlexClient(
            [$library],
            ['1' => [$film]],
            [],
            ['1' => [$collection]],
            membersByCollection: ['80' => [$film]],
        );
        (new ImportService($before, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);
        self::assertSame(['80'], $items->findByRatingKey('10')?->setKeys);

        $broken = new FailingCollectionWalkClient(
            [$library],
            ['1' => [$film]],
            [],
            ['1' => [$collection]],
        );
        $result = (new ImportService($broken, $storage, $items, $libraryRepo))->import(['1'], [PlexMediaType::Movie]);

        self::assertSame(['80'], $items->findByRatingKey('10')->setKeys, 'A failed read concludes nothing.');
        self::assertSame(0, $result->failed(), 'And does not fail the import.');
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
