<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Config\AutoImportConfig;
use App\Database\AutoImportScheduleRepository;
use App\Database\Database;
use App\Database\PlexItemRepository;
use App\Database\PlexLibraryRepository;
use App\Plex\Import\AutoImportInterval;
use App\Plex\Import\AutoImportSchedule;
use App\Plex\Import\AutoImportService;
use App\Plex\Import\ImportService;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Poster\FilesystemPosterStorage;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\MakesImages;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Throwable;

final class AutoImportServiceTest extends TestCase
{
    use MakesImages;

    private string $dir;

    /** The schedule state of the most recently built service. */
    private AutoImportScheduleRepository $schedule;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function service(FakePlexClient $plex, AutoImportConfig $config): AutoImportService
    {
        $storage = new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
        $database = new Database(':memory:');
        $import = new ImportService(
            $plex,
            $storage,
            new PlexItemRepository($database),
            new PlexLibraryRepository($database),
        );

        $this->schedule = new AutoImportScheduleRepository($database);

        return new AutoImportService($plex, $import, $config, $this->schedule, new NullLogger());
    }

    private function countFiles(string $sub): int
    {
        $dir = $this->dir . '/' . $sub;
        if (!is_dir($dir)) {
            return 0;
        }

        return count(array_filter(scandir($dir) ?: [], static fn (string $f): bool => is_file($dir . '/' . $f)));
    }

    private function config(bool $enabled, bool $movies, bool $shows, bool $seasons): AutoImportConfig
    {
        return new AutoImportConfig($enabled, $movies, $shows, $seasons, false);
    }

    public function testImportsOnlyEnabledMediaTypes(): void
    {
        $movieLib = new PlexLibrary('1', 'Movies', 'movie');
        $showLib = new PlexLibrary('2', 'TV', 'show');
        $plex = new FakePlexClient(
            [$movieLib, $showLib],
            [
                '1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')],
                '2' => [new PlexItem('20', PlexMediaType::Show, 'Severance', null, '/t/20', 'TV')],
            ],
            ['20' => [new PlexItem('200', PlexMediaType::Season, 'Season 1', null, '/t/200', 'TV', 'Severance')]],
        );

        $result = $this->service($plex, $this->config(true, true, true, false))->run();

        self::assertNotNull($result);
        self::assertSame(2, $result->imported());
        self::assertSame(1, $this->countFiles('movies'));
        self::assertSame(1, $this->countFiles('tv-shows'));
        self::assertSame(0, $this->countFiles('tv-seasons'));
    }

    public function testExcludedLibraryIsSkipped(): void
    {
        $movies = new PlexLibrary('1', 'Movies', 'movie');
        $kids = new PlexLibrary('3', 'Kids', 'movie');
        $plex = new FakePlexClient(
            [$movies, $kids],
            [
                '1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')],
                '3' => [new PlexItem('30', PlexMediaType::Movie, 'Cars', 2006, '/t/30', 'Kids')],
            ],
            excluded: ['Kids'],
        );

        $result = $this->service($plex, $this->config(true, true, false, false))->run();

        self::assertNotNull($result);
        self::assertSame(1, $result->imported());
        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testDoesNothingWhenEveryLibraryIsExcluded(): void
    {
        $plex = new FakePlexClient(
            [new PlexLibrary('1', 'Movies', 'movie')],
            ['1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')]],
            excluded: ['Movies'],
        );

        self::assertNull($this->service($plex, $this->config(true, true, false, false))->run());
        self::assertSame(0, $this->countFiles('movies'));
    }

    public function testDisabledDoesNothing(): void
    {
        $plex = new FakePlexClient([new PlexLibrary('1', 'Movies', 'movie')]);

        self::assertNull($this->service($plex, $this->config(false, true, false, false))->run());
        self::assertSame(0, $this->countFiles('movies'));
    }

    public function testNothingSelectedDoesNothing(): void
    {
        $plex = new FakePlexClient([new PlexLibrary('1', 'Movies', 'movie')]);

        self::assertNull($this->service($plex, $this->config(true, false, false, false))->run());
    }

    public function testUnconfiguredDoesNothing(): void
    {
        $plex = new FakePlexClient([new PlexLibrary('1', 'Movies', 'movie')], configured: false);

        self::assertNull($this->service($plex, $this->config(true, true, false, false))->run());
    }

    // ---- Scheduling ----

    private function library(): FakePlexClient
    {
        return new FakePlexClient(
            [new PlexLibrary('1', 'Movies', 'movie')],
            ['1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')]],
        );
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }

    public function testARunRecordsItsSlotAndTheNextTickInThatSlotDoesNothing(): void
    {
        $service = $this->service($this->library(), $this->config(true, true, false, false));

        self::assertNotNull($service->run($this->at('2026-08-18 00:00:00')));

        // Same day, daily schedule: this tick is one of the twenty-three that
        // should do nothing.
        self::assertNull($service->run($this->at('2026-08-18 13:00:00')));
        self::assertSame(1, $this->countFiles('movies'));
    }

    public function testTheNextSlotRunsAgain(): void
    {
        $service = $this->service($this->library(), $this->config(true, true, false, false));

        self::assertNotNull($service->run($this->at('2026-08-18 00:00:00')));
        self::assertNotNull($service->run($this->at('2026-08-19 00:00:00')));
    }

    /**
     * A container that was off at midnight imports when it comes back, rather
     * than waiting a full day — and does so once, not once per missed slot.
     */
    public function testAMissedSlotIsCaughtUpExactlyOnce(): void
    {
        $service = $this->service($this->library(), $this->config(true, true, false, false));

        $service->run($this->at('2026-08-15 00:00:00'));

        // Three days later, having been switched off in between.
        self::assertNotNull($service->run($this->at('2026-08-18 03:00:00')));
        self::assertNull($service->run($this->at('2026-08-18 04:00:00')));
    }

    /**
     * Losing the schedule state is a deleted database. It costs one import and
     * nothing else — the bound under which `application-shell` admits this state
     * into the cache at all.
     */
    public function testLosingTheStateCostsOneImportAndNoError(): void
    {
        $service = $this->service($this->library(), $this->config(true, true, false, false));
        $service->run($this->at('2026-08-18 00:00:00'));

        // A fresh database, as deleting the file gives.
        $fresh = $this->service($this->library(), $this->config(true, true, false, false));

        self::assertNotNull($fresh->run($this->at('2026-08-18 13:00:00')));
    }

    public function testATickDuringARunIsSkipped(): void
    {
        $service = $this->service($this->library(), $this->config(true, true, false, false));
        $this->schedule->markRunning($this->at('2026-08-18 00:00:00')->getTimestamp());

        self::assertNull($service->run($this->at('2026-08-18 00:30:00')));
        self::assertSame(0, $this->countFiles('movies'));
    }

    /**
     * A guard nobody released must not stop auto-import for good. Silent and
     * unattended, that could go unnoticed for months.
     */
    public function testAnAbandonedGuardDoesNotBlockForever(): void
    {
        $service = $this->service($this->library(), $this->config(true, true, false, false));
        $stale = $this->at('2026-08-18 00:00:00')->getTimestamp() - AutoImportSchedule::ABANDONED_AFTER_SECONDS;
        $this->schedule->markRunning($stale);

        self::assertNotNull($service->run($this->at('2026-08-18 00:00:00')));
    }

    public function testTheGuardIsReleasedAfterASuccessfulRun(): void
    {
        $service = $this->service($this->library(), $this->config(true, true, false, false));
        $service->run($this->at('2026-08-18 00:00:00'));

        self::assertNull($this->schedule->runningSince());
    }

    /**
     * Released on failure too: one unreachable Plex server must not stop every
     * later tick.
     */
    public function testTheGuardIsReleasedWhenTheImportFails(): void
    {
        $plex = new FakePlexClient(
            [new PlexLibrary('1', 'Movies', 'movie')],
            ['1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')]],
            failingKeys: ['10'],
        );
        $service = $this->service($plex, $this->config(true, true, false, false));

        try {
            $service->run($this->at('2026-08-18 00:00:00'));
        } catch (Throwable) {
            // The failure itself is ImportService's business; what matters here
            // is what it left behind.
        }

        self::assertNull($this->schedule->runningSince());
    }

    /**
     * The interval is the application's now, so changing it changes when a tick
     * is due without anything being restarted.
     */
    public function testTheIntervalGovernsWhichTicksRun(): void
    {
        $config = new AutoImportConfig(true, true, false, false, false, AutoImportInterval::EverySixHours);
        $service = $this->service($this->library(), $config);

        self::assertNotNull($service->run($this->at('2026-08-18 00:00:00')));
        // Inside the same six-hour slot.
        self::assertNull($service->run($this->at('2026-08-18 03:00:00')));
        // The next one.
        self::assertNotNull($service->run($this->at('2026-08-18 06:00:00')));
    }
}
