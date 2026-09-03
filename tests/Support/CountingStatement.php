<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PDOStatement;

/**
 * A PDO statement that counts how many are created on its connection.
 *
 * PDO exposes no statement counter, and the number of reads a render makes is a
 * pinned property of the gallery — see {@see \App\Tests\Functional\GalleryReadCostTest}.
 * Installing this as the connection's statement class is the only way to observe
 * it without threading a counter through the application itself.
 *
 * Counted where a statement is CREATED rather than where it is executed, so that
 * both `prepare()` and `query()` are seen; the repository uses each. A statement
 * prepared once and executed many times would count once, and nothing in the
 * render path does that.
 *
 * The count is static because PDO constructs these itself and takes its
 * constructor arguments from the attribute rather than from the caller. Reset it
 * before each measurement.
 */
final class CountingStatement extends PDOStatement
{
    public static int $count = 0;

    protected function __construct()
    {
        self::$count++;
    }
}
