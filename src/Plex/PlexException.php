<?php

declare(strict_types=1);

namespace App\Plex;

use RuntimeException;
use Throwable;

/**
 * Raised when Plex is unconfigured or a request fails.
 *
 * The message states what happened and nothing about how to fix it. The remedy
 * depends on which connection source is in use — a signed-in install and one
 * configured by `PLEX_TOKEN` need different advice — and that is presentation's
 * to decide. `PlexFailureMessage` turns the reason carried here into the
 * sentence a user reads.
 */
final class PlexException extends RuntimeException
{
    private function __construct(string $message, public readonly PlexFailure $reason, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('Marquee is not connected to Plex.', PlexFailure::NotConfigured);
    }

    public static function connectionFailed(?Throwable $previous = null): self
    {
        return new self('Could not connect to the Plex server.', PlexFailure::ConnectionFailed, $previous);
    }

    public static function itemNotFound(?Throwable $previous = null): self
    {
        return new self(
            'This item no longer exists in Plex, so the poster may be orphaned. Check the Orphans page.',
            PlexFailure::ItemMissing,
            $previous,
        );
    }

    public static function authFailed(?Throwable $previous = null): self
    {
        return new self('The Plex server rejected the credential.', PlexFailure::AuthRejected, $previous);
    }

    public static function unexpectedResponse(): self
    {
        return new self('The Plex server returned an unexpected response.', PlexFailure::UnexpectedResponse);
    }
}
