<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Opens the SQLite database lazily and applies idempotent migrations. The file
 * only caches Plex mappings and is safe to delete.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if ($this->path !== ':memory:') {
            $dir = dirname($this->path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0o775, true);
            }
        }

        $pdo = new PDO('sqlite:' . $this->path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->migrate($pdo);
        $this->pdo = $pdo;

        return $pdo;
    }

    private function migrate(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS plex_items (
                rating_key TEXT PRIMARY KEY,
                media_type TEXT NOT NULL,
                category TEXT NOT NULL,
                library_title TEXT NOT NULL,
                title TEXT NOT NULL,
                filename TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS plex_libraries (
                section_key TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                type TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )'
        );
    }
}
