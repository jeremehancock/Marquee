<?php

declare(strict_types=1);

namespace App\Tests\Unit\Settings;

use App\Settings\SettingKey;
use App\Settings\SupersededEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * What an upgrading install is told about the compose file it still has.
 *
 * The reason this is tested rather than left to prose: the failure is silent.
 * A variable that stops being read and is never mentioned leaves the user
 * comparing a file they trust against behaviour that no longer matches it, with
 * nothing anywhere saying which one is lying.
 */
final class SupersededEnvironmentTest extends TestCase
{
    private const RETIRED = ['PLEX_TOKEN', 'AUTH_USERNAME', 'AUTH_PASSWORD', 'AUTH_BYPASS'];

    protected function setUp(): void
    {
        $this->clear();
    }

    protected function tearDown(): void
    {
        $this->clear();
    }

    private function clear(): void
    {
        foreach (self::RETIRED as $name) {
            putenv($name);
        }
        foreach (SettingKey::all() as $key) {
            putenv($key->variable());
        }
    }

    public function testNothingSetMeansNothingToReport(): void
    {
        $report = new SupersededEnvironment();

        self::assertFalse($report->hasAny());
        self::assertSame([], $report->all());
    }

    public function testARetiredVariableIsReported(): void
    {
        putenv('PLEX_TOKEN=xyz');

        $report = new SupersededEnvironment();

        self::assertTrue($report->hasAny());
        self::assertTrue($report->has('PLEX_TOKEN'));
        self::assertCount(1, $report->retired());
        self::assertSame([], $report->relocated());
    }

    public function testEveryRetiredVariableIsKnown(): void
    {
        foreach (self::RETIRED as $name) {
            putenv($name . '=set');
        }

        $report = new SupersededEnvironment();

        self::assertCount(4, $report->retired());
        foreach (self::RETIRED as $name) {
            self::assertTrue($report->has($name), $name . ' is not reported');
        }
    }

    public function testARelocatedVariableIsReported(): void
    {
        putenv('SITE_TITLE=Home Cinema');

        $report = new SupersededEnvironment();

        self::assertSame(['SITE_TITLE'], $report->relocatedNames());
        self::assertSame([], $report->retired());
    }

    /**
     * Deriving the relocated list from `SettingKey` is what stops it going
     * stale: a setting added to the store is reported without anyone
     * remembering to add it in a second place.
     */
    public function testEverySettingTheStoreOwnsIsReportedWhenSet(): void
    {
        foreach (SettingKey::all() as $key) {
            putenv($key->variable() . '=set');
        }

        $report = new SupersededEnvironment();

        self::assertCount(count(SettingKey::all()), $report->relocated());
    }

    public function testTheTwoKindsStayDistinguishable(): void
    {
        putenv('AUTH_PASSWORD=hunter2');
        putenv('SITE_TITLE=Home Cinema');

        $report = new SupersededEnvironment();

        self::assertSame(['AUTH_PASSWORD'], array_map(
            static fn (object $v): string => $v->name,
            $report->retired(),
        ));
        self::assertSame(['SITE_TITLE'], $report->relocatedNames());
    }

    /**
     * Presence, not truth. `AUTO_IMPORT_ENABLED=false` is exactly as inert as
     * `=true`, and deleting the line is the remedy for both.
     */
    public function testAFalseValueIsStillReported(): void
    {
        putenv('AUTO_IMPORT_ENABLED=false');

        self::assertTrue((new SupersededEnvironment())->has('AUTO_IMPORT_ENABLED'));
    }

    /**
     * An emptied line has already stopped configuring anything, so telling the
     * user to delete it is noise. This also matches how the tests set up an
     * app: {@see \App\Tests\AppTestCase} clears these by setting them empty.
     */
    public function testAnEmptyVariableIsNotReported(): void
    {
        putenv('PLEX_TOKEN=');
        putenv('SITE_TITLE=');

        self::assertFalse((new SupersededEnvironment())->hasAny());
    }

    public function testAVariableThatWasNeverSupersededIsNotReported(): void
    {
        putenv('DATA_DIR=/somewhere');

        self::assertFalse((new SupersededEnvironment())->has('DATA_DIR'));

        putenv('DATA_DIR');
    }
}
