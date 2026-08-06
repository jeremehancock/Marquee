<?php

declare(strict_types=1);

namespace App\Plex;

use App\Config\PlexConfig;
use Throwable;

/**
 * Turns a Plex failure into the sentence a user reads, with the remedy that
 * matches how this install is actually connected.
 *
 * This is why the application needs no live connection indicator. Every place a
 * Plex operation can fail — sending a poster, fetching one, importing, orphan
 * detection — already surfaces the failure at the moment it happens, so making
 * that message source-aware puts the right advice everywhere, for free, and
 * without a status light asserting a reachability nobody checked.
 */
final class PlexFailureMessage
{
    public function __construct(private readonly PlexConfig $config)
    {
    }

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
            PlexFailure::NotConfigured => 'Connect Marquee to Plex on the Import page.',
            PlexFailure::AuthRejected => $this->config->isSignedIn()
                ? 'Your Plex sign-in was rejected — sign in to Plex again on the Import page.'
                : 'Check PLEX_TOKEN.',
            PlexFailure::ConnectionFailed => 'Check PLEX_SERVER_URL and that the Plex server is running.',
            // These two describe a situation rather than a misconfiguration:
            // the item is gone, or the server said something unusable. Neither
            // has an action that depends on how Marquee is connected.
            PlexFailure::ItemMissing, PlexFailure::UnexpectedResponse => '',
        };
    }
}
