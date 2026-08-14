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
 *
 * The same reasoning governs two settings that decide how long a sign-in
 * survives, and for a long time it did not reach them. Left to the runtime, the
 * cookie is discarded when the browser closes and the session file is collected
 * after twenty-four minutes of disuse — both of which end a thirty-day window
 * long before thirty days, and neither of which anybody chose. They are set
 * here, from the one configured duration, so that `SESSION_DURATION` governs
 * every layer of the session rather than only the one the application reads
 * back.
 */
final class NativeSession implements SessionInterface
{
    /**
     * @param int $lifetime how long a session may go unused before it ends, in
     *                      seconds — the same value the authenticated window is
     *                      renewed by, so the browser, the session store, and
     *                      the application all expire together
     */
    public function __construct(private readonly int $lifetime)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        // Refuses a session identifier PHP did not issue. Without this, an
        // identifier can be planted in the browser beforehand, and regenerating
        // on login only closes the other half of session fixation.
        ini_set('session.use_strict_mode', '1');

        // How long the store keeps an idle session. The runtime's default is
        // twenty-four minutes, which against a thirty-day window is what
        // actually decides when a user is signed out: the session file is
        // deleted underneath a session the application still considers valid,
        // and the user is returned to a login that needs plex.tv.
        ini_set('session.gc_maxlifetime', (string) $this->lifetime);

        // Must precede session_start(): these have no effect once the session
        // is active.
        //
        // `lifetime` here is what makes every cookie PHP issues by itself carry
        // a real expiry — including the one session_regenerate_id() emits
        // during login. It is not the sliding part; extendLifetime() is. Both
        // are needed, because relying on the re-issue alone would leave the
        // login's durability depending on which Set-Cookie header arrives last.
        session_set_cookie_params(['lifetime' => $this->lifetime] + $this->cookieAttributes());

        session_start();
    }

    /**
     * Re-issue the session cookie so its expiry moves out from now.
     *
     * PHP writes the cookie when it creates a session and not again, so without
     * this the browser's window would be stamped once at sign-in and never
     * move — an absolute deadline hidden one layer below the sliding one the
     * application enforces, and invisible when it fires.
     */
    public function extendLifetime(int $seconds): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        $name = session_name();
        $id = session_id();
        if ($name === false || $id === false) {
            return;
        }

        setcookie($name, $id, ['expires' => time() + $seconds] + $this->cookieAttributes());
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

    /**
     * The cookie's attributes, minus whichever expiry key the caller needs.
     *
     * Shared so that a cookie re-issued by extendLifetime() cannot drift from
     * the one session_start() issues. `Secure` is absent here for the reason
     * given on the class; the two callers differ only in that
     * session_set_cookie_params() takes a relative `lifetime` and setcookie()
     * takes an absolute `expires`.
     *
     * @return array{path: string, httponly: bool, samesite: 'Lax'}
     */
    private function cookieAttributes(): array
    {
        return [
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
