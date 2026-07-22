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
     * Lock the item's poster field so a metadata refresh keeps it.
     */
    public function lockPoster(string $ratingKey): void;

    /**
     * Remove the Kometa "Overlay" label from the item.
     */
    public function removeOverlayLabel(string $sectionKey, int $plexType, string $ratingKey): void;
}
