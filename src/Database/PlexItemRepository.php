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
            'INSERT INTO plex_items (rating_key, media_type, category, library_title, title, filename, updated_at)
             VALUES (:rating_key, :media_type, :category, :library_title, :title, :filename, :updated_at)
             ON CONFLICT(rating_key) DO UPDATE SET
                media_type = excluded.media_type,
                category = excluded.category,
                library_title = excluded.library_title,
                title = excluded.title,
                filename = excluded.filename,
                updated_at = excluded.updated_at'
        );

        $stmt->execute([
            ':rating_key' => $record->ratingKey,
            ':media_type' => $record->mediaType,
            ':category' => $record->category,
            ':library_title' => $record->libraryTitle,
            ':title' => $record->title,
            ':filename' => $record->filename,
            ':updated_at' => $record->updatedAt,
        ]);
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
