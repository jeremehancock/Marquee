<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Config\PlexConfig;
use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\Export\ExportException;
use App\Plex\Export\PlexExportService;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Tests\Support\FakePlexPosterWriter;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;

final class PlexExportServiceTest extends TestCase
{
    use MakesImages;

    private string $dir;
    private PlexItemRepository $items;
    private FakePlexPosterWriter $writer;

    protected function setUp(): void
    {
        $this->dir = $this->makeTempDir();
        mkdir($this->dir . '/movies');
        $this->items = new PlexItemRepository(new Database(':memory:'));
        $this->writer = new FakePlexPosterWriter();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function service(bool $removeOverlay = false): PlexExportService
    {
        return new PlexExportService(
            $this->writer,
            new FilesystemPosterStorage($this->dir, ['jpg', 'jpeg', 'png', 'webp']),
            $this->items,
            new PlexConfig('http://plex:32400', 'token', 10, 60, removeOverlayLabel: $removeOverlay),
        );
    }

    private function link(string $filename, string $ratingKey = '10', string $sectionKey = '5'): void
    {
        $this->writePng($this->dir . '/movies/' . $filename);
        $this->items->upsert(
            new PlexItemRecord('' . $ratingKey, 'movie', 'movies', 'Movies', 'Solaris', $filename, time(), $sectionKey),
        );
    }

    public function testUploadsAndLocksLinkedPoster(): void
    {
        $this->link('Solaris.jpg');

        $this->service()->sendToPlex(PosterCategory::Movies, 'Solaris.jpg');

        self::assertSame(['10'], $this->writer->uploaded);
        self::assertSame(['10'], $this->writer->locked);
        self::assertSame([], $this->writer->labelRemovals);
    }

    public function testRemovesOverlayLabelWhenEnabled(): void
    {
        $this->link('Solaris.jpg');

        $this->service(removeOverlay: true)->sendToPlex(PosterCategory::Movies, 'Solaris.jpg');

        self::assertCount(1, $this->writer->labelRemovals);
        self::assertSame(['section' => '5', 'type' => 1, 'rating' => '10'], $this->writer->labelRemovals[0]);
    }

    public function testUnlinkedPosterThrows(): void
    {
        $this->writePng($this->dir . '/movies/Manual.jpg');

        $this->expectException(ExportException::class);
        $this->service()->sendToPlex(PosterCategory::Movies, 'Manual.jpg');
    }

    public function testMissingFileThrows(): void
    {
        $this->items->upsert(new PlexItemRecord('10', 'movie', 'movies', 'Movies', 'Ghost', 'Ghost.jpg', time(), '5'));

        $this->expectException(ExportException::class);
        $this->service()->sendToPlex(PosterCategory::Movies, 'Ghost.jpg');
    }
}
