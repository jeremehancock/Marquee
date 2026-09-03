<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * What import recorded about the Plex item behind one poster.
 *
 * These six facts were read one column at a time — a query per column per
 * category, five of them on every render and a sixth under the date sort. They
 * are one row, so they are read as one row.
 *
 * **Absence is carried here rather than by a missing key.** Each of the reads
 * this replaces omitted rows on purpose, and callers leaned on that: an empty
 * title meant "caption it from the filename", a null year meant "show no year",
 * no set meant "fall back to the title search", a zero timestamp meant "order it
 * by the file's modification time". A combined read returns every row, so every
 * one of those omissions is a null or an empty list on this object instead. The
 * fallbacks are unchanged; only where they are decided has moved.
 */
final class PosterFacts
{
    private static ?self $none = null;

    /**
     * The poster has no Plex mapping at all: not imported, or imported and since
     * removed from Plex. Distinct from a mapping that records nothing useful,
     * which is what {@see $mapped} is for.
     *
     * Shared rather than built per call. This is what the index answers with on
     * every miss, and the release comparator asks it twice per comparison — so on
     * a library with unmapped posters a fresh object here would mean tens of
     * thousands of allocations inside one sort. It is immutable, so one will do.
     */
    public static function none(): self
    {
        return self::$none ??= new self(mapped: false);
    }

    /**
     * @param list<string> $setKeys
     */
    private function __construct(
        /** Whether a Plex mapping exists for this poster at all. */
        public readonly bool $mapped = false,
        /** The title Plex reported. Null where none was recorded. */
        public readonly ?string $title = null,
        /** The release year Plex reported. Null where none was recorded. */
        public readonly ?int $year = null,
        /** The Plex season number, for a season. Null for everything else. */
        public readonly ?int $seasonNumber = null,
        /** The title Related posters searches for. Empty where none is known. */
        public readonly string $relatedTitle = '',
        /** The sets this poster belongs to, as Plex rating keys. */
        public readonly array $setKeys = [],
        /** The Plex "added at" timestamp. Null where none was recorded. */
        public readonly ?int $addedAt = null,
    ) {
    }

    /**
     * Build from one row of the combined read.
     *
     * The related title is resolved here rather than in the query, because it is
     * a rule about titles rather than about storage and has to be testable
     * without a database — the same reason it was resolved outside the old
     * per-column read it replaces.
     *
     * @param list<string> $setKeys
     */
    public static function fromRecorded(
        string $title,
        ?int $year,
        ?int $seasonNumber,
        string $parentTitle,
        array $setKeys,
        int $addedAt,
    ): self {
        return new self(
            mapped: true,
            // The old read omitted a row with an empty title so the caller fell
            // back to the filename-derived one. Same rule, expressed as null.
            title: $title === '' ? null : $title,
            year: $year,
            seasonNumber: $seasonNumber,
            relatedTitle: RelatedTitle::forRecord($title, $parentTitle, $seasonNumber),
            setKeys: $setKeys,
            // Zero means "Plex told us nothing", not "the epoch". The date sort
            // falls back to the file's own modification time for these.
            addedAt: $addedAt > 0 ? $addedAt : null,
        );
    }
}
