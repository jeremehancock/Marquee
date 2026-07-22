<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\AutoImportConfig;
use App\Plex\PlexMediaType;
use PHPUnit\Framework\TestCase;

final class AutoImportConfigTest extends TestCase
{
    public function testMediaTypesReflectToggles(): void
    {
        $config = new AutoImportConfig(
            enabled: true,
            importMovies: true,
            importShows: false,
            importSeasons: true,
            importCollections: false,
            excludedLibraries: [],
        );

        self::assertSame([PlexMediaType::Movie, PlexMediaType::Season], $config->mediaTypes());
    }

    public function testExclusionIsCaseInsensitive(): void
    {
        $config = new AutoImportConfig(true, true, false, false, false, ['Kids Movies', 'Anime']);

        self::assertTrue($config->isExcluded('kids movies'));
        self::assertTrue($config->isExcluded('  Anime '));
        self::assertFalse($config->isExcluded('Movies'));
    }
}
