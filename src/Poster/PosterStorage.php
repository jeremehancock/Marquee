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

    /**
     * Move a stored poster to $desiredFilename within the same category,
     * returning the actual (possibly de-duplicated, sanitized) filename it now
     * has — which may differ from the one requested, so callers must record
     * what comes back rather than what they asked for.
     *
     * Renaming to the name the file already has is a no-op. The stored image is
     * never read or rewritten: this moves a file, it does not replace one.
     */
    public function rename(PosterCategory $category, string $currentFilename, string $desiredFilename): string;

    public function delete(PosterCategory $category, string $filename): bool;
}
