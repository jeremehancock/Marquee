<?php

declare(strict_types=1);

namespace App\Poster\Upload;

use App\Config\PosterConfig;
use App\Poster\PosterCategory;
use App\Poster\PosterStorage;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Validates and stores posters uploaded from a file or fetched from a URL.
 * Content type is verified from the actual image bytes, not just the filename.
 */
final class PosterUploader
{
    private const IMAGE_TYPE_EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    public function __construct(
        private readonly PosterStorage $storage,
        private readonly PosterConfig $config,
        private readonly ClientInterface $httpClient,
    ) {
    }

    /**
     * @return string the stored filename
     */
    public function fromUploadedFile(PosterCategory $category, UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw UploadException::failed();
        }

        $size = $file->getSize();
        if ($size !== null && $size > $this->config->maxFileSize) {
            throw UploadException::tooLarge($this->config->maxFileSize);
        }

        $temp = $this->streamToTempFile($file->getStream());

        return $this->validateAndStore($category, $temp, $file->getClientFilename() ?? 'poster');
    }

    /**
     * @return string the stored filename
     */
    public function fromUrl(PosterCategory $category, string $url): string
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('#^https?://#i', $url) !== 1) {
            throw UploadException::invalidUrl();
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
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

        $temp = $this->bytesToTempFile($bytes);

        return $this->validateAndStore($category, $temp, $this->filenameFromUrl($url));
    }

    private function validateAndStore(PosterCategory $category, string $tempPath, string $nameHint): string
    {
        try {
            if (filesize($tempPath) > $this->config->maxFileSize) {
                throw UploadException::tooLarge($this->config->maxFileSize);
            }

            $extension = $this->detectExtension($tempPath);
            $desiredName = $this->desiredFilename($nameHint, $extension);

            return $this->storage->store($category, $desiredName, $tempPath);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function detectExtension(string $path): string
    {
        $info = @getimagesize($path);
        if ($info === false || !isset(self::IMAGE_TYPE_EXTENSIONS[$info[2]])) {
            throw UploadException::notAnImage();
        }

        $extension = self::IMAGE_TYPE_EXTENSIONS[$info[2]];
        if (!in_array($extension, $this->config->allowedExtensions, true)) {
            throw UploadException::notAnImage();
        }

        return $extension;
    }

    private function desiredFilename(string $nameHint, string $extension): string
    {
        $base = pathinfo($nameHint, PATHINFO_FILENAME);
        if (trim($base) === '') {
            $base = 'poster';
        }

        return $base . '.' . $extension;
    }

    private function filenameFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $base = pathinfo($path, PATHINFO_FILENAME);

        return $base !== '' ? $base : 'poster';
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
        $temp = tempnam(sys_get_temp_dir(), 'marquee_upload_');
        if ($temp === false) {
            throw UploadException::failed();
        }

        return $temp;
    }
}
