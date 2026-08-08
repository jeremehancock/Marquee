<?php

declare(strict_types=1);

namespace App\Support\Session;

/**
 * Session backed by PHP's native session handling. Used at runtime.
 *
 * The cookie's security attributes are set here rather than left to the
 * runtime's configuration. A default is not a decision: an image rebuild, a
 * base-image change, or a different `php.ini` would silently drop them, and
 * nothing would fail. This session is what stands between a visitor and a
 * stored Plex credential, so its defences are decided in this repository.
 *
 * `Secure` is deliberately absent. Marquee is routinely reached over plain HTTP
 * on a local network, and a `Secure` cookie is never sent over HTTP — setting it
 * unconditionally would prevent logging in at all on those installs. Deriving it
 * from the request scheme was considered and rejected: it would make a security
 * attribute depend on a client-supplied header, and behind a misconfigured proxy
 * it fails toward the lockout. If it is ever wanted it belongs behind an
 * explicit opt-in, not a guess.
 */
final class NativeSession implements SessionInterface
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        // Refuses a session identifier PHP did not issue. Without this, an
        // identifier can be planted in the browser beforehand, and regenerating
        // on login only closes the other half of session fixation.
        ini_set('session.use_strict_mode', '1');

        // Must precede session_start(): these have no effect once the session
        // is active.
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        // Deletes the old session rather than leaving it usable; contents are
        // carried across, so an in-flight Plex authorization request survives
        // logging in.
        session_regenerate_id(true);
    }

    public function clear(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_destroy();
        }
    }
}
