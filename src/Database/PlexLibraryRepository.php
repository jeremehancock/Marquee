<?php

declare(strict_types=1);

namespace App\Database;

use App\Plex\PlexLibrary;

/**
 * Records the Plex libraries seen during import.
 */
final class PlexLibraryRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function sync(PlexLibrary $library): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO plex_libraries (section_key, title, type, updated_at)
             VALUES (:key, :title, :type, :updated_at)
             ON CONFLICT(section_key) DO UPDATE SET
                title = excluded.title,
                type = excluded.type,
                updated_at = excluded.updated_at'
        );

        $stmt->execute([
            ':key' => $library->key,
            ':title' => $library->title,
            ':type' => $library->type,
            ':updated_at' => time(),
        ]);
    }

    /**
     * @return list<PlexLibrary>
     */
    public function all(): array
    {
        $stmt = $this->database->pdo()->query('SELECT * FROM plex_libraries ORDER BY title');

        $libraries = [];
        foreach ($stmt !== false ? $stmt->fetchAll() : [] as $row) {
            if (is_array($row)) {
                $libraries[] = new PlexLibrary(
                    key: (string) $row['section_key'],
                    title: (string) $row['title'],
                    type: (string) $row['type'],
                );
            }
        }

        return $libraries;
    }
}
