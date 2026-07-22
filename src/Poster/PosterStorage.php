<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * Boundary over where poster image files live. Implementations are the only
 * code that touches the filesystem for posters.
 */
interface PosterStorage
{
    /**
     * All posters in a category, unsorted.
     *
     * @return list<Poster>
     */
    public function list(PosterCategory $category): array;

    public function exists(PosterCategory $category, string $filename): bool;

    /**
     * Absolute path to a poster, or null if the filename is unsafe or missing.
     */
    public function path(PosterCategory $category, string $filename): ?string;

    /**
     * Persist the file at $sourcePath under $desiredFilename, returning the
     * actual (possibly de-duplicated, sanitized) filename that was stored.
     */
    public function store(PosterCategory $category, string $desiredFilename, string $sourcePath): string;

    /**
     * Overwrite an exact filename in place (used by idempotent re-import).
     */
    public function replace(PosterCategory $category, string $filename, string $sourcePath): void;

    public function delete(PosterCategory $category, string $filename): bool;
}
