<?php

declare(strict_types=1);

namespace App\Poster\Edit;

use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Database\PlexItemRepository;
use App\Plex\Export\ExportException;
use App\Plex\Export\PlexExportService;
use App\Plex\PlexClient;
use App\Poster\PosterCategory;
use App\Poster\PosterStorage;
use App\Poster\Upload\UploadException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Replaces a poster in place from a file or URL and, when the poster is linked
 * to Plex, pushes the new image to Plex and locks it. Also re-pulls a poster
 * from Plex.
 *
 * **Two sources of images, two levels of trust.** The Plex methods here talk to
 * the server the operator configured, which is normally at a private address —
 * that is the product working. {@see $fetcher} handles the one case where the
 * address came from whoever holds a session, and it is restricted to the public
 * internet. The distinction is why this class takes a fetcher rather than an
 * HTTP client: there is no unguarded client here to reach for by mistake.
 */
final class ChangePosterService
{
    public function __construct(
        private readonly PosterStorage $storage,
        private readonly PosterConfig $config,
        private readonly PlexItemRepository $items,
        private readonly PlexClient $plex,
        private readonly PlexExportService $export,
        private readonly PlexConfig $plexConfig,
        private readonly PosterUrlFetcher $fetcher,
    ) {
    }

    /**
     * @return bool whether the change was pushed to Plex
     */
    public function changeFromUploadedFile(PosterCategory $category, string $filename, UploadedFileInterface $file): bool
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw UploadException::failed();
        }
        $size = $file->getSize();
        if ($size !== null && $size > $this->config->maxFileSize) {
            throw UploadException::tooLarge($this->config->maxFileSize);
        }

        return $this->replaceAndPush($category, $filename, $this->streamToTempFile($file->getStream()));
    }

    /**
     * @return bool whether the change was pushed to Plex
     */
    public function changeFromUrl(PosterCategory $category, string $filename, string $url): bool
    {
        return $this->replaceAndPush($category, $filename, $this->bytesToTempFile($this->fetcher->fetch($url)));
    }

    /**
     * Replace the poster with one the Plex server already holds for its item.
     *
     * Deliberately *not* replaceAndPush(). Every other tab supplies an image
     * Plex does not have, so uploading is the only way to give it one. This
     * poster is already there, and Plex never prunes an item's posters — so
     * uploading would leave a second, byte-identical copy behind, most absurdly
     * when the poster being applied is the one already in use. Selecting it
     * instead reaches the same end state: the item shows it, and lockPoster()
     * protects it exactly as it does an upload, because the lock is a flag on
     * the thumb field and is indifferent to how the thumb was set.
     *
     * Marquee still needs its own copy, so the bytes are fetched regardless —
     * here rather than by the browser, so applying never needs the Plex token
     * client-side and does not depend on the proxied grid image.
     *
     * The list is re-read rather than trusted from the request. The dialog can
     * sit open a long time, and the path arriving signed proves only that this
     * application minted it, never that Plex still has a poster there.
     *
     * @return bool whether the change was pushed to Plex
     */
    public function changeFromPlexPath(PosterCategory $category, string $filename, string $path): bool
    {
        $record = $this->items->findByFilename($category->value, $filename);
        if ($record === null) {
            throw ExportException::notLinked();
        }

        $candidate = $this->plex->itemPosters($record->ratingKey)->withPath($path);
        if ($candidate === null) {
            throw ExportException::posterGone();
        }

        $temp = $this->bytesToTempFile($this->plex->imageAt($candidate->path));
        try {
            if (filesize($temp) > $this->config->maxFileSize) {
                throw UploadException::tooLarge($this->config->maxFileSize);
            }
            $this->validateImage($temp);
            $this->storage->replace($category, $filename, $temp);
        } finally {
            $this->cleanup($temp);
        }

        if (!$this->plexConfig->isConfigured()) {
            return false;
        }

        $this->export->selectInPlex($category, $filename, $candidate->posterKey);

        return true;
    }

    /**
     * Pushes the poster currently stored in Marquee to its linked Plex item and
     * locks it, without changing the local poster first. Useful when Plex has
     * drifted (e.g. an agent refresh) and the user wants Marquee's copy back.
     */
    public function sendToPlex(PosterCategory $category, string $filename): void
    {
        $this->export->sendToPlex($category, $filename);
    }

    public function fetchFromPlex(PosterCategory $category, string $filename): void
    {
        $record = $this->items->findByFilename($category->value, $filename);
        if ($record === null) {
            throw ExportException::notLinked();
        }

        $temp = $this->bytesToTempFile($this->plex->itemPoster($record->ratingKey));
        try {
            $this->validateImage($temp);
            $this->storage->replace($category, $filename, $temp);
        } finally {
            $this->cleanup($temp);
        }
    }

    private function replaceAndPush(PosterCategory $category, string $filename, string $temp): bool
    {
        try {
            if (filesize($temp) > $this->config->maxFileSize) {
                throw UploadException::tooLarge($this->config->maxFileSize);
            }
            $this->validateImage($temp);
            $this->storage->replace($category, $filename, $temp);
        } finally {
            $this->cleanup($temp);
        }

        $linked = $this->items->findByFilename($category->value, $filename) !== null;
        if ($linked && $this->plexConfig->isConfigured()) {
            $this->export->sendToPlex($category, $filename);

            return true;
        }

        return false;
    }

    private function validateImage(string $path): void
    {
        $info = @getimagesize($path);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw UploadException::notAnImage();
        }
    }

    private function streamToTempFile(StreamInterface $stream): string
    {
        $temp = $this->createTempFile();
        $handle = fopen($temp, 'wb');
        if ($handle === false) {
            throw UploadException::failed();
        }
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        while (!$stream->eof()) {
            fwrite($handle, $stream->read(8192));
        }
        fclose($handle);

        return $temp;
    }

    private function bytesToTempFile(string $bytes): string
    {
        $temp = $this->createTempFile();
        if (file_put_contents($temp, $bytes) === false) {
            throw UploadException::failed();
        }

        return $temp;
    }

    private function createTempFile(): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'marquee_change_');
        if ($temp === false) {
            throw UploadException::failed();
        }

        return $temp;
    }

    private function cleanup(string $temp): void
    {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
}
