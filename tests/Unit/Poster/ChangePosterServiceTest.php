<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\Export\PlexExportService;
use App\Poster\Edit\ChangePosterService;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Poster\SortOrder;
use App\Poster\Upload\UploadException;
use App\Tests\Support\FakePlexClient;
use App\Tests\Support\FakePlexPosterWriter;
use App\Tests\Support\MakesImages;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;

final class ChangePosterServiceTest extends TestCase
{
    use MakesImages;

    private const EXTS = ['jpg', 'jpeg', 'png', 'webp'];

    private string $dir;
    private PlexItemRepository $items;
    private FilesystemPosterStorage $storage;
    private FakePlexPosterWriter $writer;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
        mkdir($this->dir . '/movies');
        $this->storage = new FilesystemPosterStorage($this->dir, self::EXTS);
        $database = new Database(':memory:');
        $this->items = new PlexItemRepository($database);
        $this->writer = new FakePlexPosterWriter();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function service(ClientInterface $http): ChangePosterService
    {
        $plexConfig = new PlexConfig('http://plex:32400', 'token', 10, 60);
        $export = new PlexExportService($this->writer, $this->storage, $this->items, $plexConfig);

        return new ChangePosterService(
            $this->storage,
            new PosterConfig(24, 5_000_000, self::EXTS, true, SortOrder::Alphabetical),
            $this->items,
            new FakePlexClient(),
            $export,
            $plexConfig,
            $http,
        );
    }

    private function httpReturning(string $bytes): ClientInterface
    {
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], $bytes));

        return $http;
    }

    private function seedFile(string $filename, string $bytes): void
    {
        file_put_contents($this->dir . '/movies/' . $filename, $bytes);
    }

    private function link(string $filename, string $ratingKey = '10'): void
    {
        $this->items->upsert(new PlexItemRecord($ratingKey, 'movie', 'movies', 'Movies', 'Solaris', $filename, time(), '1'));
    }

    public function testChangeFromUrlReplacesAndPushes(): void
    {
        $this->seedFile('Solaris.jpg', $this->pngBytes(5, 5));
        $this->link('Solaris.jpg');
        $newBytes = $this->pngBytes(2, 3);

        $pushed = $this->service($this->httpReturning($newBytes))
            ->changeFromUrl(PosterCategory::Movies, 'Solaris.jpg', 'https://example.com/p.png');

        self::assertTrue($pushed);
        self::assertSame($newBytes, file_get_contents($this->dir . '/movies/Solaris.jpg'));
        self::assertSame(['10'], $this->writer->uploaded);
        self::assertSame(['10'], $this->writer->locked);
    }

    public function testChangeFromUploadedFileReplacesAndPushes(): void
    {
        $this->seedFile('Solaris.jpg', $this->pngBytes(5, 5));
        $this->link('Solaris.jpg');

        $tmp = tempnam(sys_get_temp_dir(), 'up_');
        self::assertIsString($tmp);
        file_put_contents($tmp, $this->pngBytes(2, 3));
        $file = new UploadedFile($tmp, 'new.png', 'image/png', (int) filesize($tmp), UPLOAD_ERR_OK);

        $pushed = $this->service($this->createMock(ClientInterface::class))
            ->changeFromUploadedFile(PosterCategory::Movies, 'Solaris.jpg', $file);

        self::assertTrue($pushed);
        self::assertSame(['10'], $this->writer->uploaded);
    }

    public function testUnlinkedChangeDoesNotPush(): void
    {
        $this->seedFile('Manual.jpg', $this->pngBytes(5, 5));

        $pushed = $this->service($this->httpReturning($this->pngBytes()))
            ->changeFromUrl(PosterCategory::Movies, 'Manual.jpg', 'https://example.com/p.png');

        self::assertFalse($pushed);
        self::assertSame([], $this->writer->uploaded);
    }

    public function testInvalidImageIsRejected(): void
    {
        $this->seedFile('Solaris.jpg', $this->pngBytes());
        $original = file_get_contents($this->dir . '/movies/Solaris.jpg');

        try {
            $this->service($this->httpReturning('this is not an image'))
                ->changeFromUrl(PosterCategory::Movies, 'Solaris.jpg', 'https://example.com/x');
            self::fail('Expected UploadException');
        } catch (UploadException) {
            self::assertSame($original, file_get_contents($this->dir . '/movies/Solaris.jpg'));
        }
    }

    public function testSendToPlexPushesStoredPosterWithoutChangingIt(): void
    {
        $stored = $this->pngBytes(5, 5);
        $this->seedFile('Solaris.jpg', $stored);
        $this->link('Solaris.jpg');

        $this->service($this->createMock(ClientInterface::class))
            ->sendToPlex(PosterCategory::Movies, 'Solaris.jpg');

        self::assertSame(['10'], $this->writer->uploaded);
        self::assertSame(['10'], $this->writer->locked);
        // The local poster is untouched — send-to-Plex is a one-way push.
        self::assertSame($stored, file_get_contents($this->dir . '/movies/Solaris.jpg'));
    }

    public function testFetchFromPlexReplacesLocal(): void
    {
        $this->seedFile('Solaris.jpg', $this->pngBytes(5, 5));
        $this->link('Solaris.jpg');

        $this->service($this->createMock(ClientInterface::class))
            ->fetchFromPlex(PosterCategory::Movies, 'Solaris.jpg');

        // Replaced with the fake Plex client's 2x3 png.
        self::assertSame($this->pngBytes(2, 3), file_get_contents($this->dir . '/movies/Solaris.jpg'));
        self::assertSame([], $this->writer->uploaded);
    }
}
