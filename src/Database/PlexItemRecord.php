<?php

declare(strict_types=1);

namespace App\Database;

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
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            ratingKey: (string) $row['rating_key'],
            mediaType: (string) $row['media_type'],
            category: (string) $row['category'],
            libraryTitle: (string) $row['library_title'],
            title: (string) $row['title'],
            filename: (string) $row['filename'],
            updatedAt: (int) $row['updated_at'],
            sectionKey: (string) ($row['section_key'] ?? ''),
            thumb: (string) ($row['thumb'] ?? ''),
            addedAt: (int) ($row['added_at'] ?? 0),
        );
    }
}
