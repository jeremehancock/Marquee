<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Plex\PlexException;
use App\Plex\PlexFailureMessage;
use App\Poster\Upload\UploadException;
use PHPUnit\Framework\TestCase;

final class PlexFailureMessageTest extends TestCase
{
    private PlexFailureMessage $message;

    protected function setUp(): void
    {
        $this->message = new PlexFailureMessage();
    }

    public function testRejectedCredentialOffersSigningInAgain(): void
    {
        $message = $this->message->for(PlexException::authFailed());

        self::assertStringContainsString('sign in to Plex again', $message);
    }

    public function testNotConnectedPointsAtConnecting(): void
    {
        self::assertStringContainsString(
            'Sign in to Plex',
            $this->message->for(PlexException::notConfigured()),
        );
    }

    public function testConnectionFailureNamesTheServerAddress(): void
    {
        $message = $this->message->for(PlexException::connectionFailed());

        self::assertStringContainsString('Could not connect to the Plex server.', $message);
        self::assertStringContainsString('PLEX_SERVER_URL', $message);
    }

    public function testSituationalFailuresGetNoRemedy(): void
    {
        // These describe what happened, not a misconfiguration, so appending
        // connection advice would only mislead.
        self::assertSame(
            PlexException::itemNotFound()->getMessage(),
            $this->message->for(PlexException::itemNotFound()),
        );
        self::assertSame(
            PlexException::unexpectedResponse()->getMessage(),
            $this->message->for(PlexException::unexpectedResponse()),
        );
    }

    public function testNonPlexFailuresPassThroughUnchanged(): void
    {
        $e = new UploadException('That file is not an image.');

        self::assertSame('That file is not an image.', $this->message->for($e));
    }

    public function testNoMessageNamesTheObsoleteVariable(): void
    {
        // PLEX_TOKEN is no longer read as a credential, so advising anyone to
        // check it would send them to a setting that does nothing.
        foreach ([
            PlexException::notConfigured(),
            PlexException::authFailed(),
            PlexException::connectionFailed(),
            PlexException::itemNotFound(),
            PlexException::unexpectedResponse(),
        ] as $failure) {
            self::assertStringNotContainsString('PLEX_TOKEN', $this->message->for($failure));
        }
    }
}
