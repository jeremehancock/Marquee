<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\PlexConfig;
use App\Config\PlexTokenSource;
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

    public function testEnvironmentTokenWinsOverAStoredOne(): void
    {
        putenv('PLEX_TOKEN=from-environment');
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('from-sign-in');

        $config = PlexConfig::resolve($store);

        self::assertSame('from-environment', $config->token);
        self::assertSame(PlexTokenSource::Environment, $config->source());
        self::assertFalse($config->isSignedIn());
        self::assertTrue($config->isConfigured());
    }

    public function testStoredTokenIsUsedWhenTheVariableIsUnset(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('from-sign-in');

        $config = PlexConfig::resolve($store);

        self::assertSame('from-sign-in', $config->token);
        self::assertSame(PlexTokenSource::Stored, $config->source());
        self::assertTrue($config->isSignedIn());
        self::assertTrue($config->isConfigured());
    }

    public function testAnEmptyVariableFallsBackToTheStoredToken(): void
    {
        putenv('PLEX_TOKEN=');
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('from-sign-in');

        self::assertSame('from-sign-in', PlexConfig::resolve($store)->token);
    }

    public function testNeitherSourceMeansNotConfigured(): void
    {
        $config = PlexConfig::resolve(new PlexConnectionStore($this->dir));

        self::assertSame('', $config->token);
        self::assertSame(PlexTokenSource::None, $config->source());
        self::assertFalse($config->isSignedIn());
        self::assertFalse($config->isConfigured());
    }

    public function testSourceIsNoneWhenNoTokenIsPresentRegardlessOfDeclaredSource(): void
    {
        $config = new PlexConfig('http://plex:32400', '', 10, 60);

        self::assertSame(PlexTokenSource::None, $config->source());
    }

    public function testStoringATokenIsNotEnoughWithoutAServerUrl(): void
    {
        putenv('PLEX_SERVER_URL=');
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('from-sign-in');

        self::assertFalse(PlexConfig::resolve($store)->isConfigured());
    }

    public function testResolutionDoesNotWriteAStoreEntryForAnEnvironmentToken(): void
    {
        putenv('PLEX_TOKEN=from-environment');
        $store = new PlexConnectionStore($this->dir);

        PlexConfig::resolve($store);

        // Nothing may be persisted on its own: storing a credential is always
        // the result of a deliberate sign-in.
        self::assertNull((new PlexConnectionStore($this->dir))->token());
    }
}
