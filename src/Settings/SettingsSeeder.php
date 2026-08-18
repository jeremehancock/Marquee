<?php

declare(strict_types=1);

namespace App\Settings;

use App\Support\Env;

/**
 * Imports the environment into the settings store, once.
 *
 * This is what makes the move from compose-configured to app-configured
 * invisible to an install that upgrades: every value its compose file set is
 * written into the store on the first boot that finds no store, so the install
 * behaves identically before and after. From then on the store is the only
 * source, and the variables that seeded it are reported as superseded rather
 * than obeyed — see {@see SupersededEnvironment}.
 *
 * Seeding once rather than on every boot is deliberate. Re-seeding until the
 * user first saved something would keep compose working for longer, but it
 * would also leave an install that never opens the settings screen never told
 * to clean its compose file up, and would then obsolete every variable at once
 * the first time anything was saved. One import and one notice is the whole
 * point of the migration.
 *
 * Coercion goes through {@see Env}, so a value means the same thing here as it
 * did when the environment was read directly — `AUTO_IMPORT_ENABLED=yes` is the
 * boolean it always was, and an empty variable is an absent one.
 */
final class SettingsSeeder
{
    public function __construct(private readonly SettingsStore $store)
    {
    }

    /**
     * Import the environment unless the store has already been seeded.
     *
     * Safe to call on every bootstrap: the store itself refuses a second seed,
     * so this cannot overwrite a value the user has since changed.
     */
    public function seed(): void
    {
        if ($this->store->isSeeded()) {
            return;
        }

        $values = [];
        foreach (SettingKey::all() as $key) {
            $values[$key->value] = self::read($key);
        }

        $this->store->seed($values);
    }

    /**
     * Read one setting from the environment, falling back to its default.
     *
     * The default is passed to `Env` rather than applied afterwards, so an
     * absent variable and an empty one produce the same stored value — which is
     * what they have always produced when read directly.
     *
     * @return string|int|bool|list<string>
     */
    private static function read(SettingKey $key): string|int|bool|array
    {
        $default = $key->default();
        $variable = $key->variable();

        return match (true) {
            is_bool($default) => Env::bool($variable, $default),
            is_int($default) => Env::int($variable, $default),
            is_array($default) => Env::list($variable, $default),
            default => Env::str($variable, $default),
        };
    }
}
