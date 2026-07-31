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
     * The one title every surface shows for this poster: the caption and its
     * tooltip, the image's alt text, the mobile action sheet heading, the
     * change-poster dialog heading, and the delete confirmation. They
     * deliberately share it, so a poster reads the same wherever it is named.
     *
     * $plexTitle is the title import recorded for the item, and it is preferred
     * over title() because the filename is a lossy copy of it: sanitising
     * flattens every run of non-alphanumerics ("Marvel's Agents of S.H.I.E.L.D."
     * lands as "Marvel_s_Agents_of_S.H.I.E.L.D.") and import appends the source
     * library. Reconstructing from that cannot be made correct — a show Plex
     * names "Lucky (2026)" reaches disk as "Lucky_2026", which is
     * indistinguishable from a show genuinely called "Class of 2026". The
     * recorded title has the parentheses, so nothing has to be guessed.
     *
     * The year is appended in parentheses when known, unless the title already
     * contains it that way. The check looks for the parenthesised form
     * specifically, so bare digits in a title are never read as a year already
     * being present: "Class of 2026" still becomes "Class of 2026 (2026)".
     *
     * Seasons are the exception and get no year at all. A season row stores its
     * *show's* year — Plex reports none on a season node — so "Breaking Bad -
     * Season 5 (2008)" would date a 2012 season to the year the show began.
     * A year that is part of the show's own name ("Lucky (2026) - Season 1")
     * still shows, because that is the title Plex gave the show, not one Marquee
     * added.
     *
     * A poster with no Plex record (or an empty recorded title) falls back to
     * title(). title() itself is untouched, so sorting and search are unaffected,
     * and nothing here writes to the filename or the database.
     */
    public function captionTitle(?string $plexTitle = null, ?int $year = null): string
    {
        $title = $plexTitle === null || $plexTitle === '' ? $this->title() : $plexTitle;

        if ($this->category === PosterCategory::TvSeasons) {
            return $title;
        }

        if ($year === null || str_contains($title, '(' . $year . ')')) {
            return $title;
        }

        return $title . ' (' . $year . ')';
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
     * Sort key with a leading article removed for article-aware ordering, and
     * numbers ordered by value rather than by digit — so a show's seasons list
     * 1, 2, 3 … 10 rather than 1, 10, 11 … 2. See NaturalOrder for why.
     *
     * The two steps compose in either order: stripping an article works on the
     * front of the string and padding works on digit runs, so neither can move
     * what the other looks at. Padding goes last only because it reads better.
     *
     * This key is never shown to anyone. title() is what the gallery displays
     * and it is untouched here.
     */
    public function sortKey(bool $ignoreArticles): string
    {
        $title = mb_strtolower($this->title());
        if ($ignoreArticles) {
            $title = preg_replace('/^(a|an|the)\s+/', '', $title) ?? $title;
        }

        return NaturalOrder::key($title);
    }
}
