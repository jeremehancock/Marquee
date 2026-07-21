<?php

declare(strict_types=1);

namespace App\Plex;

use RuntimeException;
use Throwable;

/**
 * Raised when Plex is unconfigured or a request fails. Messages are safe to show.
 */
final class PlexException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Plex is not configured. Set PLEX_SERVER_URL and PLEX_TOKEN.');
    }

    public static function connectionFailed(?Throwable $previous = null): self
    {
        return new self('Could not connect to the Plex server. Check the URL and token.', 0, $previous);
    }

    public static function unexpectedResponse(): self
    {
        return new self('The Plex server returned an unexpected response.');
    }
}
