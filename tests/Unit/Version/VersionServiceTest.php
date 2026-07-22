<?php

declare(strict_types=1);

namespace App\Tests\Unit\Version;

use App\Version\LatestReleaseProvider;
use App\Version\VersionService;
use PHPUnit\Framework\TestCase;

final class VersionServiceTest extends TestCase
{
    private function provider(?string $latest): LatestReleaseProvider
    {
        return new class ($latest) implements LatestReleaseProvider {
            public function __construct(private readonly ?string $latest)
            {
            }

            public function latestVersion(): ?string
            {
                return $this->latest;
            }
        };
    }

    public function testUpdateAvailableWhenLatestIsNewer(): void
    {
        $service = new VersionService('0.1.0', $this->provider('0.2.0'));

        self::assertTrue($service->updateAvailable());
        self::assertSame('0.2.0', $service->latest());
    }

    public function testNoUpdateWhenEqual(): void
    {
        self::assertFalse((new VersionService('0.1.0', $this->provider('0.1.0')))->updateAvailable());
    }

    public function testNoUpdateWhenOlder(): void
    {
        self::assertFalse((new VersionService('0.2.0', $this->provider('0.1.0')))->updateAvailable());
    }

    public function testNoUpdateWhenUnknown(): void
    {
        $service = new VersionService('0.1.0', $this->provider(null));

        self::assertFalse($service->updateAvailable());
        self::assertNull($service->latest());
        self::assertSame('0.1.0', $service->current());
    }
}
