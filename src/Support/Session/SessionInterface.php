<?php

declare(strict_types=1);

namespace App\Support\Session;

/**
 * Abstraction over the session store so that authentication logic never touches
 * PHP's session superglobals directly. This keeps it unit-testable.
 */
interface SessionInterface
{
    public function start(): void;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    /**
     * Issue a new session identifier, carrying the session's contents across
     * and discarding the one that was in use.
     *
     * Belongs here rather than in the caller so that authentication can close
     * session fixation without reaching for PHP's session functions, which is
     * the whole reason this interface exists.
     */
    public function regenerate(): void;

    /**
     * Extend how long the *browser* holds on to this session.
     *
     * Named for the effect rather than the mechanism. The native implementation
     * re-issues a cookie; the in-memory one has no browser and should not be
     * made to pretend it does. What both promise is the same: after this call
     * the client's copy of the session is good for another `$seconds`.
     *
     * This exists because the server-side window and the browser's window are
     * two different clocks, and only one of them was ever being wound. A
     * session can be perfectly valid on the server while the browser has
     * already thrown away the only reference to it, which is indistinguishable
     * from being signed out.
     */
    public function extendLifetime(int $seconds): void;

    public function clear(): void;
}
