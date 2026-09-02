<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Scalar;

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
                (rating_key, media_type, category, library_title, section_key, title, filename, thumb, added_at, year, season_number, tmdb_id, parent_title, updated_at)
             VALUES
                (:rating_key, :media_type, :category, :library_title, :section_key, :title, :filename, :thumb, :added_at, :year, :season_number, :tmdb_id, :parent_title, :updated_at)
             ON CONFLICT(rating_key) DO UPDATE SET
                media_type = excluded.media_type,
                category = excluded.category,
                library_title = excluded.library_title,
                section_key = excluded.section_key,
                title = excluded.title,
                filename = excluded.filename,
                thumb = excluded.thumb,
                added_at = excluded.added_at,
                year = excluded.year,
                season_number = excluded.season_number,
                tmdb_id = excluded.tmdb_id,
                parent_title = excluded.parent_title,
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
            ':year' => $record->year,
            ':season_number' => $record->seasonNumber,
            ':tmdb_id' => $record->tmdbId,
            ':parent_title' => $record->parentTitle,
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
                $filenames[] = Scalar::string($row['filename']);
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
                $map[Scalar::string($row['filename'])] = Scalar::int($row['added_at']);
            }
        }

        return $map;
    }

    /**
     * The title Plex reported for each mapped poster in a category, keyed by
     * filename. This is what the gallery shows. It is preferred over the
     * filename-derived title because the filename is a sanitised copy of it —
     * punctuation flattened, library appended — and that loss cannot be undone by
     * inspecting the filename. Rows with an empty title are omitted so the caller
     * falls back rather than rendering a blank caption.
     *
     * @return array<string, string>
     */
    public function titlesForCategory(string $category): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT filename, title FROM plex_items WHERE category = :category AND title <> \'\''
        );
        $stmt->execute([':category' => $category]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['filename'], $row['title'])) {
                $map[Scalar::string($row['filename'])] = Scalar::string($row['title']);
            }
        }

        return $map;
    }

    /**
     * The release year for each mapped poster in a category, keyed by filename.
     * The gallery shows it in parentheses. Import writes a year into the filename
     * only for movies but records one for shows and seasons too, so this column —
     * not the filename — is the reliable source. Rows with no year are omitted,
     * and the caller shows those posters' titles unchanged.
     *
     * @return array<string, int>
     */
    public function yearsForCategory(string $category): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT filename, year FROM plex_items WHERE category = :category AND year IS NOT NULL'
        );
        $stmt->execute([':category' => $category]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['filename'], $row['year'])) {
                $map[Scalar::string($row['filename'])] = Scalar::int($row['year']);
            }
        }

        return $map;
    }

    /**
     * The title to search for when a user asks for a poster's related posters,
     * keyed by filename within a category.
     *
     * A season answers with its **show's** title, so the search gathers the show
     * and every sibling season rather than the one season it started from.
     * Everything else answers with its own title. Resolving that here rather than
     * at the caller means no surface has to know which kind of item it is holding.
     *
     * The year is deliberately not part of this. The caption appends it, but a
     * query carrying "(1999)" would narrow the search back to the single poster
     * the action was started from.
     *
     * A season imported before show titles were recorded has an empty
     * `parent_title` and answers with its own title until the next import fills
     * it in — narrow rather than wrong. Rows with nothing to offer are omitted so
     * the caller falls back to the filename-derived title.
     *
     * @return array<string, string>
     */
    public function relatedTitlesForCategory(string $category): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT filename,
                    CASE WHEN parent_title <> \'\' THEN parent_title ELSE title END AS related_title
               FROM plex_items
              WHERE category = :category
                AND (parent_title <> \'\' OR title <> \'\')'
        );
        $stmt->execute([':category' => $category]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['filename'], $row['related_title'])) {
                $map[Scalar::string($row['filename'])] = Scalar::string($row['related_title']);
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
     * Remove every mapping for a poster file. Deleting a poster can leave more
     * than one row behind — a stale mapping from a since-recreated Plex item and
     * the live one can share a filename — so all matching rows are cleared.
     */
    public function deleteByCategoryAndFilename(string $category, string $filename): void
    {
        $stmt = $this->database->pdo()->prepare(
            'DELETE FROM plex_items WHERE category = :category AND filename = :filename'
        );
        $stmt->execute([':category' => $category, ':filename' => $filename]);
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
                $types[] = Scalar::string($row['media_type']);
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
