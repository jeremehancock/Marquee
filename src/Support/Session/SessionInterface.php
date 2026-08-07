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

    public function clear(): void;
}
