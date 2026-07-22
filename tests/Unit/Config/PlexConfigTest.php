<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\PlexConfig;
use PHPUnit\Framework\TestCase;

final class PlexConfigTest extends TestCase
{
    public function testConfiguredRequiresUrlAndToken(): void
    {
        self::assertTrue((new PlexConfig('http://plex:32400', 'token', 10, 60))->isConfigured());
        self::assertFalse((new PlexConfig('', '', 10, 60))->isConfigured());
        self::assertFalse((new PlexConfig('http://plex:32400', '', 10, 60))->isConfigured());
    }
}
