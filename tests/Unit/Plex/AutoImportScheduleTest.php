<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Plex\Import\AutoImportInterval;
use App\Plex\Import\AutoImportSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Slot arithmetic — the thing that replaced five cron expressions.
 *
 * The firing times asserted here are the ones those expressions produced:
 * midnight, and every 12, 6, 3, or 1 hours from midnight. An install upgrading
 * into this change keeps the schedule it already had, and these are what
 * "keeps" means.
 *
 * (The expressions themselves are not quoted in this docblock: they contain the
 * character pair that ends a block comment.)
 */
final class AutoImportScheduleTest extends TestCase
{
    private function at(string $time, string $zone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone($zone));
    }

    public function testDailyRunsAtMidnight(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Daily);

        $midnight = $schedule->slotFor($this->at('2026-08-18 00:00:00'));

        // Every hour of the day belongs to the midnight slot, so the first tick
        // of the day is the one that runs and the other twenty-three do not.
        self::assertSame($midnight, $schedule->slotFor($this->at('2026-08-18 13:00:00')));
        self::assertSame($midnight, $schedule->slotFor($this->at('2026-08-18 23:00:00')));
        self::assertNotSame($midnight, $schedule->slotFor($this->at('2026-08-19 00:00:00')));
    }

    public function testSixHourlyKeepsItsCronBoundaries(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::EverySixHours);

        // 00:00, 06:00, 12:00, 18:00 — the boundaries of `0 */6 * * *`.
        $slots = [];
        foreach (['00', '06', '12', '18'] as $hour) {
            $slots[] = $schedule->slotFor($this->at("2026-08-18 {$hour}:00:00"));
        }

        self::assertSame($slots, array_values(array_unique($slots)));
        // An hour inside a slot belongs to that slot's start, not its own.
        self::assertSame($slots[1], $schedule->slotFor($this->at('2026-08-18 07:00:00')));
        self::assertSame($slots[1], $schedule->slotFor($this->at('2026-08-18 11:00:00')));
    }

    public function testHourlyGivesEveryHourItsOwnSlot(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Hourly);

        $slots = [];
        for ($hour = 0; $hour < 24; ++$hour) {
            $slots[] = $schedule->slotFor($this->at(sprintf('2026-08-18 %02d:00:00', $hour)));
        }

        self::assertCount(24, array_unique($slots));
    }

    public function testSlotsIncreaseAcrossADaylightSavingChange(): void
    {
        // 2026-03-29 is a 23-hour day in London: the clocks go forward at 01:00.
        // A slot computed as "local midnight plus N hours" would make that day's
        // last hourly slot collide with the next day's first, and one run would
        // be silently skipped.
        $schedule = new AutoImportSchedule(AutoImportInterval::Hourly);

        $lastOfShortDay = $schedule->slotFor($this->at('2026-03-29 23:00:00', 'Europe/London'));
        $firstOfNextDay = $schedule->slotFor($this->at('2026-03-30 00:00:00', 'Europe/London'));

        self::assertGreaterThan($lastOfShortDay, $firstOfNextDay);
    }

    public function testSlotsFollowLocalTimeRatherThanUtc(): void
    {
        // Local midnight in Chicago is 05:00 or 06:00 UTC. A schedule anchored to
        // UTC would fire in the middle of the user's evening.
        $schedule = new AutoImportSchedule(AutoImportInterval::Daily);

        $lateEvening = $schedule->slotFor($this->at('2026-08-18 23:00:00', 'America/Chicago'));
        $justAfterMidnight = $schedule->slotFor($this->at('2026-08-19 00:30:00', 'America/Chicago'));

        self::assertGreaterThan($lateEvening, $justAfterMidnight);
    }

    public function testAFreshInstallIsDue(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Daily);

        self::assertTrue($schedule->isDue($this->at('2026-08-18 00:00:00'), null));
    }

    public function testACompletedSlotIsNotDueAgainWithinIt(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Daily);
        $now = $this->at('2026-08-18 00:00:00');

        $completed = $schedule->slotFor($now);

        self::assertFalse($schedule->isDue($this->at('2026-08-18 13:00:00'), $completed));
        self::assertFalse($schedule->isDue($this->at('2026-08-18 23:00:00'), $completed));
    }

    public function testTheNextSlotIsDue(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Daily);

        $completed = $schedule->slotFor($this->at('2026-08-18 00:00:00'));

        self::assertTrue($schedule->isDue($this->at('2026-08-19 00:00:00'), $completed));
    }

    /**
     * The behaviour a crontab could not give: an install that was switched off
     * at its scheduled time runs when it comes back, rather than waiting a full
     * interval.
     */
    public function testAMissedSlotIsDueAtTheNextTick(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Daily);

        $completed = $schedule->slotFor($this->at('2026-08-17 00:00:00'));

        // Started at 03:00 having missed midnight.
        self::assertTrue($schedule->isDue($this->at('2026-08-18 03:00:00'), $completed));
    }

    public function testCatchUpDoesNotRepeat(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Daily);

        // Several slots were missed; the catch-up run records the current one.
        $now = $this->at('2026-08-18 03:00:00');
        $completed = $schedule->slotFor($now);

        self::assertFalse($schedule->isDue($this->at('2026-08-18 04:00:00'), $completed));
    }

    /**
     * A clock that moved backwards settles by itself rather than raising.
     */
    public function testASlotFromTheFutureIsNotDue(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Daily);

        $future = $schedule->slotFor($this->at('2026-09-01 00:00:00'));

        self::assertFalse($schedule->isDue($this->at('2026-08-18 00:00:00'), $future));
    }

    public function testARunIsAbandonedOnlyAfterTheBound(): void
    {
        $schedule = new AutoImportSchedule(AutoImportInterval::Hourly);
        $now = $this->at('2026-08-18 12:00:00');

        $justStarted = $now->getTimestamp() - 60;
        $ancient = $now->getTimestamp() - AutoImportSchedule::ABANDONED_AFTER_SECONDS;

        self::assertFalse($schedule->isAbandoned($now, $justStarted));
        self::assertTrue($schedule->isAbandoned($now, $ancient));
    }
}
