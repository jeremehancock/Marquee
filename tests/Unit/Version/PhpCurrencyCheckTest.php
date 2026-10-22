<?php

declare(strict_types=1);

namespace App\Tests\Unit\Version;

use App\Version\PhpCurrencyCheck;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PhpCurrencyCheckTest extends TestCase
{
    /** The shape php.net returns, trimmed to the fields the check reads. */
    private function release(string $version, string $date): string
    {
        return (string) json_encode(['version' => $version, 'date' => $date, 'tags' => ['security']]);
    }

    private function today(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 06:17:00 UTC');
    }

    public function testBehindBeyondTheGracePeriodFailsNamingBothVersions(): void
    {
        $verdict = (new PhpCurrencyCheck())->assess('8.5.12', $this->release('8.5.13', '19 Nov 2026'), $this->today('2026-11-27'));

        self::assertFalse($verdict->passed);
        self::assertStringContainsString('8.5.12', $verdict->message);
        self::assertStringContainsString('8.5.13', $verdict->message);
    }

    public function testBehindInsideTheGracePeriodPasses(): void
    {
        $verdict = (new PhpCurrencyCheck())->assess('8.5.12', $this->release('8.5.13', '19 Nov 2026'), $this->today('2026-11-26'));

        self::assertTrue($verdict->passed);
        self::assertStringContainsString('8.5.13', $verdict->message);
    }

    public function testTheGracePeriodIsSevenDays(): void
    {
        self::assertSame(7, PhpCurrencyCheck::GRACE_DAYS);
    }

    public function testCurrentImagePasses(): void
    {
        $verdict = (new PhpCurrencyCheck())->assess('8.5.12', $this->release('8.5.12', '21 Oct 2026'), $this->today('2027-03-01'));

        self::assertTrue($verdict->passed);
    }

    public function testVersionsCompareNumericallyNotAsText(): void
    {
        // As text, "8.5.9" sorts after "8.5.10".
        $verdict = (new PhpCurrencyCheck())->assess('8.5.9', $this->release('8.5.10', '1 Jan 2027'), $this->today('2027-02-01'));

        self::assertFalse($verdict->passed);
    }

    public function testUnreadableImageVersionFails(): void
    {
        foreach (['', 'PHP 8.5.12', '8.5'] as $image) {
            $verdict = (new PhpCurrencyCheck())->assess($image, $this->release('8.5.12', '21 Oct 2026'), $this->today('2026-10-22'));

            self::assertFalse($verdict->passed, "image version '{$image}'");
        }
    }

    public function testUnreadableReleaseListFails(): void
    {
        $unreadable = [
            '',
            'not json',
            '{"error":"Unknown version"}',
            $this->release('8.5.12', 'yesterday'),
            $this->release('', '21 Oct 2026'),
        ];

        foreach ($unreadable as $json) {
            $verdict = (new PhpCurrencyCheck())->assess('8.5.12', $json, $this->today('2026-10-22'));

            self::assertFalse($verdict->passed, "release JSON '{$json}'");
        }
    }

    public function testAnAnswerForAnotherLineFails(): void
    {
        $verdict = (new PhpCurrencyCheck())->assess('8.5.12', $this->release('8.4.27', '21 Oct 2026'), $this->today('2026-10-22'));

        self::assertFalse($verdict->passed);
    }
}
