<?php

declare(strict_types=1);

namespace App\Support\Session;

/**
 * In-memory session used by tests so the auth flow can be exercised without
 * touching PHP's global session state.
 */
final class ArraySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): void
    {
        // Nothing to do for an in-memory store.
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
