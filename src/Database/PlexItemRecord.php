<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Scalar;

/**
 * A stored mapping between a Plex item (rating key) and its poster file.
 */
final class PlexItemRecord
{
    public function __construct(
        public readonly string $ratingKey,
        public readonly string $mediaType,
        public readonly string $category,
        public readonly string $libraryTitle,
        public readonly string $title,
        public readonly string $filename,
        public readonly int $updatedAt,
        public readonly string $sectionKey = '',
        public readonly string $thumb = '',
        public readonly int $addedAt = 0,
        public readonly ?int $year = null,
        public readonly ?int $seasonNumber = null,
        public readonly ?string $tmdbId = null,
        public readonly string $parentTitle = '',
        /** @var list<string> */
        public readonly array $setKeys = [],
    ) {
    }

    /**
     * The stored form of {@see $setKeys}: the keys joined by commas, or the empty
     * string for an item in no set. Plex rating keys carry no commas, so nothing
     * has to be escaped.
     *
     * @param list<string> $setKeys
     */
    public static function joinSetKeys(array $setKeys): string
    {
        return implode(',', $setKeys);
    }

    /**
     * @return list<string>
     */
    private static function splitSetKeys(string $stored): array
    {
        if ($stored === '') {
            return [];
        }

        return array_values(array_filter(
            explode(',', $stored),
            static fn (string $key): bool => $key !== '',
        ));
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            ratingKey: Scalar::string($row['rating_key'] ?? null),
            mediaType: Scalar::string($row['media_type'] ?? null),
            category: Scalar::string($row['category'] ?? null),
            libraryTitle: Scalar::string($row['library_title'] ?? null),
            title: Scalar::string($row['title'] ?? null),
            filename: Scalar::string($row['filename'] ?? null),
            updatedAt: Scalar::int($row['updated_at'] ?? null),
            sectionKey: Scalar::string($row['section_key'] ?? null),
            thumb: Scalar::string($row['thumb'] ?? null),
            addedAt: Scalar::int($row['added_at'] ?? null),
            year: Scalar::intOrNull($row['year'] ?? null),
            seasonNumber: Scalar::intOrNull($row['season_number'] ?? null),
            tmdbId: Scalar::stringOrNull($row['tmdb_id'] ?? null),
            parentTitle: Scalar::string($row['parent_title'] ?? null),
            setKeys: self::splitSetKeys(Scalar::string($row['set_keys'] ?? null)),
        );
    }
}
