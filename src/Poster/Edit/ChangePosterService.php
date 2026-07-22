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
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Replaces a poster in place from a file or URL and, when the poster is linked
 * to Plex, pushes the new image to Plex and locks it. Also re-pulls a poster
 * from Plex.
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
        private readonly ClientInterface $http,
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
        return $this->replaceAndPush($category, $filename, $this->bytesToTempFile($this->fetchUrl($url)));
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

    private function fetchUrl(string $url): string
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('#^https?://#i', $url) !== 1) {
            throw UploadException::invalidUrl();
        }

        try {
            $response = $this->http->request('GET', $url, [
                'timeout' => 20,
                'connect_timeout' => 10,
                'http_errors' => true,
            ]);
            $bytes = (string) $response->getBody();
        } catch (Throwable) {
            throw UploadException::fetchFailed();
        }

        if ($bytes === '') {
            throw UploadException::fetchFailed();
        }
        if (strlen($bytes) > $this->config->maxFileSize) {
            throw UploadException::tooLarge($this->config->maxFileSize);
        }

        return $bytes;
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
