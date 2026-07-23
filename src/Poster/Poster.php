<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * An immutable view of one poster image file on disk.
 */
final class Poster
{
    public function __construct(
        public readonly PosterCategory $category,
        public readonly string $filename,
        public readonly int $size,
        public readonly int $modifiedAt,
    ) {
    }

    /**
     * Human-friendly title derived from the filename: the extension is dropped
     * and separators become spaces.
     */
    public function title(): string
    {
        $base = pathinfo($this->filename, PATHINFO_FILENAME);

        return trim(preg_replace('/[._]+/', ' ', $base) ?? $base);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
    }

    /**
     * The app URL that serves this image.
     *
     * The `v` parameter is the file's modification time. Replacing a poster
     * keeps its filename, so without it the URL would not change and a browser
     * would keep serving the previous image from cache. It exists only to move
     * the cache key: the request handler routes on the path and never reads it,
     * so a URL with a stale or missing `v` still serves the current file.
     */
    public function url(): string
    {
        $path = '/posters/' . $this->category->value . '/' . rawurlencode($this->filename);

        // filemtime() failures surface as 0; a constant would bust nothing, so
        // leave the parameter off rather than imply a version we do not have.
        return $this->modifiedAt > 0 ? $path . '?v=' . $this->modifiedAt : $path;
    }

    /**
     * Sort key with a leading article removed for article-aware ordering.
     */
    public function sortKey(bool $ignoreArticles): string
    {
        $title = mb_strtolower($this->title());
        if ($ignoreArticles) {
            $title = preg_replace('/^(a|an|the)\s+/', '', $title) ?? $title;
        }

        return $title;
    }
}
