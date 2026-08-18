<?php

declare(strict_types=1);

namespace App\Auth\Claim;

use JsonException;

/**
 * Bounds how often the claim code may be guessed.
 *
 * Global rather than per-address, which is the unusual choice here and the
 * deliberate one. Claiming is a single global event, not something each visitor
 * does, so there is nothing to key a per-visitor limit to. Per-address limits
 * would also be worth less than they look: the web server's own rate limit
 * already documents that behind a reverse proxy every request shares one
 * address, and an attacker with a handful of addresses defeats the idea outright.
 * Counting attempts against the install itself cannot be sidestepped that way.
 *
 * The cost is that an attacker can lock the owner out by guessing wrongly on
 * purpose. That is acceptable and not symmetric: the owner has host access, so a
 * lockout costs them a short wait — or deleting this file — while the failure it
 * prevents costs them the install. The window is minutes for that reason.
 *
 * This is not what makes guessing impractical. {@see ClaimCode} is; at 130 bits
 * an attacker allowed unlimited attempts would still be nowhere. This exists so
 * that a flood is refused cheaply rather than because the code needs protecting.
 *
 * State lives in its own small file rather than in the database, because the
 * database is a deletable cache and a lockout that `rm` clears is not one — and
 * because this must work before anything else about the install exists.
 */
final class ClaimAttempts
{
    public const FILENAME = 'claim-attempts.json';

    /**
     * Failures tolerated before the cooling-off begins.
     *
     * Generous enough that someone mistyping a 26-character code, or pasting the
     * wrong line out of a log, is never caught by it.
     */
    public const LIMIT = 10;

    /**
     * How long the endpoint refuses attempts once the limit is reached.
     */
    public const COOLING_OFF_SECONDS = 900;

    public function __construct(private readonly string $dataDir)
    {
    }

    /**
     * Whether attempts are currently being refused.
     */
    public function isCoolingOff(int $now): bool
    {
        return $this->remainingSeconds($now) > 0;
    }

    /**
     * Seconds until attempts are accepted again; zero when they already are.
     */
    public function remainingSeconds(int $now): int
    {
        $state = $this->read();
        if ($state['failures'] < self::LIMIT) {
            return 0;
        }

        $elapsed = $now - $state['last_failure'];
        $remaining = self::COOLING_OFF_SECONDS - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Record a failed attempt.
     *
     * A failure after the cooling-off has elapsed starts the count again rather
     * than adding to a total that never resets, so an install is not permanently
     * one mistake away from being locked.
     */
    public function recordFailure(int $now): void
    {
        $state = $this->read();

        $expired = $state['failures'] >= self::LIMIT && $this->remainingSeconds($now) === 0;
        $failures = $expired ? 1 : $state['failures'] + 1;

        $this->write(['failures' => $failures, 'last_failure' => $now]);
    }

    /**
     * Forget every attempt. Called once the install is claimed, when the endpoint
     * stops existing in any meaningful sense.
     */
    public function clear(): void
    {
        $path = $this->path();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array{failures: int, last_failure: int}
     */
    private function read(): array
    {
        $path = $this->path();
        if (!is_file($path) || !is_readable($path)) {
            return ['failures' => 0, 'last_failure' => 0];
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return ['failures' => 0, 'last_failure' => 0];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['failures' => 0, 'last_failure' => 0];
        }

        if (!is_array($decoded)) {
            return ['failures' => 0, 'last_failure' => 0];
        }

        $failures = $decoded['failures'] ?? 0;
        $last = $decoded['last_failure'] ?? 0;

        return [
            'failures' => is_int($failures) ? $failures : 0,
            'last_failure' => is_int($last) ? $last : 0,
        ];
    }

    /**
     * @param array{failures: int, last_failure: int} $state
     */
    private function write(array $state): void
    {
        if (!is_dir($this->dataDir)) {
            @mkdir($this->dataDir, 0o775, true);
        }

        $temp = @tempnam($this->dataDir, '.attempts');
        if ($temp === false) {
            return;
        }

        @chmod($temp, 0o600);

        try {
            $encoded = json_encode($state, JSON_THROW_ON_ERROR);
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
