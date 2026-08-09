<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Plex\SignedImagePath;
use App\Poster\Wall\StreamToken;
use PHPUnit\Framework\TestCase;

/**
 * The signature is the whole of the protection on the image proxies: without a
 * verified one, a proxy fetches whatever path a caller names, using the
 * server's own Plex token to do it.
 */
final class SignedImagePathTest extends TestCase
{
    private SignedImagePath $paths;

    protected function setUp(): void
    {
        $this->paths = new SignedImagePath('candidate-secret');
    }

    public function testRoundTripsAnImagePath(): void
    {
        $signed = $this->paths->sign('/library/metadata/10/thumb/171');

        self::assertSame('/library/metadata/10/thumb/171', $this->paths->pathFor($signed));
    }

    public function testTokenDoesNotDiscloseThePath(): void
    {
        $signed = $this->paths->sign('/library/metadata/10/thumb/171');

        self::assertStringNotContainsString('/library/metadata', $signed);
    }

    public function testRejectsATamperedToken(): void
    {
        self::assertNull($this->paths->pathFor($this->paths->sign('/t/1') . 'x'));
    }

    public function testRejectsATokenSignedWithAnotherSecret(): void
    {
        $forged = (new SignedImagePath('other-secret'))->sign('/t/1');

        self::assertNull($this->paths->pathFor($forged));
    }

    public function testRejectsAPathThatWasNeverSigned(): void
    {
        self::assertNull($this->paths->pathFor('/library/metadata/10/thumb/171'));
        self::assertNull($this->paths->pathFor('not-a-token'));
        self::assertNull($this->paths->pathFor(''));
    }

    /**
     * A correctly signed token still cannot point the proxy at another host.
     */
    public function testRejectsACorrectlySignedNonAbsolutePath(): void
    {
        self::assertNull($this->paths->pathFor($this->paths->sign('http://evil/')));
    }

    /**
     * The wall's poster proxy is public and prints its tokens into a page anyone
     * can read. Deriving the candidate key from the same secret rather than
     * reusing it is what stops one of those tokens being replayed against the
     * other proxy — in particular, a candidate token being fed to the public
     * wall route by someone with no session at all.
     */
    public function testCandidateTokensAndWallTokensAreNotInterchangeable(): void
    {
        $secret = 'shared-install-secret';
        $derived = hash_hmac('sha256', 'plex-poster-candidate', $secret);

        $candidate = new SignedImagePath($derived);
        $wall = new StreamToken($secret);

        self::assertNull($wall->thumbFor($candidate->sign('/library/metadata/10/thumb/171')));
        self::assertNull($candidate->pathFor($wall->forThumb('/library/metadata/10/thumb/171')));
    }
}
