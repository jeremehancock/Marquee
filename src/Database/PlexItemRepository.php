<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Maps Plex items (by rating key) to the poster files imported for them.
 */
final class PlexItemRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findByRatingKey(string $ratingKey): ?PlexItemRecord
    {
        $stmt = $this->database->pdo()->prepare('SELECT * FROM plex_items WHERE rating_key = :key');
        $stmt->execute([':key' => $ratingKey]);
        $row = $stmt->fetch();

        return is_array($row) ? PlexItemRecord::fromRow($row) : null;
    }

    public function upsert(PlexItemRecord $record): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO plex_items
                (rating_key, media_type, category, library_title, section_key, title, filename, thumb, added_at, updated_at)
             VALUES
                (:rating_key, :media_type, :category, :library_title, :section_key, :title, :filename, :thumb, :added_at, :updated_at)
             ON CONFLICT(rating_key) DO UPDATE SET
                media_type = excluded.media_type,
                category = excluded.category,
                library_title = excluded.library_title,
                section_key = excluded.section_key,
                title = excluded.title,
                filename = excluded.filename,
                thumb = excluded.thumb,
                added_at = excluded.added_at,
                updated_at = excluded.updated_at'
        );

        $stmt->execute([
            ':rating_key' => $record->ratingKey,
            ':media_type' => $record->mediaType,
            ':category' => $record->category,
            ':library_title' => $record->libraryTitle,
            ':section_key' => $record->sectionKey,
            ':title' => $record->title,
            ':filename' => $record->filename,
            ':thumb' => $record->thumb,
            ':added_at' => $record->addedAt,
            ':updated_at' => $record->updatedAt,
        ]);
    }

    public function findByFilename(string $category, string $filename): ?PlexItemRecord
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT * FROM plex_items WHERE category = :category AND filename = :filename LIMIT 1'
        );
        $stmt->execute([':category' => $category, ':filename' => $filename]);
        $row = $stmt->fetch();

        return is_array($row) ? PlexItemRecord::fromRow($row) : null;
    }

    /**
     * Filenames in a category that are linked to a Plex item.
     *
     * @return list<string>
     */
    public function filenamesForCategory(string $category): array
    {
        $stmt = $this->database->pdo()->prepare('SELECT filename FROM plex_items WHERE category = :category');
        $stmt->execute([':category' => $category]);

        $filenames = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['filename'])) {
                $filenames[] = (string) $row['filename'];
            }
        }

        return $filenames;
    }

    /**
     * The Plex "added at" timestamp for each mapped poster in a category, keyed
     * by filename. Rows with no known timestamp (added_at = 0) are omitted so
     * the caller can fall back to the file's modification time.
     *
     * @return array<string, int>
     */
    public function addedAtForCategory(string $category): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT filename, added_at FROM plex_items WHERE category = :category AND added_at > 0'
        );
        $stmt->execute([':category' => $category]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['filename'], $row['added_at'])) {
                $map[(string) $row['filename']] = (int) $row['added_at'];
            }
        }

        return $map;
    }

    public function deleteByRatingKey(string $ratingKey): void
    {
        $stmt = $this->database->pdo()->prepare('DELETE FROM plex_items WHERE rating_key = :key');
        $stmt->execute([':key' => $ratingKey]);
    }

    /**
     * Distinct Plex media types that currently have a stored poster.
     *
     * @return list<string>
     */
    public function distinctMediaTypes(): array
    {
        $stmt = $this->database->pdo()->query('SELECT DISTINCT media_type FROM plex_items');

        $types = [];
        foreach ($stmt !== false ? $stmt->fetchAll() : [] as $row) {
            if (is_array($row) && isset($row['media_type'])) {
                $types[] = (string) $row['media_type'];
            }
        }

        return $types;
    }

    /**
     * @return list<PlexItemRecord>
     */
    public function all(): array
    {
        $stmt = $this->database->pdo()->query('SELECT * FROM plex_items ORDER BY title');

        $records = [];
        foreach ($stmt !== false ? $stmt->fetchAll() : [] as $row) {
            if (is_array($row)) {
                $records[] = PlexItemRecord::fromRow($row);
            }
        }

        return $records;
    }
}
