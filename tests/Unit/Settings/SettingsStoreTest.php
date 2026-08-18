<?php

declare(strict_types=1);

namespace App\Tests\Unit\Settings;

use App\Settings\SettingKey;
use App\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;

/**
 * The store's durability promises, which are the reason it is a file of its own
 * rather than a column somewhere.
 *
 * The failure this file exists to prevent is the quiet one: the store is read on
 * every request, so anything that raises instead of degrading turns one bad
 * write into an install nobody can log in to fix.
 */
final class SettingsStoreTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/marquee-settings-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o775, true);
        $this->dir = $dir;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testAStoredValueSurvivesANewStore(): void
    {
        (new SettingsStore($this->dir))->set(SettingKey::SiteTitle, 'Home Cinema');

        self::assertSame('Home Cinema', (new SettingsStore($this->dir))->string(SettingKey::SiteTitle));
    }

    public function testEveryTypeRoundTrips(): void
    {
        $store = new SettingsStore($this->dir);
        $store->set(SettingKey::ImagesPerPage, 48);
        $store->set(SettingKey::PlexRemoveOverlayLabel, true);
        $store->set(SettingKey::ExcludedLibraries, ['4K Movies', 'Kids']);

        $reopened = new SettingsStore($this->dir);
        self::assertSame(48, $reopened->int(SettingKey::ImagesPerPage));
        self::assertTrue($reopened->bool(SettingKey::PlexRemoveOverlayLabel));
        self::assertSame(['4K Movies', 'Kids'], $reopened->list(SettingKey::ExcludedLibraries));
    }

    /**
     * The web process and the scheduled import hold separate stores. Writing a
     * whole in-memory snapshot would drop whatever the other one had added since
     * this one last read.
     */
    public function testAWriteKeepsAKeyAnotherStoreWroteFirst(): void
    {
        $first = new SettingsStore($this->dir);
        $second = new SettingsStore($this->dir);

        // Both load before either writes, so each holds a snapshot that is about
        // to go stale.
        $first->string(SettingKey::SiteTitle);
        $second->string(SettingKey::SiteTitle);

        $first->set(SettingKey::SiteTitle, 'Home Cinema');
        $second->set(SettingKey::ImagesPerPage, 48);

        $reopened = new SettingsStore($this->dir);
        self::assertSame('Home Cinema', $reopened->string(SettingKey::SiteTitle));
        self::assertSame(48, $reopened->int(SettingKey::ImagesPerPage));
    }

    public function testAnAbsentFileYieldsDefaults(): void
    {
        $store = new SettingsStore($this->dir);

        self::assertSame('Marquee', $store->string(SettingKey::SiteTitle));
        self::assertSame(24, $store->int(SettingKey::ImagesPerPage));
        self::assertFalse($store->bool(SettingKey::AutoImportEnabled));
        self::assertSame([], $store->list(SettingKey::ExcludedLibraries));
    }

    public function testAMalformedFileYieldsDefaultsRatherThanRaising(): void
    {
        file_put_contents($this->dir . '/settings.json', '{ this is not json');

        self::assertSame('Marquee', (new SettingsStore($this->dir))->string(SettingKey::SiteTitle));
    }

    public function testAFileThatIsNotAnObjectYieldsDefaults(): void
    {
        file_put_contents($this->dir . '/settings.json', '"a bare string"');

        self::assertSame('Marquee', (new SettingsStore($this->dir))->string(SettingKey::SiteTitle));
    }

    /**
     * One bad entry costs one setting. Anything else would let a hand-edited
     * file take the whole configuration down with it.
     */
    public function testAnEntryOfTheWrongShapeCostsOnlyItsOwnSetting(): void
    {
        file_put_contents($this->dir . '/settings.json', json_encode([
            'site_title' => ['not', 'a', 'string'],
            'images_per_page' => 48,
        ], JSON_THROW_ON_ERROR));

        $store = new SettingsStore($this->dir);
        self::assertSame('Marquee', $store->string(SettingKey::SiteTitle));
        self::assertSame(48, $store->int(SettingKey::ImagesPerPage));
    }

    public function testUnusableListEntriesAreDroppedIndividually(): void
    {
        file_put_contents($this->dir . '/settings.json', json_encode([
            'excluded_libraries' => ['4K Movies', '', 7, '  ', 'Kids'],
        ], JSON_THROW_ON_ERROR));

        self::assertSame(
            ['4K Movies', 'Kids'],
            (new SettingsStore($this->dir))->list(SettingKey::ExcludedLibraries),
        );
    }

    /**
     * Seeding writes numeric strings straight from the environment, so reading
     * one back as an integer has to work.
     */
    public function testANumericStringReadsAsAnInteger(): void
    {
        file_put_contents($this->dir . '/settings.json', json_encode([
            'images_per_page' => '48',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(48, (new SettingsStore($this->dir))->int(SettingKey::ImagesPerPage));
    }

    public function testTheFileIsNotReadableByOthers(): void
    {
        (new SettingsStore($this->dir))->set(SettingKey::SiteTitle, 'Home Cinema');

        $mode = fileperms($this->dir . '/settings.json');
        self::assertNotFalse($mode);
        self::assertSame(0o600, $mode & 0o777);
    }

    public function testNoTemporaryFileIsLeftBehind(): void
    {
        (new SettingsStore($this->dir))->set(SettingKey::SiteTitle, 'Home Cinema');

        self::assertSame(['settings.json'], array_values(array_diff(
            scandir($this->dir) ?: [],
            ['.', '..'],
        )));
    }
}
