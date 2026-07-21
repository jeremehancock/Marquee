<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Config\PosterConfig;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Poster\Upload\PosterUploader;
use App\Poster\Upload\UploadException;
use App\Tests\Support\MakesImages;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;

final class PosterUploaderTest extends TestCase
{
    use MakesImages;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function storage(): FilesystemPosterStorage
    {
        return new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']);
    }

    private function config(int $maxBytes = 5_000_000): PosterConfig
    {
        return new PosterConfig(24, $maxBytes, ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function uploader(?ClientInterface $client = null, int $maxBytes = 5_000_000): PosterUploader
    {
        return new PosterUploader(
            $this->storage(),
            $this->config($maxBytes),
            $client ?? $this->createMock(ClientInterface::class),
        );
    }

    private function uploadedFile(string $bytes, string $name, string $type): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'marquee_fixture_');
        self::assertIsString($tmp);
        file_put_contents($tmp, $bytes);

        return new UploadedFile($tmp, $name, $type, strlen($bytes), UPLOAD_ERR_OK);
    }

    public function testValidFileIsStored(): void
    {
        $uploader = $this->uploader();

        $stored = $uploader->fromUploadedFile(
            PosterCategory::Movies,
            $this->uploadedFile($this->pngBytes(), 'Poster.png', 'image/png'),
        );

        self::assertSame('Poster.png', $stored);
        self::assertTrue($this->storage()->exists(PosterCategory::Movies, 'Poster.png'));
    }

    public function testDisallowedTypeIsRejected(): void
    {
        $uploader = $this->uploader();

        $this->expectException(UploadException::class);
        $uploader->fromUploadedFile(
            PosterCategory::Movies,
            $this->uploadedFile('not an image', 'note.txt', 'text/plain'),
        );
    }

    public function testOversizedFileIsRejected(): void
    {
        $uploader = $this->uploader(maxBytes: 10);

        $this->expectException(UploadException::class);
        $uploader->fromUploadedFile(
            PosterCategory::Movies,
            $this->uploadedFile($this->pngBytes(), 'Big.png', 'image/png'),
        );
    }

    public function testCollidingNameIsMadeUnique(): void
    {
        $uploader = $this->uploader();

        $first = $uploader->fromUploadedFile(
            PosterCategory::Movies,
            $this->uploadedFile($this->pngBytes(), 'Dupe.png', 'image/png'),
        );
        $second = $uploader->fromUploadedFile(
            PosterCategory::Movies,
            $this->uploadedFile($this->pngBytes(), 'Dupe.png', 'image/png'),
        );

        self::assertSame('Dupe.png', $first);
        self::assertSame('Dupe-1.png', $second);
    }

    public function testValidImageUrlIsStored(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willReturn(new Response(200, [], $this->pngBytes()));

        $stored = $this->uploader($client)->fromUrl(PosterCategory::Movies, 'https://example.com/art.png');

        self::assertTrue($this->storage()->exists(PosterCategory::Movies, $stored));
    }

    public function testNonImageUrlIsRejected(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willReturn(new Response(200, [], 'this is not an image'));

        $this->expectException(UploadException::class);
        $this->uploader($client)->fromUrl(PosterCategory::Movies, 'https://example.com/x.png');
    }

    public function testInvalidUrlIsRejected(): void
    {
        $this->expectException(UploadException::class);
        $this->uploader()->fromUrl(PosterCategory::Movies, 'not-a-url');
    }
}
