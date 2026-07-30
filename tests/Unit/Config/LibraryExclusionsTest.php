<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\LibraryExclusions;
use PHPUnit\Framework\TestCase;

final class LibraryExclusionsTest extends TestCase
{
    public function testMatchesAnExactName(): void
    {
        $exclusions = new LibraryExclusions(['Kids Movies']);

        self::assertTrue($exclusions->isExcluded('Kids Movies'));
        self::assertFalse($exclusions->isExcluded('Movies'));
    }

    public function testMatchingIgnoresCaseAndSurroundingWhitespace(): void
    {
        $exclusions = new LibraryExclusions(['Kids Movies', '  Anime  ']);

        self::assertTrue($exclusions->isExcluded('kids movies'));
        self::assertTrue($exclusions->isExcluded('KIDS MOVIES'));
        self::assertTrue($exclusions->isExcluded('  Anime '));
        self::assertTrue($exclusions->isExcluded('anime'));
    }

    public function testNothingIsExcludedWithoutConfiguration(): void
    {
        $exclusions = new LibraryExclusions([]);

        self::assertFalse($exclusions->hasAny());
        self::assertFalse($exclusions->isExcluded('Movies'));
    }

    public function testAnEntryThatIsNotALibraryNameExcludesNothing(): void
    {
        // Matching is by library name only. A section key looks plausible in
        // EXCLUDED_LIBRARIES but names no library, so it excludes nothing.
        $exclusions = new LibraryExclusions(['2']);

        self::assertTrue($exclusions->hasAny());
        self::assertFalse($exclusions->isExcluded('TV Shows'));
    }

    public function testReadsCommaSeparatedNamesFromTheEnvironment(): void
    {
        putenv('EXCLUDED_LIBRARIES=Kids Movies, Anime ,');

        try {
            $exclusions = LibraryExclusions::fromEnv();

            self::assertSame(['Kids Movies', 'Anime'], $exclusions->names);
            self::assertTrue($exclusions->isExcluded('kids movies'));
        } finally {
            putenv('EXCLUDED_LIBRARIES');
        }
    }

    public function testUnsetEnvironmentExcludesNothing(): void
    {
        putenv('EXCLUDED_LIBRARIES');

        self::assertFalse(LibraryExclusions::fromEnv()->hasAny());
    }
}
