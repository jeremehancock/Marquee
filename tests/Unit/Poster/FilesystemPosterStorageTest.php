<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterCategory;
use App\Tests\Support\MakesImages;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function testRenameSanitizesAndReturnsTheNameItSettledOn(): void
    {
        $dir = $this->makeTempDir();
        try {
            $storage = new FilesystemPosterStorage($dir . '/posters', self::EXTS);
            $source = $dir . '/incoming.png';
            $this->writePng($source);
            $stored = $storage->store(PosterCategory::Movies, 'Solaris.png', $source);

            $renamed = $storage->rename(PosterCategory::Movies, $stored, "Marvel's Agents [Movies].png");

            self::assertSame('Marvel_s_Agents_Movies.png', $renamed);
            self::assertTrue($storage->exists(PosterCategory::Movies, $renamed));
            self::assertFalse($storage->exists(PosterCategory::Movies, $stored));
            self::assertCount(1, $storage->list(PosterCategory::Movies));
        } finally {
            $this->removeDir($dir);
        }
    }

    /**
     * An unchanged name must not drift to a "-1" suffix. Import calls this for
     * every mapped poster on every run, so a no-op has to stay a no-op.
     */
    public function testRenameToTheSameNameIsANoOp(): void
    {
        $dir = $this->makeTempDir();
        try {
            $storage = new FilesystemPosterStorage($dir . '/posters', self::EXTS);
            $source = $dir . '/incoming.png';
            $this->writePng($source);
            $stored = $storage->store(PosterCategory::Movies, 'Solaris.png', $source);

            self::assertSame($stored, $storage->rename(PosterCategory::Movies, $stored, 'Solaris.png'));
            self::assertCount(1, $storage->list(PosterCategory::Movies));
        } finally {
            $this->removeDir($dir);
        }
    }

    /**
     * Recasing a title must not make the file collide with itself. On a
     * case-insensitive filesystem the target name already "exists" — it is the
     * same file — and suffixing it would be wrong.
     */
    public function testRenameThatOnlyChangesCaseDoesNotSuffix(): void
    {
        $dir = $this->makeTempDir();
        try {
            $storage = new FilesystemPosterStorage($dir . '/posters', self::EXTS);
            $source = $dir . '/incoming.png';
            $this->writePng($source);
            $stored = $storage->store(PosterCategory::Movies, 'solaris.png', $source);

            $renamed = $storage->rename(PosterCategory::Movies, $stored, 'Solaris.png');

            self::assertSame('Solaris.png', $renamed);
            self::assertCount(1, $storage->list(PosterCategory::Movies));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testRenamingAPosterThatIsNotStoredFails(): void
    {
        $dir = $this->makeTempDir();
        try {
            $storage = new FilesystemPosterStorage($dir . '/posters', self::EXTS);

            $this->expectException(RuntimeException::class);
            $storage->rename(PosterCategory::Movies, 'Missing.png', 'Solaris.png');
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testRenamingRejectsAnUnsafeCurrentName(): void
    {
        $dir = $this->makeTempDir();
        try {
            $storage = new FilesystemPosterStorage($dir . '/posters', self::EXTS);

            $this->expectException(RuntimeException::class);
            $storage->rename(PosterCategory::Movies, '../escape.png', 'Solaris.png');
        } finally {
            $this->removeDir($dir);
        }
    }
}
