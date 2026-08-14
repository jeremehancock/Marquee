<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Session\ArraySession;
use App\Support\Session\SessionInterface;

/**
 * An in-memory session that records whether it was started.
 *
 * Exists for one requirement: a route that reads no session state must not
 * start one. That is invisible to every other kind of assertion — the response
 * is byte-for-byte identical either way — so it can only be checked by watching
 * the session itself.
 *
 * It matters because starting a session is not free on the routes that do it
 * most. The store collects expired sessions probabilistically during session
 * startup, so the health check and the Poster Wall — polled continuously, and
 * reading nothing — were the dominant trigger for the sweep that evicted real
 * signed-in users.
 *
 * Wraps ArraySession rather than extending it: that class is final, and
 * delegating keeps this a recorder rather than a second implementation of
 * session semantics that could drift from the one under test.
 */
final class SpySession implements SessionInterface
{
    private readonly ArraySession $inner;

    private int $starts = 0;

    public function __construct()
    {
        $this->inner = new ArraySession();
    }

    public function start(): void
    {
        ++$this->starts;

        $this->inner->start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->inner->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function regenerate(): void
    {
        $this->inner->regenerate();
    }

    public function extendLifetime(int $seconds): void
    {
        $this->inner->extendLifetime($seconds);
    }

    public function clear(): void
    {
        $this->inner->clear();
    }

    /**
     * How many times this session was started.
     */
    public function starts(): int
    {
        return $this->starts;
    }

    /**
     * Whether anything asked for a session at all.
     */
    public function wasStarted(): bool
    {
        return $this->starts > 0;
    }
}
