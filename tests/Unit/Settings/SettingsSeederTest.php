<?php

declare(strict_types=1);

namespace App\Tests\Unit\Settings;

use App\Settings\SettingKey;
use App\Settings\SettingsSeeder;
use App\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;

/**
 * The upgrade path: an install configured entirely from its compose file must
 * come up behaving exactly as it did, and must then stop taking direction from
 * that file.
 *
 * The second half is the one worth pinning. A seeder that ran twice would
 * overwrite whatever the user had changed with whatever their compose file
 * still said, on every container restart — a silent revert with no error and
 * nothing in a log.
 */
final class SettingsSeederTest extends TestCase
{
    private string $dir = '';

    /** Every variable this class sets, cleared unconditionally. */
    private const TOUCHED = [
        'SITE_TITLE',
        'IMAGES_PER_PAGE',
        'AUTO_IMPORT_ENABLED',
        'EXCLUDED_LIBRARIES',
        'PLEX_SERVER_URL',
        'IGNORE_ARTICLES_IN_SORT',
    ];

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/marquee-seed-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o775, true);
        $this->dir = $dir;

        $this->clearEnvironment();
    }

    protected function tearDown(): void
    {
        $this->clearEnvironment();

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    private function clearEnvironment(): void
    {
        foreach (self::TOUCHED as $variable) {
            putenv($variable);
        }
    }

    private function seed(): SettingsStore
    {
        $store = new SettingsStore($this->dir);
        (new SettingsSeeder($store))->seed();

        return $store;
    }

    public function testAnUpgradingInstallKeepsItsComposeConfiguration(): void
    {
        putenv('SITE_TITLE=Home Cinema');
        putenv('IMAGES_PER_PAGE=48');
        putenv('AUTO_IMPORT_ENABLED=true');
        putenv('EXCLUDED_LIBRARIES=4K Movies, Kids');

        $this->seed();

        $store = new SettingsStore($this->dir);
        self::assertSame('Home Cinema', $store->string(SettingKey::SiteTitle));
        self::assertSame(48, $store->int(SettingKey::ImagesPerPage));
        self::assertTrue($store->bool(SettingKey::AutoImportEnabled));
        self::assertSame(['4K Movies', 'Kids'], $store->list(SettingKey::ExcludedLibraries));
    }

    public function testAFreshInstallSeedsDocumentedDefaults(): void
    {
        $this->seed();

        $store = new SettingsStore($this->dir);
        self::assertSame('Marquee', $store->string(SettingKey::SiteTitle));
        self::assertSame(24, $store->int(SettingKey::ImagesPerPage));
        self::assertFalse($store->bool(SettingKey::AutoImportEnabled));
        self::assertSame([], $store->list(SettingKey::ExcludedLibraries));
        self::assertTrue($store->bool(SettingKey::IgnoreArticlesInSort));
    }

    public function testSeedingMarksTheStore(): void
    {
        self::assertFalse((new SettingsStore($this->dir))->isSeeded());

        $this->seed();

        self::assertTrue((new SettingsStore($this->dir))->isSeeded());
    }

    /**
     * The regression this whole class is for: a changed compose file must not
     * reach a store that has already been seeded.
     */
    public function testASecondBootDoesNotReSeed(): void
    {
        putenv('SITE_TITLE=First Choice');
        $this->seed();

        putenv('SITE_TITLE=Changed In Compose');
        $this->seed();

        self::assertSame('First Choice', (new SettingsStore($this->dir))->string(SettingKey::SiteTitle));
    }

    public function testSeedingDoesNotOverwriteAValueTheUserChanged(): void
    {
        putenv('SITE_TITLE=From Compose');
        $this->seed();

        (new SettingsStore($this->dir))->set(SettingKey::SiteTitle, 'Chosen In The App');

        // A container restart: a fresh store, a fresh seeder, same environment.
        $this->seed();

        self::assertSame('Chosen In The App', (new SettingsStore($this->dir))->string(SettingKey::SiteTitle));
    }

    /**
     * Booleans, integers, and lists have to mean what they meant when the
     * configuration objects read them directly, or seeding silently changes
     * behaviour on upgrade.
     */
    public function testCoercionMatchesWhatTheEnvironmentAlwaysMeant(): void
    {
        putenv('AUTO_IMPORT_ENABLED=yes');
        putenv('IGNORE_ARTICLES_IN_SORT=off');
        putenv('EXCLUDED_LIBRARIES= Kids , , 4K Movies ');

        $this->seed();

        $store = new SettingsStore($this->dir);
        self::assertTrue($store->bool(SettingKey::AutoImportEnabled));
        self::assertFalse($store->bool(SettingKey::IgnoreArticlesInSort));
        self::assertSame(['Kids', '4K Movies'], $store->list(SettingKey::ExcludedLibraries));
    }

    public function testAnEmptyVariableSeedsTheDefault(): void
    {
        putenv('SITE_TITLE=');

        $this->seed();

        self::assertSame('Marquee', (new SettingsStore($this->dir))->string(SettingKey::SiteTitle));
    }

    /**
     * Every key is written, so that the store is a complete record of the
     * install rather than a sparse overlay whose gaps are filled by defaults
     * that could later move.
     */
    public function testEverySettingIsWritten(): void
    {
        $this->seed();

        $raw = file_get_contents($this->dir . '/settings.json');
        self::assertNotFalse($raw);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        foreach (SettingKey::all() as $key) {
            self::assertArrayHasKey($key->value, $decoded, $key->value . ' was not seeded');
        }
    }
}
