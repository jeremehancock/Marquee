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

        // Avoid "database is locked" errors when a write (e.g. an import) overlaps
        // a read (e.g. the gallery): wait for locks and let readers run during writes.
        $pdo->exec('PRAGMA busy_timeout = 5000');
        if ($this->path !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
        $pdo->exec('PRAGMA synchronous = NORMAL');

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
                section_key TEXT NOT NULL DEFAULT \'\',
                title TEXT NOT NULL,
                filename TEXT NOT NULL,
                thumb TEXT NOT NULL DEFAULT \'\',
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

        // The connected server's own name, cached so every page can report the
        // Plex connection without a request to Plex on render. It is Plex's own
        // data, so caching it keeps the database a cache of things Plex holds.
        // One row, hence the CHECK: there is exactly one connected server.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS plex_server (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                friendly_name TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )'
        );

        // Which auto-import slot last completed, and the guard held while one is
        // running. One row, hence the CHECK: there is one schedule.
        //
        // This is the one thing here that is not a cache of Plex's own data.
        // `application-shell` admits it under a stated bound — losing it costs
        // one redundant import, and an import that finds nothing changed in Plex
        // downloads nothing — so deleting this file stays a safe reset.
        //
        // `last_slot` is the slot, not the moment the run finished: "which slot
        // is done" is the question the scheduler asks.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS auto_import_schedule (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                last_slot INTEGER,
                running_since INTEGER
            )'
        );

        // What a set is CALLED, keyed by the rating key its members record.
        //
        // A set's name would seem to belong on the item that names it, and it
        // does — in plex_items, which holds one row per imported POSTER. That is
        // the problem: a user who imports films without collection posters has
        // no row for the collection at all, so a set opened from one of its films
        // could only be described ("in this set") rather than named. The name is
        // a fact about the set; the poster is a separate thing that may not
        // exist.
        //
        // Not a `set_titles` column beside `set_keys`. Titles contain commas, so
        // the comma-joined encoding would have to become JSON; a parallel array
        // has to be kept in step with its partner on every write; and it would
        // store a collection's name once per member film rather than once.
        //
        // Written during the walk that already reads collection membership, so
        // it costs no request. Empty until the next import, where a set is named
        // exactly as well as it is today.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS plex_sets (
                rating_key TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )'
        );

        // Added after the initial release; safe to run every boot.
        $this->ensureColumn($pdo, 'plex_items', 'section_key', "TEXT NOT NULL DEFAULT ''");
        // Plex's poster path carries a version token; storing it lets an import
        // skip re-downloading posters that have not changed in Plex.
        $this->ensureColumn($pdo, 'plex_items', 'thumb', "TEXT NOT NULL DEFAULT ''");
        // Plex's "added at" timestamp lets the gallery order posters by when
        // their media was added to Plex. 0 means unknown (falls back to mtime).
        $this->ensureColumn($pdo, 'plex_items', 'added_at', 'INTEGER NOT NULL DEFAULT 0');
        // Release year, sent to the poster search as a disambiguation hint.
        // Nullable: Plex does not report a year for every item.
        $this->ensureColumn($pdo, 'plex_items', 'year', 'INTEGER');
        // Plex's season index. Nullable rather than defaulted, because 0 is a
        // real season number (Specials) and so cannot double as "not a season".
        $this->ensureColumn($pdo, 'plex_items', 'season_number', 'INTEGER');
        // The work's TMDB id, which identifies it exactly where a title cannot.
        // TEXT because it is an opaque key, never an operand. Nullable, and null
        // is a normal permanent state: a collection is a local Plex grouping
        // with no upstream record, and an unmatched item has no id either.
        $this->ensureColumn($pdo, 'plex_items', 'tmdb_id', 'TEXT');
        // The title of the show a season belongs to, recorded as a fact of its
        // own rather than left inside the season's display title. That title is
        // the show's and the season's joined ("Breaking Bad - Season 5"), and the
        // join cannot be undone by inspecting the result: splitting at the first
        // separator misreads a show whose name contains one ("Cowboy Bebop -
        // Remastered"), and splitting at the last misreads a season whose name
        // does ("Part 2 - Finale"). Plex reports the two separately at import, so
        // nothing has to be guessed. Empty for everything that is not a season —
        // a movie, show or collection has no parent to name — which is why this
        // defaults rather than being nullable.
        $this->ensureColumn($pdo, 'plex_items', 'parent_title', "TEXT NOT NULL DEFAULT ''");
        // The Plex rating keys of the sets this item belongs to, comma separated:
        // a show and a collection record their own, a season its show's, a movie
        // those of every collection holding it. Posters listing a key are shown
        // together by Related posters. Rating keys rather than titles because a
        // title cannot express a collection whose films share no words in their
        // names ("Iron Man", "Thor"), and because two works can share a title
        // while no two share a key. Empty for a movie in no collection, which is
        // the ordinary case and not missing information.
        //
        // A LIST because collections overlap in a real library: "Godzilla vs.
        // Kong" is in both King Kong and MonsterVerse, "Planes" in both Planes
        // and Thanksgiving. This was one key, and the collection that happened to
        // be read first took the film — which left every other collection sharing
        // a film holding nothing but its own poster.
        //
        // The `set_key` column an earlier build of this change added is dead and
        // read by nothing. It is left in place because SQLite cannot drop a
        // column without rewriting the table, and an unused one costs nothing.
        $this->ensureColumn($pdo, 'plex_items', 'set_keys', "TEXT NOT NULL DEFAULT ''");
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $type): void
    {
        $stmt = $pdo->query(sprintf('PRAGMA table_info(%s)', $table));
        $columns = $stmt !== false ? array_column($stmt->fetchAll(), 'name') : [];
        if (!in_array($column, $columns, true)) {
            $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $type));
        }
    }
}
