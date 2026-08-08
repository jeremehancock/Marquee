<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Plex\Connection\PlexConnectionStore;
use App\Poster\Wall\StreamToken;
use PHPUnit\Framework\TestCase;

/**
 * The wall's signing secret is its own, not the Plex token. These are the two
 * failures that coupling would produce once the Plex token became mutable:
 * signing in again would invalidate tokens already on a running wall, and an
 * empty secret would let anyone compute a valid signature.
 */
final class StreamTokenSecretTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/marquee-wall-secret-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testTokensSurviveSigningInAgain(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('first-plex-token');

        $minted = (new StreamToken($store->signingSecret()))->forThumb('/library/metadata/10/thumb/171');

        // Sign in again: a different Plex token entirely.
        $store->storeToken('second-plex-token');
        $after = new StreamToken((new PlexConnectionStore($this->dir))->signingSecret());

        self::assertSame('/library/metadata/10/thumb/171', $after->thumbFor($minted));
    }

    public function testTokensSurviveSigningOut(): void
    {
        $store = new PlexConnectionStore($this->dir);
        $store->storeToken('a-plex-token');
        $minted = (new StreamToken($store->signingSecret()))->forThumb('/t/1');

        $store->clearToken();

        self::assertSame('/t/1', (new StreamToken($store->signingSecret()))->thumbFor($minted));
    }

    public function testSecretExistsAndIsNotEmptyWithoutAnyPlexToken(): void
    {
        $secret = (new PlexConnectionStore($this->dir))->signingSecret();

        self::assertNotSame('', $secret);
        // An empty secret still produces a signature, so "not empty" is the
        // property that actually keeps tokens unforgeable.
        self::assertNull((new StreamToken(''))->thumbFor((new StreamToken($secret))->forThumb('/t/1')));
    }

    public function testSeparateInstallsDoNotShareASecret(): void
    {
        $other = $this->dir . '-other';
        $mine = (new StreamToken((new PlexConnectionStore($this->dir))->signingSecret()))->forThumb('/t/1');
        $theirs = new StreamToken((new PlexConnectionStore($other))->signingSecret());

        self::assertNull($theirs->thumbFor($mine));

        foreach (glob($other . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($other);
    }
}
