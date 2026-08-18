<?php

declare(strict_types=1);

namespace App\Plex\Import;

/**
 * How often auto-import runs.
 *
 * The backing value is the slug the setting has always used, so an install
 * seeded from `AUTO_IMPORT_SCHEDULE` keeps its schedule without migration.
 *
 * These were a `case` statement in the container's init script mapping each slug
 * to a cron expression. They are here now because the application decides when a
 * run is due, and one definition is what stops the settings screen offering a
 * choice the scheduler cannot honour.
 *
 * Every interval divides the day evenly, which is not decoration: slots are
 * anchored at local midnight, so an interval that did not divide 24 would give a
 * short final slot each day and a firing time that moved.
 */
enum AutoImportInterval: string
{
    case Hourly = '1h';
    case EveryThreeHours = '3h';
    case EverySixHours = '6h';
    case TwelveHourly = '12h';
    case Daily = '24h';

    /**
     * The span of one slot, in hours.
     */
    public function hours(): int
    {
        return match ($this) {
            self::Hourly => 1,
            self::EveryThreeHours => 3,
            self::EverySixHours => 6,
            self::TwelveHourly => 12,
            self::Daily => 24,
        };
    }

    /**
     * What the settings screen calls it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Every hour',
            self::EveryThreeHours => 'Every 3 hours',
            self::EverySixHours => 'Every 6 hours',
            self::TwelveHourly => 'Every 12 hours',
            self::Daily => 'Once a day',
        };
    }

    /**
     * Resolve a stored slug, or null when it names no interval offered.
     */
    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom(strtolower(trim($slug)));
    }

    /**
     * What an unrecognised or unset value resolves to.
     *
     * Daily, matching both the setting's default and the cron expression the
     * init script fell back to for an unknown slug.
     */
    public static function default(): self
    {
        return self::Daily;
    }
}
