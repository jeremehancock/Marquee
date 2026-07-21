<?php

declare(strict_types=1);

namespace App\Poster\Upload;

use RuntimeException;

/**
 * Raised when an upload cannot be accepted. The message is safe to show users.
 */
final class UploadException extends RuntimeException
{
    public static function failed(): self
    {
        return new self('The file could not be uploaded. Please try again.');
    }

    public static function tooLarge(int $maxBytes): self
    {
        $maxMb = round($maxBytes / 1_048_576, 1);

        return new self(sprintf('That file is too large. The maximum size is %s MB.', $maxMb));
    }

    public static function notAnImage(): self
    {
        return new self('That file is not a supported image (JPG, PNG, or WebP).');
    }

    public static function invalidUrl(): self
    {
        return new self('Please enter a valid http(s) image URL.');
    }

    public static function fetchFailed(): self
    {
        return new self('The image could not be downloaded from that URL.');
    }
}
