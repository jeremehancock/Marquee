<?php

declare(strict_types=1);

namespace App\Database;

use App\Poster\PosterFacts;
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
                (rating_key, media_type, category, library_title, section_key, title, filename, thumb, added_at, year, season_number, tmdb_id, parent_title, set_keys, updated_at)
             VALUES
                (:rating_key, :media_type, :category, :library_title, :section_key, :title, :filename, :thumb, :added_at, :year, :season_number, :tmdb_id, :parent_title, :set_keys, :updated_at)
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
                set_keys = excluded.set_keys,
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
            ':set_keys' => PlexItemRecord::joinSetKeys($record->setKeys),
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
     * Everything a render needs to know about one category's mapped posters, in
     * a single read.
     *
     * This replaces six separate per-column reads — the title, the year, the
     * season number, the related title's inputs, the sets, and the Plex "added
     * at" timestamp — each of which scanned the same rows of the same category.
     * The All view was paying twenty of them, and a filtered one twenty-four.
     *
     * **Every row comes back.** The reads this replaces each dropped rows they
     * had nothing to say about, and their callers read that omission as an
     * instruction to fall back. That cannot survive a combined read — a row with
     * no year still has a title — so absence moves into {@see PosterFacts},
     * which spells each of those fallbacks out. Nothing about which poster falls
     * back to what has changed.
     *
     * @return array<string, PosterFacts> keyed by filename
     */
    public function factsForCategory(string $category): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT filename, title, year, season_number, parent_title, set_keys, added_at
               FROM plex_items
              WHERE category = :category'
        );
        $stmt->execute([':category' => $category]);

        $facts = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row) || !isset($row['filename'])) {
                continue;
            }

            $facts[Scalar::string($row['filename'])] = PosterFacts::fromRecorded(
                Scalar::string($row['title'] ?? null),
                Scalar::intOrNull($row['year'] ?? null),
                Scalar::intOrNull($row['season_number'] ?? null),
                Scalar::string($row['parent_title'] ?? null),
                PlexItemRecord::splitSetKeys(Scalar::string($row['set_keys'] ?? null)),
                Scalar::int($row['added_at'] ?? null),
            );
        }

        return $facts;
    }

    /**
     * Record what a set is called, as Plex reports it.
     *
     * Written from the import's collection walk and show loop, both of which
     * already hold the title — so this costs no request. Upserted rather than
     * filled-if-blank, because a collection renamed in Plex should be corrected,
     * and there is exactly one writer.
     */
    public function rememberSetName(string $ratingKey, string $title): void
    {
        if ($ratingKey === '' || $title === '') {
            return;
        }

        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO plex_sets (rating_key, title, updated_at)
             VALUES (:key, :title, :now)
             ON CONFLICT(rating_key) DO UPDATE SET
                title = excluded.title,
                updated_at = excluded.updated_at'
        );
        $stmt->execute([':key' => $ratingKey, ':title' => $title, ':now' => time()]);
    }

    /**
     * The title of the item a set is named by — the show's or the collection's —
     * so a set view can say what it is showing.
     *
     * Two places hold it, and the order matters. The item's OWN poster row is
     * preferred: it is the title the gallery captions that poster with, so a set
     * cannot be named one thing in its summary and another on the card naming
     * it. Failing that, the name recorded during the import's membership walk,
     * which exists whether or not the poster was ever imported — the common case
     * for a user who imports films but not collection artwork.
     *
     * Null when neither knows, which is a set imported before names were
     * recorded. The caller describes the set rather than failing.
     *
     * One keyed lookup, not a scan, and one per render rather than one per
     * category — which is why it is not part of the combined facts read.
     */
    public function titleForRatingKey(string $ratingKey): ?string
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT title FROM (
                SELECT title, 0 AS rank FROM plex_items WHERE rating_key = :key AND title <> \'\'
                UNION ALL
                SELECT title, 1 AS rank FROM plex_sets WHERE rating_key = :key AND title <> \'\'
             ) ORDER BY rank LIMIT 1'
        );
        $stmt->execute([':key' => $ratingKey]);
        $row = $stmt->fetch();

        return is_array($row) && isset($row['title']) ? Scalar::string($row['title']) : null;
    }

    /**
     * The names of several sets at once, keyed by rating key — what the "also
     * in" line on a set view needs.
     *
     * One read for the whole list rather than one per set: a poster in five
     * collections must not cost five queries to name four of them. Sets with no
     * known name are simply absent, and the caller describes those rather than
     * omitting the link.
     *
     * @param list<string> $ratingKeys
     *
     * @return array<string, string>
     */
    public function titlesForRatingKeys(array $ratingKeys): array
    {
        $keys = array_values(array_unique(array_filter($ratingKeys, static fn (string $k): bool => $k !== '')));
        if ($keys === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->database->pdo()->prepare(sprintf(
            'SELECT rating_key, title FROM (
                SELECT rating_key, title, 0 AS rank FROM plex_items
                 WHERE rating_key IN (%1$s) AND title <> \'\'
                UNION ALL
                SELECT rating_key, title, 1 AS rank FROM plex_sets
                 WHERE rating_key IN (%1$s) AND title <> \'\'
             ) ORDER BY rank',
            $placeholders,
        ));
        $stmt->execute([...$keys, ...$keys]);

        $titles = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row) || !isset($row['rating_key'], $row['title'])) {
                continue;
            }
            // Ordered by rank, so the poster row's title arrives first and the
            // recorded set name never overwrites it.
            $key = Scalar::string($row['rating_key']);
            $titles[$key] ??= Scalar::string($row['title']);
        }

        return $titles;
    }

    /**
     * Record an item's set only where it has none yet.
     *
     * An item's set can be known while that item's own type is not being
     * imported: a movie import learns every collection in the library, and a
     * season import walks each show. Without this, a user who imports only
     * movies leaves the collection's own poster out of the set its films point
     * at, and one who imports only seasons leaves the show's poster out of
     * theirs — the set is right except for the poster that names it.
     *
     * Guarded on the set being empty so it can only ever fill a blank. The full
     * import path owns changing one, and this must not race it.
     */
    public function fillMissingSetKey(string $ratingKey, string $setKey): void
    {
        if ($setKey === '') {
            return;
        }

        $stmt = $this->database->pdo()->prepare(
            'UPDATE plex_items SET set_keys = :set, updated_at = :now
              WHERE rating_key = :key AND set_keys = \'\''
        );
        $stmt->execute([':set' => $setKey, ':now' => time(), ':key' => $ratingKey]);
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
