<?php

declare(strict_types=1);

namespace App\Tests\Unit\Database;

use App\Database\Database;
use App\Tests\Support\MakesImages;
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
}
