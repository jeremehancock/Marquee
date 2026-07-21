<?php

declare(strict_types=1);

namespace App\Poster;

use RuntimeException;

/**
 * Stores posters as image files under {baseDir}/{category}. This is the only
 * class that knows about directories, extensions, and filename safety.
 */
final class FilesystemPosterStorage implements PosterStorage
{
    /**
     * @param list<string> $allowedExtensions lowercase, without the dot
     */
    public function __construct(
        private readonly string $baseDir,
        private readonly array $allowedExtensions,
    ) {
    }

    public function list(PosterCategory $category): array
    {
        $dir = $this->categoryDir($category);
        if (!is_dir($dir)) {
            return [];
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return [];
        }

        $posters = [];
        foreach ($entries as $entry) {
            $path = $dir . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowedExtensions, true)) {
                continue;
            }
            $posters[] = new Poster(
                category: $category,
                filename: $entry,
                size: (int) (filesize($path) ?: 0),
                modifiedAt: (int) (filemtime($path) ?: 0),
            );
        }

        return $posters;
    }

    public function exists(PosterCategory $category, string $filename): bool
    {
        return $this->path($category, $filename) !== null;
    }

    public function path(PosterCategory $category, string $filename): ?string
    {
        if (!$this->isSafeFilename($filename)) {
            return null;
        }
        $path = $this->categoryDir($category) . '/' . $filename;

        return is_file($path) ? $path : null;
    }

    public function store(PosterCategory $category, string $desiredFilename, string $sourcePath): string
    {
        $dir = $this->categoryDir($category);
        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Could not create category directory: %s', $dir));
        }

        $filename = $this->uniqueFilename($category, $this->sanitizeFilename($desiredFilename));
        $target = $dir . '/' . $filename;

        if (!$this->moveFile($sourcePath, $target)) {
            throw new RuntimeException('Could not store the poster file.');
        }

        return $filename;
    }

    public function delete(PosterCategory $category, string $filename): bool
    {
        $path = $this->path($category, $filename);

        return $path !== null && unlink($path);
    }

    private function categoryDir(PosterCategory $category): string
    {
        return $this->baseDir . '/' . $category->directory();
    }

    private function isSafeFilename(string $filename): bool
    {
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return false;
        }
        if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, "\0")) {
            return false;
        }

        return basename($filename) === $filename;
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions, true)) {
            $extension = $this->allowedExtensions[0] ?? 'jpg';
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($filename, PATHINFO_FILENAME)) ?? '';
        $name = trim($name, '._-');
        if ($name === '') {
            $name = 'poster';
        }

        return $name . '.' . $extension;
    }

    private function uniqueFilename(PosterCategory $category, string $filename): string
    {
        $dir = $this->categoryDir($category);
        if (!is_file($dir . '/' . $filename)) {
            return $filename;
        }

        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 1;
        do {
            $candidate = sprintf('%s-%d.%s', $name, $counter, $extension);
            $counter++;
        } while (is_file($dir . '/' . $candidate));

        return $candidate;
    }

    private function moveFile(string $source, string $target): bool
    {
        if (is_uploaded_file($source)) {
            return move_uploaded_file($source, $target);
        }

        return rename($source, $target);
    }
}
