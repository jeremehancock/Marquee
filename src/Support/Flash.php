<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Session\SessionInterface;

/**
 * One-shot flash messages stored in the session and consumed on the next render.
 */
final class Flash
{
    private const KEY = 'flash';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function add(string $type, string $message): void
    {
        $this->session->set(self::KEY, ['type' => $type, 'message' => $message]);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    public function pull(): ?array
    {
        $flash = $this->session->get(self::KEY);
        $this->session->set(self::KEY, null);

        if (
            is_array($flash)
            && isset($flash['type'], $flash['message'])
            && is_string($flash['type'])
            && is_string($flash['message'])
        ) {
            return ['type' => $flash['type'], 'message' => $flash['message']];
        }

        return null;
    }
}
