<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\PlexConfig;
use App\Plex\Connection\PlexConnectionStore;
use App\Tests\Support\SeedsSettings;
use PHPUnit\Framework\TestCase;

final class PlexConfigResolutionTest extends TestCase
{
    use SeedsSettings;

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

        $config = PlexConfig::resolve($store, $this->seededStore());

        self::assertSame('from-sign-in', $config->token);
        self::assertTrue($config->isConfigured());
    }

    public function testAnEnvironmentTokenIsNotACredential(): void
    {
        putenv('PLEX_TOKEN=from-environment');

        $config = PlexConfig::resolve(new PlexConnectionStore($this->dir), $this->seededStore());

        // Read, but never used to authenticate.
        self::assertSame('', $config->token);
        self::assertFalse($config->isConfigured());
    }

    public function testAnEnvironmentTokenNeverOverridesTheStoredOne(): void
    {
        putenv('PLEX_TOKEN=from-environment');
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('from-sign-in');

        self::assertSame('from-sign-in', PlexConfig::resolve($store, $this->seededStore())->token);
    }

    // Reporting `PLEX_TOKEN` as obsolete used to be this class's job, through a
    // flag on the config it resolved. It belongs to
    // {@see \App\Tests\Unit\Settings\SupersededEnvironmentTest} now, along with
    // every other superseded variable. What stays here is the half that is
    // about the credential: that the variable authenticates nothing.

    public function testNoStoredTokenMeansNotConfigured(): void
    {
        $config = PlexConfig::resolve(new PlexConnectionStore($this->dir), $this->seededStore());

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
        self::assertFalse(PlexConfig::resolve($store, $this->seededStore())->isConfigured());
    }

    public function testResolutionNeverWritesToTheStore(): void
    {
        putenv('PLEX_TOKEN=from-environment');

        PlexConfig::resolve(new PlexConnectionStore($this->dir), $this->seededStore());

        // An environment token is not migrated onto disk on the user's behalf.
        self::assertNull((new PlexConnectionStore($this->dir))->token());
    }

    /**
     * A port above 65535 does not parse, and the exception Guzzle raises for it
     * is an InvalidArgumentException rather than a GuzzleException — so nothing
     * on the Plex path caught it and the connection screen answered with a
     * stack trace. Typing 324000 for 32400 was enough.
     */
    public function testAnUnparseableServerUrlIsTreatedAsNoAddress(): void
    {
        foreach ([
            'http://192.168.1.10:324000',
            'http://192.168.1.10:abc',
            'http://192.168.1.10:-1',
            'http://my server:32400',
            'http://[::1',
        ] as $url) {
            putenv('PLEX_SERVER_URL=' . $url);
            $store = new PlexConnectionStore($this->dir);
            $store->storeToken('a-token');

            $config = PlexConfig::resolve($store, $this->seededStore());

            self::assertSame('', $config->serverUrl, $url);
            self::assertFalse($config->isConfigured(), $url);
        }
    }

    /**
     * A stray space in a compose file survives into the value and is enough to
     * make the address unparseable, so it is trimmed before anything else.
     */
    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        putenv('PLEX_SERVER_URL=  http://plex:32400/  ');
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('a-token');

        $config = PlexConfig::resolve($store, $this->seededStore());

        self::assertSame('http://plex:32400', $config->serverUrl);
        self::assertTrue($config->isConfigured());
    }

    public function testAUsableAddressIsKept(): void
    {
        foreach ([
            'http://plex:32400' => 'http://plex:32400',
            'https://192.168.1.10:32400/' => 'https://192.168.1.10:32400',
            'http://[::1]:32400' => 'http://[::1]:32400',
            'https://plex.example.com' => 'https://plex.example.com',
        ] as $raw => $expected) {
            putenv('PLEX_SERVER_URL=' . $raw);
            $store = new PlexConnectionStore($this->dir);
            $store->storeToken('a-token');

            self::assertSame($expected, PlexConfig::resolve($store, $this->seededStore())->serverUrl, $raw);
        }
    }
}
