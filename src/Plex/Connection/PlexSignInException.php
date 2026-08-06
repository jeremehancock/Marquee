<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use RuntimeException;
use Throwable;

/**
 * Raised when the sign-in conversation with plex.tv fails. Messages are safe to
 * show; none of them carries any part of a response body, because the body of a
 * successful authorization response contains the token itself.
 */
final class PlexSignInException extends RuntimeException
{
    private function __construct(string $message, public readonly bool $expired, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The authorization request no longer exists. Plex discards a pin once it
     * has been exchanged or has aged out, so this is an ordinary outcome for a
     * sign-in the user walked away from.
     */
    public static function expired(?Throwable $previous = null): self
    {
        return new self('The Plex sign-in request expired before it was approved.', true, $previous);
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self('Could not reach Plex to sign in. Check the server has internet access.', false, $previous);
    }

    public static function unexpectedResponse(): self
    {
        return new self('Plex returned an unexpected response while signing in.', false);
    }
}
