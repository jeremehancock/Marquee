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

    private int $regenerations = 0;

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

    /**
     * Counted rather than ignored, so that "logging in issues a new identifier"
     * can be asserted without starting a real session. A no-op double would
     * leave the requirement untestable at the unit level.
     *
     * The contents survive, as they do natively.
     */
    public function regenerate(): void
    {
        ++$this->regenerations;
    }

    /**
     * How many times a new identifier has been issued. Test support.
     */
    public function regenerations(): int
    {
        return $this->regenerations;
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
