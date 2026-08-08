<?php

declare(strict_types=1);

namespace App\Plex;

use Throwable;

/**
 * Turns a Plex failure into the sentence a user reads, remedy included.
 *
 * The remedy lives here rather than in the exception so that user-facing copy
 * stays out of a value object, and so the scheduled auto-import's log says the
 * same thing the interface does. With one way to connect the advice no longer
 * varies — but it must no longer name `PLEX_TOKEN` either, which is a variable
 * the application stopped reading.
 */
final class PlexFailureMessage
{
    /**
     * The full message for a failure, including its remedy where one helps.
     *
     * Anything that is not a Plex failure is passed through untouched — an
     * upload or export problem already reads as its own advice.
     */
    public function for(Throwable $e): string
    {
        if (!$e instanceof PlexException) {
            return $e->getMessage();
        }

        $remedy = $this->remedy($e->reason);

        return $remedy === '' ? $e->getMessage() : $e->getMessage() . ' ' . $remedy;
    }

    private function remedy(PlexFailure $reason): string
    {
        return match ($reason) {
            PlexFailure::NotConfigured => 'Sign in to Plex to continue.',
            PlexFailure::AuthRejected => 'Your Plex sign-in was rejected — sign in to Plex again.',
            PlexFailure::ConnectionFailed => 'Check PLEX_SERVER_URL and that the Plex server is running.',
            // These two describe a situation rather than a misconfiguration:
            // the item is gone, or the server said something unusable. Neither
            // has an action to do with the connection.
            PlexFailure::ItemMissing, PlexFailure::UnexpectedResponse => '',
        };
    }
}
