<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\AuthConfig;
use App\Support\Scalar;
use App\Support\Session\SessionInterface;

/**
 * Tracks whether this session has been authenticated, and for how much longer.
 *
 * It authenticates nobody itself. A session becomes authenticated only when a
 * Plex sign-in has been verified against the account that owns the configured
 * server, which happens in {@see \App\Plex\Connection\PlexSignInService}. There
 * is no credential to compare here because there is no credential at all.
 */
final class SessionAuthenticator
{
    private const KEY_AUTHENTICATED = 'authenticated';
    private const KEY_EXPIRES_AT = 'expires_at';

    public function __construct(
        private readonly AuthConfig $config,
        private readonly SessionInterface $session,
    ) {
    }

    /**
     * Whether this session is authenticated, renewing its window if it is.
     *
     * The window slides: every request from a live session pushes its expiry
     * out by the configured duration, so the session ends after a period of
     * disuse rather than a fixed interval after signing in. With a thirty-day
     * default an absolute deadline would eject a user mid-session for no reason
     * they could observe, which is the opposite of trusting our own session —
     * and signing in again needs plex.tv, so the eviction would land wherever
     * their internet happened to be.
     */
    public function isAuthenticated(): bool
    {
        if ($this->session->get(self::KEY_AUTHENTICATED) !== true) {
            return false;
        }

        $expiresAt = Scalar::int($this->session->get(self::KEY_EXPIRES_AT, 0));
        if (time() >= $expiresAt) {
            $this->logout();

            return false;
        }

        $this->renew();

        return true;
    }

    /**
     * Mark this session authenticated, after a sign-in has been verified.
     *
     * The identifier is replaced before the session is marked, not after: an
     * identifier known to somebody else before the user signed in must be
     * worthless afterwards, and the pre-sign-in identifier should never hold the
     * authenticated flag at any instant. Contents carry across.
     *
     * Only a verified sign-in reaches here. A refusal must never call this, so
     * that nothing is granted and the identifier is left alone — rotating on
     * refusal would let an unauthenticated caller churn identifiers at will.
     */
    public function establish(): void
    {
        $this->session->regenerate();

        $this->session->set(self::KEY_AUTHENTICATED, true);
        $this->renew();
    }

    /**
     * End this session.
     *
     * This clears the session and nothing else. The stored Plex token survives
     * deliberately: the scheduled auto-import runs as a separate process with no
     * session and authenticates with that token, so clearing it here would stop
     * scheduled imports at the next run, silently. Forgetting the token is what
     * disconnecting is for.
     */
    public function logout(): void
    {
        $this->session->clear();
    }

    private function renew(): void
    {
        $this->session->set(self::KEY_EXPIRES_AT, time() + $this->config->sessionDuration);
    }
}
