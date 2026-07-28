<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Wall\StreamToken;
use PHPUnit\Framework\TestCase;

final class StreamTokenTest extends TestCase
{
    private StreamToken $token;

    protected function setUp(): void
    {
        $this->token = new StreamToken('plex-secret');
    }

    public function testRoundTripsAThumbPath(): void
    {
        $encoded = $this->token->forThumb('/library/metadata/10/thumb/171');

        self::assertSame('/library/metadata/10/thumb/171', $this->token->thumbFor($encoded));
    }

    public function testRejectsATamperedToken(): void
    {
        $encoded = $this->token->forThumb('/t/1');

        self::assertNull($this->token->thumbFor($encoded . 'x'));
    }

    public function testRejectsATokenSignedWithAnotherSecret(): void
    {
        $forged = (new StreamToken('other-secret'))->forThumb('/t/1');

        self::assertNull($this->token->thumbFor($forged));
    }

    public function testRejectsANonAbsolutePath(): void
    {
        // Correctly signed, but the payload is not a Plex image path.
        $payload = rtrim(strtr(base64_encode('http://evil/'), '+/', '-_'), '=');
        $signed = $this->token->forThumb('http://evil/');

        self::assertStringStartsWith($payload . '.', $signed);
        self::assertNull($this->token->thumbFor($signed));
    }

    public function testLiveSentinelIsRecognisedAndHasNoThumb(): void
    {
        self::assertTrue($this->token->isLive(StreamToken::LIVE));
        self::assertNull($this->token->thumbFor(StreamToken::LIVE));
    }

    public function testGarbageTokenResolvesToNull(): void
    {
        self::assertNull($this->token->thumbFor('not-a-token'));
    }
}
