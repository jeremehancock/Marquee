<?php

declare(strict_types=1);

namespace App\Plex\Export;

use RuntimeException;

/**
 * Raised when a poster cannot be sent to Plex. Messages are safe to show.
 */
final class ExportException extends RuntimeException
{
    public static function notLinked(): self
    {
        return new self('This poster is not linked to a Plex item, so it cannot be sent to Plex.');
    }

    public static function missingFile(): self
    {
        return new self('The poster file could not be read.');
    }
}
