<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Config\PlexConfig;
use App\Config\PlexTokenSource;
use App\Plex\PlexException;
use App\Plex\PlexFailureMessage;
use App\Poster\Upload\UploadException;
use PHPUnit\Framework\TestCase;

final class PlexFailureMessageTest extends TestCase
{
    public function testRejectedCredentialWhileSignedInOffersSigningInAgain(): void
    {
        $message = $this->signedIn()->for(PlexException::authFailed());

        self::assertStringContainsString('sign in to Plex again', $message);
        // The whole point: never send a signed-in user to a variable they do
        // not have.
        self::assertStringNotContainsString('PLEX_TOKEN', $message);
    }

    public function testRejectedCredentialFromTheEnvironmentNamesTheVariable(): void
    {
        $message = $this->usingEnvironment()->for(PlexException::authFailed());

        self::assertStringContainsString('Check PLEX_TOKEN.', $message);
        self::assertStringNotContainsString('sign in', $message);
    }

    public function testNotConnectedPointsAtTheConnectionPanel(): void
    {
        foreach ([$this->signedIn(), $this->usingEnvironment(), $this->notConnected()] as $presenter) {
            $message = $presenter->for(PlexException::notConfigured());

            self::assertStringContainsString('Connect Marquee to Plex', $message);
            self::assertStringNotContainsString('PLEX_TOKEN', $message);
        }
    }

    public function testConnectionFailureNamesTheServerAddressWhicheverSourceIsInUse(): void
    {
        foreach ([$this->signedIn(), $this->usingEnvironment()] as $presenter) {
            $message = $presenter->for(PlexException::connectionFailed());

            self::assertStringContainsString('Could not connect to the Plex server.', $message);
            self::assertStringContainsString('PLEX_SERVER_URL', $message);
        }
    }

    public function testSituationalFailuresGetNoRemedy(): void
    {
        // These describe what happened, not a misconfiguration, so appending
        // connection advice would only mislead.
        foreach ([$this->signedIn(), $this->usingEnvironment()] as $presenter) {
            $missing = $presenter->for(PlexException::itemNotFound());
            self::assertSame(PlexException::itemNotFound()->getMessage(), $missing);

            $unexpected = $presenter->for(PlexException::unexpectedResponse());
            self::assertSame(PlexException::unexpectedResponse()->getMessage(), $unexpected);
        }
    }

    public function testNonPlexFailuresPassThroughUnchanged(): void
    {
        $e = new UploadException('That file is not an image.');

        self::assertSame('That file is not an image.', $this->signedIn()->for($e));
    }

    public function testNoMessageNamesPlexTokenWhileSignedIn(): void
    {
        $signedIn = $this->signedIn();

        foreach ([
            PlexException::notConfigured(),
            PlexException::authFailed(),
            PlexException::connectionFailed(),
            PlexException::itemNotFound(),
            PlexException::unexpectedResponse(),
        ] as $failure) {
            self::assertStringNotContainsString('PLEX_TOKEN', $signedIn->for($failure));
        }
    }

    private function signedIn(): PlexFailureMessage
    {
        return new PlexFailureMessage(
            new PlexConfig('http://plex:32400', 'token', 10, 60, tokenSource: PlexTokenSource::Stored),
        );
    }

    private function usingEnvironment(): PlexFailureMessage
    {
        return new PlexFailureMessage(
            new PlexConfig('http://plex:32400', 'token', 10, 60, tokenSource: PlexTokenSource::Environment),
        );
    }

    private function notConnected(): PlexFailureMessage
    {
        return new PlexFailureMessage(new PlexConfig('', '', 10, 60, tokenSource: PlexTokenSource::None));
    }
}
