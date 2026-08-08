<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use JsonException;

/**
 * Persists the Plex connection secrets that the application generates or is
 * given at runtime: the token obtained by signing in to Plex, the client
 * identifier Plex knows this install by, and the secret the poster wall signs
 * its now-playing tokens with.
 *
 * These live in their own file rather than in the SQLite database, for two
 * reasons. The database is specified as a pure cache of Plex data that is safe
 * to delete; a credential in it would make deleting it destructive. And a
 * credential wants explicit owner-only permissions rather than whatever the
 * process umask happens to be, which is easier to guarantee for one small file
 * than for a database the driver creates.
 *
 * The scheduled auto-import runs as a separate CLI process with no session, so
 * reading from disk is what makes a signed-in token usable outside a request.
 * It runs as the same user as the web process, so no permission widening is
 * needed to share the file.
 *
 * A missing, unreadable, or malformed file means "nothing stored" rather than
 * an error: losing the data directory must return Marquee to its first-run
 * state, not a broken one.
 */
final class PlexConnectionStore
{
    private const FILENAME = 'plex-connection.json';

    private const KEY_TOKEN = 'token';
    private const KEY_CLIENT_ID = 'client_identifier';
    private const KEY_SIGNING_SECRET = 'signing_secret';

    /**
     * Loaded contents, or null when the file has not been read yet. Every value
     * is a non-empty string; anything else is dropped while loading.
     *
     * @var array<string, string>|null
     */
    private ?array $data = null;

    public function __construct(private readonly string $dataDir)
    {
    }

    /**
     * The token obtained by signing in to Plex, or null when nobody has.
     */
    public function token(): ?string
    {
        return $this->load()[self::KEY_TOKEN] ?? null;
    }

    public function storeToken(string $token): void
    {
        if ($token === '') {
            return;
        }

        $this->put(self::KEY_TOKEN, $token);
    }

    /**
     * Forget the stored token. The client identifier and signing secret are
     * deliberately kept: signing out must not make Plex treat the next sign-in
     * as a new device, and must not invalidate poster-wall tokens already in
     * flight.
     */
    public function clearToken(): void
    {
        $data = $this->read();
        if (!isset($data[self::KEY_TOKEN])) {
            $this->data = $data;

            return;
        }

        unset($data[self::KEY_TOKEN]);
        $this->data = $data;
        $this->write($data);
    }

    /**
     * The identifier Plex knows this install by, generated once on first use.
     *
     * Stability matters: Plex records one authorized device per identifier, so
     * a value that changed per sign-in would leave the user's account littered
     * with duplicate Marquee entries.
     */
    public function clientIdentifier(): string
    {
        return $this->remember(self::KEY_CLIENT_ID, self::uuid(...));
    }

    /**
     * The secret the poster wall signs now-playing tokens with, generated once
     * on first use.
     *
     * It is generated rather than derived from the Plex token so that signing
     * in again does not rotate it — which would invalidate every token already
     * rendered onto a running wall — and so that it is never empty, which would
     * make signatures computable by anyone.
     */
    public function signingSecret(): string
    {
        return $this->remember(self::KEY_SIGNING_SECRET, static fn (): string => bin2hex(random_bytes(32)));
    }

    /**
     * Return the stored value for a key, generating and persisting one first if
     * it is absent.
     *
     * @param callable(): string $generate
     */
    private function remember(string $key, callable $generate): string
    {
        $existing = $this->load()[$key] ?? null;
        if ($existing !== null) {
            return $existing;
        }

        $value = $generate();
        $this->put($key, $value);

        return $value;
    }

    /**
     * Set one key, re-reading first so a write changes only that key.
     *
     * The in-memory copy can be older than the file: the web process and the
     * scheduled import each hold their own store, and either may generate the
     * client identifier or the signing secret. Writing back a whole stale
     * snapshot would silently drop whatever the other one had added. Writes are
     * rare — signing in, signing out, first-use generation — so the extra read
     * costs nothing worth counting.
     */
    private function put(string $key, string $value): void
    {
        $data = $this->read();
        if (($data[$key] ?? null) === $value) {
            $this->data = $data;

            return;
        }

        $data[$key] = $value;
        $this->data = $data;
        $this->write($data);
    }

    /**
     * @return array<string, string>
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
     * @return array<string, string>
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

        // Keep only usable entries. A hand-edited file with a null or a nested
        // object under one key should cost that key, not the whole connection.
        $data = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Write the file with owner-only permissions.
     *
     * The mode is set on a temporary file which is then renamed into place, so
     * the credential is never briefly readable at the process umask, and a
     * concurrent reader sees either the old file or the new one.
     *
     * @param array<string, string> $data
     */
    private function write(array $data): void
    {
        $dir = $this->dataDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        $temp = @tempnam($dir, '.plex-connection');
        if ($temp === false) {
            return;
        }

        @chmod($temp, 0o600);

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        if (@file_put_contents($temp, $encoded) === false || !@rename($temp, $this->path())) {
            @unlink($temp);
        }
    }

    private function path(): string
    {
        return $this->dataDir . '/' . self::FILENAME;
    }

    /**
     * A random UUID, formatted the way Plex's clients present themselves.
     */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
