<?php

declare(strict_types=1);

namespace App\Plex\Import;

use DateTimeImmutable;

/**
 * Decides whether the tick that just fired should run an import.
 *
 * The container's crontab is now a fixed hourly tick that knows nothing about
 * the schedule. This is what the schedule became: the day is divided into slots
 * of the configured interval, anchored at local midnight, and a run is due when
 * the current slot has not been completed.
 *
 * Slots rather than "has an interval elapsed since the last run" is the whole
 * design, and the difference is not academic. Elapsed time drifts: a daily
 * import finishing at 00:05 is next due at 00:05 tomorrow, the 00:00 tick misses
 * it, it runs at 01:00, and it walks an hour later every day until it wraps
 * around. Slots reproduce exactly the cron expressions this replaces — `24h` is
 * midnight, `6h` is 00:00/06:00/12:00/18:00 — which is what an install upgrading
 * into this already had.
 *
 * Anchoring is at *local* midnight because that is what a crontab does, and the
 * container's timezone is the one the user set. Both `crond` and PHP read the
 * same `TZ`.
 *
 * Catch-up falls out of comparing slots rather than clocks: an install that was
 * switched off at midnight finds today's slot outstanding when it starts at
 * 03:00 and runs then. Only one import ever results, however many slots were
 * missed — an import is a synchronisation with Plex, not a queue of work, so
 * running it five times would not do more than running it once.
 */
final class AutoImportSchedule
{
    /**
     * How long a run may hold the guard before it is presumed dead.
     *
     * Generous on purpose. The cost of being wrong in one direction is two
     * imports overlapping; in the other, it is auto-import silently never
     * running again — so a bound that errs long is the wrong kind of safe, and
     * this errs only long enough to clear the slowest plausible import.
     */
    public const ABANDONED_AFTER_SECONDS = 21600;

    public function __construct(
        private readonly AutoImportInterval $interval,
    ) {
    }

    /**
     * Identifies the slot the given moment falls in, as `YYYYMMDDnn` where `nn`
     * is the slot's index within the local day.
     *
     * An identifier rather than the slot's start timestamp, because the only
     * thing ever asked of it is whether one slot is later than another, and a
     * timestamp answers that wrongly twice a year. A spring-forward day is 23
     * hours long, so local midnight plus 23 hours is the *next* midnight: with an
     * hourly interval, that day's last slot and the next day's first slot would
     * compute to the same timestamp and one run would be skipped. Composing the
     * local date with the index cannot collide, because the date dominates.
     *
     * Local, not UTC: a crontab fires on the container's clock, and that clock is
     * what the user set `TZ` to.
     */
    public function slotFor(DateTimeImmutable $now): int
    {
        $slotIndex = intdiv((int) $now->format('G'), $this->interval->hours());

        return ((int) $now->format('Ymd')) * 100 + $slotIndex;
    }

    /**
     * Whether a run is due: the current slot has not been completed.
     *
     * A `lastCompletedSlot` in the future — a clock that moved backwards, or a
     * database carried between hosts — is treated as not-due rather than as an
     * error, so the schedule settles by itself once the clock passes it again.
     */
    public function isDue(DateTimeImmutable $now, ?int $lastCompletedSlot): bool
    {
        if ($lastCompletedSlot === null) {
            return true;
        }

        return $this->slotFor($now) > $lastCompletedSlot;
    }

    /**
     * Whether a run that started at the given time should be presumed abandoned.
     */
    public function isAbandoned(DateTimeImmutable $now, int $runningSince): bool
    {
        return ($now->getTimestamp() - $runningSince) >= self::ABANDONED_AFTER_SECONDS;
    }
}
