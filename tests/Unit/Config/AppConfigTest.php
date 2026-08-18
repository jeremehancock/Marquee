<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\AppConfig;
use App\Tests\Support\SeedsSettings;
use PHPUnit\Framework\TestCase;

/**
 * Every default `AppConfig` applies, pinned.
 *
 * A default nobody asserts is a default a refactor can move silently. That is
 * not a hypothetical here: the session save path was left to the runtime's
 * default, the base image put it in the container's `/tmp`, and every image
 * update quietly signed users out. Nothing failed, because nothing asserted.
 *
 * These defaults are promises about where a self-hosted install keeps its
 * posters, its database, and its sessions. Changing one should require changing
 * a test that says so out loud.
 *
 * The promise covers the undocumented variables too. Withholding `DATA_DIR` and
 * `POSTERS_DIR` from the README is a decision about what to advertise, not a
 * decision to stop guaranteeing where the data goes — see
 * {@see ConfigurationSurfaceTest} for the other half of that decision.
 */
final class AppConfigTest extends TestCase
{
    use SeedsSettings;

    /**
     * Every name this class touches, unconditionally.
     *
     * `putenv()` is process-global and PHPUnit runs a single process, so a value
     * leaked from one test would be read as the environment by any later test
     * that expects a default.
     */
    protected function tearDown(): void
    {
        putenv('SITE_TITLE');
        putenv('DATA_DIR');
        putenv('POSTERS_DIR');
        putenv('SESSION_DIR');
        putenv('DISPLAY_ERRORS');
    }

    /**
     * The literal, deliberately, rather than `AppConfig::APP_NAME`.
     *
     * A test that computes its expectation from the same constant the code reads
     * cannot detect that constant changing — which is the one thing worth
     * catching about a product name.
     */
    public function testSiteTitleDefaultsToTheProductName(): void
    {
        putenv('SITE_TITLE');

        self::assertSame('Marquee', AppConfig::resolve($this->seededStore())->siteTitle);
    }

    public function testDataDefaultsToThePersistentVolume(): void
    {
        putenv('DATA_DIR');

        self::assertSame('/config/data', AppConfig::resolve($this->seededStore())->dataDir);
    }

    public function testPostersDefaultToThePersistentVolume(): void
    {
        putenv('POSTERS_DIR');

        self::assertSame('/config/posters', AppConfig::resolve($this->seededStore())->postersDir);
    }

    /**
     * The default is the whole point. Sessions left in the container's `/tmp`
     * are discarded whenever the container is recreated, and pulling a new image
     * recreates the container — so a thirty-day window really lasted until the
     * next update. Pointing the default at the persistent volume is what fixes
     * that.
     */
    public function testSessionsDefaultToThePersistentVolume(): void
    {
        putenv('SESSION_DIR');

        self::assertSame('/config/sessions', AppConfig::resolve($this->seededStore())->sessionDir);
    }

    /**
     * Off unless asked for, because the alternative renders the exception in
     * place of the generic error page. A stack trace names filesystem paths and
     * configuration, so this default is what keeps a reachable install from
     * describing itself to whoever provokes an error.
     */
    public function testErrorsAreNotDisplayedByDefault(): void
    {
        putenv('DISPLAY_ERRORS');

        self::assertFalse(AppConfig::resolve($this->seededStore())->displayErrors);
    }

    /**
     * The escape hatch, for a `/config` on a network mount whose file locking
     * misbehaves. It is a path rather than a switch so that `/tmp`, a tmpfs, or
     * another volume all work without this having anticipated them.
     */
    public function testSessionDirCanBeMovedOffTheVolume(): void
    {
        putenv('SESSION_DIR=/tmp');

        self::assertSame('/tmp', AppConfig::resolve($this->seededStore())->sessionDir);
    }

    /**
     * Paths are composed by appending, so an untrimmed trailing slash yields a
     * doubled separator in every path built from the setting.
     *
     * One test per directory rather than one loop over the three: these are read
     * at the moment something breaks, and a failure should name the setting that
     * regressed rather than a row index.
     */
    public function testATrailingSlashIsTrimmedFromTheDataDirectory(): void
    {
        putenv('DATA_DIR=/mnt/data/');

        self::assertSame('/mnt/data', AppConfig::resolve($this->seededStore())->dataDir);
    }

    public function testATrailingSlashIsTrimmedFromThePostersDirectory(): void
    {
        putenv('POSTERS_DIR=/mnt/posters/');

        self::assertSame('/mnt/posters', AppConfig::resolve($this->seededStore())->postersDir);
    }

    public function testATrailingSlashIsTrimmedFromTheSessionDirectory(): void
    {
        putenv('SESSION_DIR=/mnt/sessions/');

        self::assertSame('/mnt/sessions', AppConfig::resolve($this->seededStore())->sessionDir);
    }
}
