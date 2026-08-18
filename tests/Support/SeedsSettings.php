<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Settings\SettingsSeeder;
use App\Settings\SettingsStore;
use PHPUnit\Framework\Attributes\After;

/**
 * Builds a settings store seeded from the environment as it stands right now.
 *
 * The configuration tests were written when the configuration objects read the
 * environment directly, and each one asks the same worthwhile question: given
 * this variable, what does the application end up with? That question survives
 * the store — it just now runs through seeding on the way, which is the path a
 * real install takes on its first boot.
 *
 * Each call gets its own directory, because seeding happens once per store and
 * a shared one would freeze the first test's environment for every later test.
 * That is the same trap {@see \App\Tests\AppTestCase} avoids by unlinking the
 * file, and it is worth avoiding twice rather than debugging once.
 */
trait SeedsSettings
{
    /** @var list<string> */
    private array $seededDirs = [];

    /**
     * A store holding what the current environment would seed into a new install.
     */
    protected function seededStore(): SettingsStore
    {
        $dir = sys_get_temp_dir() . '/marquee-seeded-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o775, true);
        $this->seededDirs[] = $dir;

        $store = new SettingsStore($dir);
        (new SettingsSeeder($store))->seed();

        return $store;
    }

    /**
     * An `#[After]` hook rather than `tearDown()`, so that a test class using
     * this trait keeps its own teardown without having to remember to chain.
     */
    #[After]
    protected function removeSeededDirs(): void
    {
        foreach ($this->seededDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }

        $this->seededDirs = [];
    }
}
