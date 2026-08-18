<?php

declare(strict_types=1);

namespace App\Plex\Import;

use App\Config\AutoImportConfig;
use App\Database\AutoImportScheduleRepository;
use App\Plex\PlexClient;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs one unattended import of the configured media types across every Plex
 * library the client reports — excluded libraries are already filtered out
 * there.
 *
 * Scheduling is decided here rather than by the container. The crontab is a
 * fixed hourly tick that knows nothing about the settings; this asks
 * {@see AutoImportSchedule} whether the tick that woke it is due, and most ticks
 * are not. A tick that does nothing is the design working, not a failure, so it
 * returns quietly rather than warning.
 *
 * The schedule check sits in *front* of the enabled / configured / media-type
 * checks rather than replacing any of them. Being due is about the clock; those
 * are about whether there is anything to do, and a due tick still has to pass
 * all of them.
 */
final class AutoImportService
{
    public function __construct(
        private readonly PlexClient $plex,
        private readonly ImportService $import,
        private readonly AutoImportConfig $config,
        private readonly AutoImportScheduleRepository $schedule,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(?DateTimeImmutable $now = null): ?ImportResult
    {
        $now ??= new DateTimeImmutable();
        $schedule = new AutoImportSchedule($this->config->interval);

        if (!$this->config->enabled) {
            $this->logger->info('Auto-import is disabled; skipping.');

            return null;
        }

        if (!$schedule->isDue($now, $this->schedule->lastCompletedSlot())) {
            // Deliberately not logged at info. With an hourly tick and a daily
            // schedule this is twenty-three out of every twenty-four wake-ups,
            // and a log that is mostly this is a log nobody reads.
            $this->logger->debug('Auto-import is not due yet; skipping.');

            return null;
        }

        $runningSince = $this->schedule->runningSince();
        if ($runningSince !== null && !$schedule->isAbandoned($now, $runningSince)) {
            $this->logger->info('Auto-import is already running; skipping this tick.');

            return null;
        }
        if ($runningSince !== null) {
            // A run that never released the guard. Proceeding is the lesser
            // failure: overlapping imports cost time, whereas a guard nobody
            // clears would stop auto-import until someone noticed, which — being
            // silent and unattended — could be months.
            $this->logger->warning('Auto-import found an abandoned run; starting a new one.');
        }

        if (!$this->plex->isConfigured()) {
            $this->logger->warning('Auto-import skipped: Plex is not configured.');

            return null;
        }

        $mediaTypes = $this->config->mediaTypes();
        if ($mediaTypes === []) {
            $this->logger->info('Auto-import skipped: no media types are enabled.');

            return null;
        }

        // libraries() already omits excluded libraries, so an empty list here
        // means there is genuinely nothing to import.
        $sectionKeys = [];
        foreach ($this->plex->libraries() as $library) {
            $sectionKeys[] = $library->key;
        }

        if ($sectionKeys === []) {
            $this->logger->info('Auto-import skipped: no libraries to import.');

            return null;
        }

        // Only now is a run actually starting, so only now is the guard taken.
        // Taking it before the checks above would mean a disabled or
        // unconfigured install repeatedly claiming and releasing it for nothing.
        $this->schedule->markRunning($now->getTimestamp());

        try {
            $result = $this->import->import($sectionKeys, $mediaTypes);
        } catch (Throwable $e) {
            // Released on failure as well as on success. A failed import that
            // left the guard held would stop every later tick, turning one
            // unreachable Plex server into auto-import never running again.
            $this->schedule->clearRunning();

            throw $e;
        }

        // Recorded on completion, never on start: a run interrupted part-way
        // leaves its slot outstanding and is retried at the next tick, because a
        // half-finished import is not an import.
        $this->schedule->recordCompleted($schedule->slotFor($now));
        $this->schedule->clearRunning();

        $this->logger->info('Auto-import complete. ' . $result->summary());

        return $result;
    }
}
