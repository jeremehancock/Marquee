<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function App\buildContainer;

use App\Config\AppConfig;
use App\Config\AutoImportConfig;
use App\Config\LibraryExclusions;
use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Poster\SortField;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;
use App\Tests\AppTestCase;

/**
 * Configuration reaches the application from the store, and reaches both
 * processes that need it.
 *
 * The scheduled import is a separate process with no session and no request. It
 * builds its own container and reads the same directory off disk, which is what
 * makes "configured in the browser" mean anything to a job that runs at
 * midnight. A setting that only the web process could see would fail silently,
 * at a time nobody is watching.
 */
final class SettingsResolutionTest extends AppTestCase
{
    private string $dataDir = '';

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/marquee-resolution-' . bin2hex(random_bytes(6));
        mkdir($this->dataDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dataDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dataDir);
    }

    /**
     * Build the app once so the store is seeded and the directory is settled,
     * then hand back the store to write into.
     */
    private function installed(): SettingsStore
    {
        $this->makeApp(['DATA_DIR' => $this->dataDir]);

        return new SettingsStore($this->dataDir);
    }

    public function testStoredSettingsReachEveryConfigurationObject(): void
    {
        $store = $this->installed();
        $store->set(SettingKey::SiteTitle, 'Home Cinema');
        $store->set(SettingKey::ImagesPerPage, 48);
        $store->set(SettingKey::PlexServerUrl, 'http://plex.local:32400');
        $store->set(SettingKey::PlexRemoveOverlayLabel, true);
        $store->set(SettingKey::AutoImportEnabled, true);
        $store->set(SettingKey::ExcludedLibraries, ['Kids']);
        $store->set(SettingKey::DefaultSort, 'date_added');

        $container = buildContainer();
        /** @var AppConfig $app */
        $app = $container->get(AppConfig::class);
        /** @var PosterConfig $poster */
        $poster = $container->get(PosterConfig::class);
        /** @var PlexConfig $plex */
        $plex = $container->get(PlexConfig::class);
        /** @var AutoImportConfig $autoImport */
        $autoImport = $container->get(AutoImportConfig::class);
        /** @var LibraryExclusions $exclusions */
        $exclusions = $container->get(LibraryExclusions::class);

        self::assertSame('Home Cinema', $app->siteTitle);
        self::assertSame(48, $poster->perPage);
        self::assertSame(SortField::DateAdded, $poster->defaultSort->field());
        self::assertSame('http://plex.local:32400', $plex->serverUrl);
        self::assertTrue($plex->removeOverlayLabel);
        self::assertTrue($autoImport->enabled);
        self::assertTrue($exclusions->isExcluded('Kids'));
    }

    /**
     * The failure this guards: a setting the browser can change that the cron
     * job cannot see.
     */
    public function testTheScheduledImportSeesWhatTheWebProcessWrote(): void
    {
        $store = $this->installed();
        $store->set(SettingKey::AutoImportEnabled, true);
        $store->set(SettingKey::AutoImportSeasons, true);
        $store->set(SettingKey::ExcludedLibraries, ['Home Videos']);

        // A fresh container over the same directory, as bin/auto-import.php
        // builds. Nothing carries over but the disk.
        $cli = buildContainer();
        /** @var AutoImportConfig $autoImport */
        $autoImport = $cli->get(AutoImportConfig::class);
        /** @var LibraryExclusions $exclusions */
        $exclusions = $cli->get(LibraryExclusions::class);

        self::assertTrue($autoImport->enabled);
        self::assertTrue($autoImport->importSeasons);
        self::assertTrue($exclusions->isExcluded('Home Videos'));
    }

    /**
     * The environment no longer decides anything once the store exists, which is
     * the whole point of seeding once.
     */
    public function testTheEnvironmentDoesNotOverrideAStoredSetting(): void
    {
        $store = $this->installed();
        $store->set(SettingKey::SiteTitle, 'Chosen In The App');

        $this->supersede(['SITE_TITLE' => 'Left In Compose']);

        /** @var AppConfig $app */
        $app = buildContainer()->get(AppConfig::class);

        self::assertSame('Chosen In The App', $app->siteTitle);
    }

    /**
     * The two settings that cannot come from the store, because the store is
     * inside one of them and the other has to survive the store being broken.
     */
    public function testTheDirectoriesAndErrorSwitchStillComeFromTheEnvironment(): void
    {
        $this->makeApp(['DATA_DIR' => $this->dataDir, 'DISPLAY_ERRORS' => 'true']);

        /** @var AppConfig $config */
        $config = buildContainer()->get(AppConfig::class);

        self::assertSame($this->dataDir, $config->dataDir);
        self::assertTrue($config->displayErrors);
    }

    public function testAnUnreadableStoreLeavesTheApplicationServingDefaults(): void
    {
        $this->installed();
        file_put_contents($this->dataDir . '/settings.json', 'not json at all');

        /** @var AppConfig $app */
        $app = buildContainer()->get(AppConfig::class);

        self::assertSame('Marquee', $app->siteTitle);
    }
}
