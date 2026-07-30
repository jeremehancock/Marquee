<?php

declare(strict_types=1);

namespace App\Tests\Unit\Database;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Tests\Support\MakesImages;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    use MakesImages;

    public function testFileDatabaseUsesWalJournal(): void
    {
        $dir = $this->makeTempDir();
        try {
            $database = new Database($dir . '/marquee.sqlite');
            $statement = $database->pdo()->query('PRAGMA journal_mode');
            self::assertNotFalse($statement);

            self::assertSame('wal', strtolower((string) $statement->fetchColumn()));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testBusyTimeoutIsSet(): void
    {
        $statement = (new Database(':memory:'))->pdo()->query('PRAGMA busy_timeout');
        self::assertNotFalse($statement);

        self::assertSame(5000, (int) $statement->fetchColumn());
    }

    /**
     * A database written by the initial release opens and gains every column
     * added since, so upgrading never requires deleting or rebuilding it.
     */
    public function testOlderDatabaseGainsTheColumnsAddedSinceRelease(): void
    {
        $dir = $this->makeTempDir();
        try {
            $path = $dir . '/marquee.sqlite';

            $legacy = new PDO('sqlite:' . $path);
            $legacy->exec(
                'CREATE TABLE plex_items (
                    rating_key TEXT PRIMARY KEY,
                    media_type TEXT NOT NULL,
                    category TEXT NOT NULL,
                    library_title TEXT NOT NULL,
                    title TEXT NOT NULL,
                    filename TEXT NOT NULL,
                    updated_at INTEGER NOT NULL
                )'
            );
            $legacy->exec(
                "INSERT INTO plex_items VALUES ('10', 'movie', 'movies', 'Movies', 'Solaris', 'Solaris.jpg', 1)"
            );
            unset($legacy);

            $repository = new PlexItemRepository(new Database($path));

            $existing = $repository->findByRatingKey('10');
            self::assertNotNull($existing);
            self::assertNull($existing->tmdbId);

            // …and the row gains the id on the next import, in place.
            $repository->upsert(new PlexItemRecord(
                '10',
                'movie',
                'movies',
                'Movies',
                'Solaris',
                'Solaris.jpg',
                time(),
                tmdbId: '1726',
            ));

            self::assertSame('1726', $repository->findByRatingKey('10')?->tmdbId);
        } finally {
            $this->removeDir($dir);
        }
    }
}
