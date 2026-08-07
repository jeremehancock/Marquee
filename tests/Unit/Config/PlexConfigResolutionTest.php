<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\PlexConfig;
use App\Plex\Connection\PlexConnectionStore;
use PHPUnit\Framework\TestCase;

final class PlexConfigResolutionTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/marquee-resolve-' . bin2hex(random_bytes(6));
        putenv('PLEX_SERVER_URL=http://plex:32400');
        putenv('PLEX_TOKEN=');
    }

    protected function tearDown(): void
    {
        putenv('PLEX_SERVER_URL=');
        putenv('PLEX_TOKEN=');

        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testTheStoredTokenIsTheCredential(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('from-sign-in');

        $config = PlexConfig::resolve($store);

        self::assertSame('from-sign-in', $config->token);
        self::assertTrue($config->isConfigured());
    }

    public function testAnEnvironmentTokenIsNotACredential(): void
    {
        putenv('PLEX_TOKEN=from-environment');

        $config = PlexConfig::resolve(new PlexConnectionStore($this->dir));

        // Read, but never used to authenticate.
        self::assertSame('', $config->token);
        self::assertFalse($config->isConfigured());
    }

    public function testAnEnvironmentTokenNeverOverridesTheStoredOne(): void
    {
        putenv('PLEX_TOKEN=from-environment');
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('from-sign-in');

        self::assertSame('from-sign-in', PlexConfig::resolve($store)->token);
    }

    public function testAnEnvironmentTokenIsReportedAsObsolete(): void
    {
        putenv('PLEX_TOKEN=from-environment');

        // This is the one thing the variable still does: drive the notice that
        // tells an upgrading user why their install is disconnected.
        self::assertTrue(PlexConfig::resolve(new PlexConnectionStore($this->dir))->obsoleteEnvToken);
    }

    public function testNoEnvironmentTokenIsNotReportedAsObsolete(): void
    {
        self::assertFalse(PlexConfig::resolve(new PlexConnectionStore($this->dir))->obsoleteEnvToken);
    }

    public function testNoStoredTokenMeansNotConfigured(): void
    {
        $config = PlexConfig::resolve(new PlexConnectionStore($this->dir));

        self::assertSame('', $config->token);
        self::assertFalse($config->isConfigured());
    }

    public function testAStoredTokenIsNotEnoughWithoutAServerAddress(): void
    {
        putenv('PLEX_SERVER_URL=');
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('from-sign-in');

        // Signing in cannot supply an address; the connection screen has to say
        // so rather than offering to sign in again.
        self::assertFalse(PlexConfig::resolve($store)->isConfigured());
    }

    public function testResolutionNeverWritesToTheStore(): void
    {
        putenv('PLEX_TOKEN=from-environment');

        PlexConfig::resolve(new PlexConnectionStore($this->dir));

        // An environment token is not migrated onto disk on the user's behalf.
        self::assertNull((new PlexConnectionStore($this->dir))->token());
    }
}
