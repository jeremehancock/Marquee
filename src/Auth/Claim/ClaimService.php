<?php

declare(strict_types=1);

namespace App\Auth\Claim;

use App\Plex\Connection\PlexConnectionStore;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;
use Psr\Log\LoggerInterface;

/**
 * Owns whether this install has been claimed, and the code that claims it.
 *
 * The problem it solves: Marquee is entered by signing in to Plex as the account
 * that owns the configured server, which was a real check only while the server
 * address came from the environment — an assertion only someone with host access
 * could make. Once the address is typed into a browser it proves nothing, because
 * whoever typed it chose the server. Without something in its place, the first
 * stranger to load an unconfigured install would become its owner.
 *
 * So the first boot writes a code to a file only the owning user can read, and
 * logs it. Presenting that code is presenting evidence of host access. After the
 * claim the file is deleted and no further code is ever issued: reclaiming means
 * removing the marker from the filesystem, which is the property being preserved
 * rather than an inconvenience to design around.
 */
final class ClaimService
{
    public const FILENAME = 'claim-code.txt';

    public function __construct(
        private readonly PlexConnectionStore $connection,
        private readonly SettingsStore $settings,
        private readonly ClaimAttempts $attempts,
        private readonly LoggerInterface $logger,
        private readonly string $dataDir,
    ) {
    }

    public function isClaimed(): bool
    {
        return $this->connection->isClaimed();
    }

    /**
     * Seconds until claim attempts are accepted again; zero when they are.
     */
    public function coolingOffSeconds(?int $now = null): int
    {
        return $this->attempts->remainingSeconds($now ?? time());
    }

    /**
     * Make sure an unclaimed install has a code, and that a claimed one does not.
     *
     * Called at bootstrap. Doing it there rather than on the first request to the
     * claim screen means the code is in the log and on disk before anyone can
     * reach the install at all — an operator who starts the container and walks
     * away comes back to a code, not to a screen asking for one that was never
     * written.
     *
     * An install that already has a Plex connection, or that was given a server
     * address in its environment, is treated as claimed without ever seeing a
     * code. That is the upgrade path: presenting an existing user with a wizard
     * demanding a code they have never seen would lock them out of their own
     * install.
     */
    public function ensureCode(): void
    {
        if ($this->isClaimed()) {
            $this->discardCode();

            return;
        }

        if ($this->wasConfiguredBefore()) {
            $this->connection->markClaimed();
            $this->discardCode();

            return;
        }

        if ($this->storedCode() !== null) {
            return;
        }

        $code = ClaimCode::generate();
        $this->writeCode($code);

        // Logged once, on generation, and never again. The log is one of the two
        // ways an operator retrieves it, and reaching either the log or the file
        // needs host access — which is the whole point of the code.
        $this->logger->info(sprintf(
            'Marquee is unclaimed. Claim code: %s (also in %s). Open Marquee and enter it to set this install up.',
            $code,
            $this->path(),
        ));
    }

    /**
     * Claim the install with a submitted code and a server address.
     *
     * One transaction, in this order: store the address, record the claim, then
     * delete the code file. Recording the claim before the address is stored
     * could leave an install claimed with nowhere to connect to and no way to
     * finish; deleting the file before the marker is written could lose both, and
     * an install with neither a code nor a claim is one nobody can ever enter.
     */
    public function claim(string $submittedCode, string $serverUrl, ?int $now = null): bool
    {
        $now ??= time();

        if ($this->attempts->isCoolingOff($now)) {
            $this->logger->warning('Refused a claim attempt: too many failures, still cooling off.');

            return false;
        }

        $stored = $this->storedCode();
        if ($stored === null || !ClaimCode::matches($submittedCode, $stored)) {
            $this->attempts->recordFailure($now);
            $this->logger->warning('Rejected a claim attempt: the code did not match.');

            return false;
        }

        $this->settings->set(SettingKey::PlexServerUrl, $serverUrl);
        $this->connection->markClaimed();
        $this->discardCode();
        $this->attempts->clear();

        // The address, not the code. What matters afterwards is which server this
        // install was pointed at, and by whom — the sign-in that follows logs the
        // account.
        $this->logger->info(sprintf('Marquee has been claimed. Plex server: %s', $serverUrl));

        return true;
    }

    /**
     * The code currently on disk, or null when there is none.
     */
    public function storedCode(): ?string
    {
        $path = $this->path();
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $code = trim($contents);

        return $code !== '' ? $code : null;
    }

    /**
     * Whether this install was configured before claiming existed.
     *
     * A stored token means somebody already signed in and proved ownership. A
     * seeded server address means the compose file carried one, which is the
     * host-access assertion the claim replaces. Either is evidence enough.
     */
    private function wasConfiguredBefore(): bool
    {
        return $this->connection->token() !== null
            || trim($this->settings->string(SettingKey::PlexServerUrl)) !== '';
    }

    private function writeCode(string $code): void
    {
        if (!is_dir($this->dataDir)) {
            @mkdir($this->dataDir, 0o775, true);
        }

        // The same atomic-rename dance the stores use: the mode is set on a
        // temporary file before it is moved into place, so the code is never
        // readable by anyone else, not even for the instant between creating the
        // file and chmod'ing it.
        $temp = @tempnam($this->dataDir, '.claim');
        if ($temp === false) {
            return;
        }

        @chmod($temp, 0o600);

        if (@file_put_contents($temp, $code . "\n") === false || !@rename($temp, $this->path())) {
            @unlink($temp);
        }
    }

    private function discardCode(): void
    {
        $path = $this->path();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function path(): string
    {
        return $this->dataDir . '/' . self::FILENAME;
    }
}
