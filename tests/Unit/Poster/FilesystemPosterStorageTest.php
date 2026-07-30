<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;

/**
 * Deleting the posters directory is a documented way to start over (see the
 * README FAQ). These cover what has to hold for that to be safe: a missing
 * directory reads as empty, and the next import puts it back.
 */
final class FilesystemPosterStorageTest extends TestCase
{
    use MakesImages;

    private const EXTS = ['jpg', 'jpeg', 'png', 'webp'];

    public function testAbsentCategoryDirectoryReadsAsEmptyRatherThanFailing(): void
    {
        $dir = $this->makeTempDir();
        try {
            $storage = new FilesystemPosterStorage($dir . '/posters', self::EXTS);

            foreach (PosterCategory::cases() as $category) {
                self::assertSame([], $storage->list($category));
                self::assertFalse($storage->exists($category, 'Solaris.jpg'));
            }
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testStoringRecreatesTheAbsentCategoryDirectory(): void
    {
        $dir = $this->makeTempDir();
        try {
            $base = $dir . '/posters';
            $storage = new FilesystemPosterStorage($base, self::EXTS);
            self::assertDirectoryDoesNotExist($base);

            $source = $dir . '/incoming.png';
            $this->writePng($source);

            $filename = $storage->store(PosterCategory::Movies, 'Solaris.png', $source);

            self::assertDirectoryExists($base . '/' . PosterCategory::Movies->directory());
            self::assertTrue($storage->exists(PosterCategory::Movies, $filename));
            self::assertCount(1, $storage->list(PosterCategory::Movies));
        } finally {
            $this->removeDir($dir);
        }
    }
}
