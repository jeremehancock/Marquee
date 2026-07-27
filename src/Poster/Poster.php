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

    /**
     * Title for the gallery caption. Plex import appends the library name to the
     * filename to keep names unique across libraries, so it surfaces as a
     * trailing token in title() (e.g. "…2003 Movies"). Given that library name
     * this drops the token, because the type is already conveyed by the All-view
     * badge and the active tab. Non-Plex posters (or an unrecognised token) pass
     * null and keep their full title. title() itself is untouched, so sorting is
     * unaffected.
     */
    public function captionTitle(?string $libraryTitle = null): string
    {
        $title = $this->title();
        $token = $this->trailingLibraryToken($title, $libraryTitle);

        return $token === null ? $title : rtrim(mb_substr($title, 0, -mb_strlen($token)));
    }

    /**
     * Title for the mobile action sheet: like {@see captionTitle()} but the
     * library is kept and shown in parentheses (e.g. "…2003 (Movies)") rather
     * than dropped, so a tapped poster names both its title and its library.
     */
    public function sheetTitle(?string $libraryTitle = null): string
    {
        $title = $this->title();
        $token = $this->trailingLibraryToken($title, $libraryTitle);
        if ($token === null) {
            return $title;
        }

        return rtrim(mb_substr($title, 0, -mb_strlen($token))) . ' (' . $token . ')';
    }

    /**
     * The library name as it appears at the end of $title, or null if $title
     * does not end with it. The stored library name went through the same
     * filename sanitising and title() rendering as the rest of the name, so it
     * is normalised the same way before matching.
     */
    private function trailingLibraryToken(string $title, ?string $libraryTitle): ?string
    {
        if ($libraryTitle === null || $libraryTitle === '') {
            return null;
        }

        $normalized = preg_replace('/[^A-Za-z0-9._-]+/', '_', $libraryTitle) ?? $libraryTitle;
        $normalized = trim(preg_replace('/[._]+/', ' ', $normalized) ?? $normalized);
        if ($normalized === '') {
            return null;
        }

        return str_ends_with($title, ' ' . $normalized) ? $normalized : null;
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
