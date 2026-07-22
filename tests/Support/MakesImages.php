<?php

declare(strict_types=1);

namespace App\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Helpers for creating real image fixtures and temp directories in tests.
 */
trait MakesImages
{
    protected function pngBytes(int $width = 2, int $height = 3): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes === false ? '' : $bytes;
    }

    protected function writePng(string $path): void
    {
        file_put_contents($path, $this->pngBytes());
    }

    protected function makeTempDir(string $prefix = 'marquee_test_'): string
    {
        $dir = sys_get_temp_dir() . '/' . uniqid($prefix, true);
        mkdir($dir, 0o777, true);

        return $dir;
    }

    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $path = (string) $item;
            is_dir($path) ? rmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
