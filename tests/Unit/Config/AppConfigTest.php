<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;

/**
 * Where sessions are stored, which decides whether a login survives an update.
 *
 * The default is the whole point: sessions left in the container's `/tmp` are
 * discarded whenever the container is recreated, and pulling a new image
 * recreates the container — so a thirty-day window really lasted until the next
 * update. Pointing the default at the persistent volume is what fixes that, and
 * a default nobody asserts is a default a refactor can quietly move.
 */
final class AppConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SESSION_DIR');
    }

    public function testSessionsDefaultToThePersistentVolume(): void
    {
        putenv('SESSION_DIR');

        self::assertSame('/config/sessions', AppConfig::fromEnv()->sessionDir);
    }

    /**
     * The escape hatch, for a `/config` on a network mount whose file locking
     * misbehaves. It is a path rather than a switch so that `/tmp`, a tmpfs, or
     * another volume all work without this having anticipated them.
     */
    public function testSessionDirCanBeMovedOffTheVolume(): void
    {
        putenv('SESSION_DIR=/tmp');

        self::assertSame('/tmp', AppConfig::fromEnv()->sessionDir);
    }

    /**
     * Trimmed like the sibling directories, so a trailing slash cannot produce
     * a doubled separator in the save path.
     */
    public function testATrailingSlashIsTrimmed(): void
    {
        putenv('SESSION_DIR=/mnt/sessions/');

        self::assertSame('/mnt/sessions', AppConfig::fromEnv()->sessionDir);
    }
}
