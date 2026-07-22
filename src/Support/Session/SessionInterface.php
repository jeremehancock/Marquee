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

    public function clear(): void;
}
