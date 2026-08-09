<?php

declare(strict_types=1);

namespace App\Plex;

/**
 * Write access to a Plex Media Server: set and lock an item's poster, and edit
 * its labels. All methods throw PlexException on failure.
 */
interface PlexPosterWriter
{
    /**
     * Upload image bytes as the item's poster (Plex selects the uploaded one).
     */
    public function uploadPoster(string $ratingKey, string $imageBytes): void;

    /**
     * Select a poster the server already holds, named by its own key from the
     * item's poster list (e.g. `upload://posters/<hash>`).
     *
     * The counterpart to uploading for a poster that is already there. Plex
     * keeps every poster an item has ever had and never prunes them, so
     * uploading one it already holds would add a second, byte-identical copy —
     * most absurdly when the poster being applied is the one already in use.
     *
     * This does not lock: locking is {@see lockPoster()}, a separate flag on the
     * thumb field that is indifferent to how the thumb was set.
     */
    public function selectPoster(string $ratingKey, string $posterKey): void;

    /**
     * Lock the item's poster field so a metadata refresh keeps it.
     */
    public function lockPoster(string $ratingKey): void;

    /**
     * Remove the Kometa "Overlay" label from the item.
     */
    public function removeOverlayLabel(string $sectionKey, int $plexType, string $ratingKey): void;
}
