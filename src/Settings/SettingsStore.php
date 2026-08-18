<?php

declare(strict_types=1);

namespace App\Settings;

use JsonException;

/**
 * Persists the settings an install chooses, so that configuration is something
 * the application owns rather than something its compose file dictates.
 *
 * The guarantees are {@see \App\Plex\Connection\PlexConnectionStore}'s, adopted
 * wholesale because they were arrived at for the same reasons: a write lands
 * via a renamed temporary file so a concurrent reader sees the old contents or
 * the new ones and never half of either, and a write re-reads first so that the
 * web process and the scheduled import — which hold separate stores and may
 * each have written since the other last read — cannot silently drop one
 * another's keys.
 *
 * It is a separate file from the connection store rather than more keys in it.
 * The two hold different things with different lifecycles: that file holds
 * credentials, and discarding preferences should not cost a Plex sign-in.
 * Keeping them apart also keeps the number of code paths that can corrupt a
 * credential to the ones that have a reason to touch it.
 *
 * It is not the database either. The database is specified as a cache of Plex
 * data that is safe to delete, and configuration kept there would make deleting
 * it destructive.
 *
 * A missing, unreadable, or malformed file means "nothing stored" rather than an
 * error, and an individual entry whose value is the wrong shape costs that
 * setting rather than the file. This is read on every request: a parse failure
 * that raised would make one bad write impossible to fix from the interface
 * that caused it.
 */
final class SettingsStore
{
    private const FILENAME = 'settings.json';

    /**
     * Records that the environment has already been imported. Not a
     * {@see SettingKey}: it describes the store rather than configuring
     * anything, and seeding must never be able to overwrite it as though it
     * were a setting.
     */
    private const KEY_SEEDED_AT = 'seeded_at';

    /**
     * Loaded contents, or null when the file has not been read yet.
     *
     * @var array<string, mixed>|null
     */
    private ?array $data = null;

    public function __construct(private readonly string $dataDir)
    {
    }

    public function string(SettingKey $key): string
    {
        $value = $this->load()[$key->value] ?? null;

        return is_string($value) ? $value : self::defaultString($key);
    }

    public function int(SettingKey $key): int
    {
        $value = $this->load()[$key->value] ?? null;

        // Integers arrive as integers from JSON, but a value seeded from a
        // numeric string is still the number the user configured.
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '' && is_numeric($value)) {
            return (int) $value;
        }

        return self::defaultInt($key);
    }

    public function bool(SettingKey $key): bool
    {
        $value = $this->load()[$key->value] ?? null;

        return is_bool($value) ? $value : self::defaultBool($key);
    }

    /**
     * @return list<string>
     */
    public function list(SettingKey $key): array
    {
        $value = $this->load()[$key->value] ?? null;
        if (!is_array($value)) {
            return self::defaultList($key);
        }

        // Entries that are not usable strings are dropped rather than costing
        // the whole list: one bad name in an exclusion list should exclude one
        // fewer library, not stop excluding anything.
        $items = [];
        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $items[] = $entry;
            }
        }

        return $items;
    }

    /**
     * Whether the environment has already been imported into this store.
     */
    public function isSeeded(): bool
    {
        $value = $this->load()[self::KEY_SEEDED_AT] ?? null;

        return is_string($value) && $value !== '';
    }

    /**
     * Import the environment, once.
     *
     * The values and the marker land in a single write, so an install can never
     * come up seeded-but-empty or full-but-unmarked — the first would silently
     * reset every setting to its default, and the second would re-import the
     * environment on the next boot and overwrite whatever the user had changed.
     *
     * A store that is already seeded is left exactly as it is.
     *
     * @param array<string, string|int|bool|list<string>> $values keyed by {@see SettingKey} value
     */
    public function seed(array $values): void
    {
        $data = $this->read();
        if (isset($data[self::KEY_SEEDED_AT])) {
            $this->data = $data;

            return;
        }

        foreach ($values as $key => $value) {
            $data[$key] = $value;
        }
        $data[self::KEY_SEEDED_AT] = gmdate('c');

        $this->data = $data;
        $this->write($data);
    }

    /**
     * Store one setting, re-reading first so a write changes only that key.
     *
     * @param string|int|bool|list<string> $value
     */
    public function set(SettingKey $key, string|int|bool|array $value): void
    {
        $data = $this->read();
        if (($data[$key->value] ?? null) === $value) {
            $this->data = $data;

            return;
        }

        $data[$key->value] = $value;
        $this->data = $data;
        $this->write($data);
    }

    private static function defaultString(SettingKey $key): string
    {
        $default = $key->default();

        return is_string($default) ? $default : '';
    }

    private static function defaultInt(SettingKey $key): int
    {
        $default = $key->default();

        return is_int($default) ? $default : 0;
    }

    private static function defaultBool(SettingKey $key): bool
    {
        $default = $key->default();

        return is_bool($default) && $default;
    }

    /**
     * @return list<string>
     */
    private static function defaultList(SettingKey $key): array
    {
        $default = $key->default();

        return is_array($default) ? $default : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $this->data = $this->read();

        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->path();
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        // Non-string keys cannot name a setting, so they are dropped here.
        // Values are kept as they are and checked by the accessor that knows
        // what shape the setting should be.
        $data = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Write the file with owner-only permissions.
     *
     * The mode is set on a temporary file which is then renamed into place, so a
     * concurrent reader sees either the old file or the new one. Settings hold
     * no credential, but they share a directory with one, and a uniform mode is
     * easier to keep right than a per-file exception.
     *
     * @param array<string, mixed> $data
     */
    private function write(array $data): void
    {
        $dir = $this->dataDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        $temp = @tempnam($dir, '.settings');
        if ($temp === false) {
            return;
        }

        @chmod($temp, 0o600);

        try {
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException) {
            @unlink($temp);

            return;
        }

        if (@file_put_contents($temp, $encoded) === false || !@rename($temp, $this->path())) {
            @unlink($temp);
        }
    }

    private function path(): string
    {
        return $this->dataDir . '/' . self::FILENAME;
    }
}
