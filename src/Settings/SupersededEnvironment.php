<?php

declare(strict_types=1);

namespace App\Settings;

use App\Support\Env;

/**
 * Reports the environment variables an install still sets that Marquee no
 * longer reads.
 *
 * This exists because the alternative is the worst version of every change that
 * moves a setting: an install is upgraded, something it configured stops taking
 * effect, and nothing says so. The user is left comparing their compose file
 * against behaviour that no longer matches it. One sentence turns that into an
 * instruction.
 *
 * It replaces the per-configuration flags that did this for the three variables
 * anyone had thought to handle — `PLEX_TOKEN` on {@see \App\Config\PlexConfig},
 * `AUTH_USERNAME` / `AUTH_PASSWORD` / `AUTH_BYPASS` on
 * {@see \App\Config\AuthConfig}. Every setting the store owns is now in the same
 * position, so the report is derived from {@see SettingKey} rather than listed
 * by hand: a setting added to the store is reported without anyone remembering
 * to add it here.
 *
 * Presence is what counts, never truth. `AUTO_IMPORT_ENABLED=false` is exactly
 * as superseded as `AUTO_IMPORT_ENABLED=true`, and deleting the line is the
 * remedy for both.
 */
final class SupersededEnvironment
{
    /**
     * Variables whose capability is gone rather than moved.
     *
     * `PLEX_TOKEN` was replaced by signing in to Plex; the three `AUTH_*`
     * variables by the same sign-in becoming the login. None of them has an
     * equivalent anywhere in the interface, which is why they are not reported
     * as merely relocated.
     *
     * @var list<string>
     */
    private const RETIRED = [
        'PLEX_TOKEN',
        'AUTH_USERNAME',
        'AUTH_PASSWORD',
        'AUTH_BYPASS',
    ];

    /**
     * @var list<SupersededVariable>|null
     */
    private ?array $variables = null;

    /**
     * Every superseded variable that is currently set.
     *
     * @return list<SupersededVariable>
     */
    public function all(): array
    {
        if ($this->variables !== null) {
            return $this->variables;
        }

        $found = [];

        foreach (self::RETIRED as $name) {
            if (self::isSet($name)) {
                $found[] = new SupersededVariable($name, SupersededKind::Retired);
            }
        }

        foreach (SettingKey::all() as $key) {
            $name = $key->variable();
            if (self::isSet($name)) {
                $found[] = new SupersededVariable($name, SupersededKind::Relocated);
            }
        }

        $this->variables = $found;

        return $found;
    }

    /**
     * Variables whose capability no longer exists.
     *
     * @return list<SupersededVariable>
     */
    public function retired(): array
    {
        return array_values(array_filter($this->all(), static fn (SupersededVariable $v): bool => $v->isRetired()));
    }

    /**
     * Variables the application now owns.
     *
     * @return list<SupersededVariable>
     */
    public function relocated(): array
    {
        return array_values(array_filter($this->all(), static fn (SupersededVariable $v): bool => $v->isRelocated()));
    }

    public function hasAny(): bool
    {
        return $this->all() !== [];
    }

    /**
     * Whether one named variable is superseded and still set.
     *
     * The retired variables each get their own sentence on the connection
     * screen, because "this no longer exists" is only useful alongside what
     * replaced it — and what replaced a password is not what replaced a token.
     * This lets the template ask for them by name without reaching past the
     * report for a second source of truth.
     */
    public function has(string $name): bool
    {
        foreach ($this->all() as $variable) {
            if ($variable->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * The names of the variables the application now owns, for listing.
     *
     * @return list<string>
     */
    public function relocatedNames(): array
    {
        return array_map(static fn (SupersededVariable $v): string => $v->name, $this->relocated());
    }

    /**
     * Whether a variable is present, by presence rather than by value.
     *
     * `Env::str()` treats an empty variable as absent, which is right for
     * configuration — an empty value means "use the default" — and right here
     * too. A user who has emptied a line rather than deleting it has already
     * stopped configuring anything with it, and telling them to delete an empty
     * line is noise.
     */
    private static function isSet(string $name): bool
    {
        return Env::str($name, '') !== '';
    }
}
