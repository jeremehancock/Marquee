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
     */
    public function url(): string
    {
        return '/posters/' . $this->category->value . '/' . rawurlencode($this->filename);
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
