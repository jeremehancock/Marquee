<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\AuthConfig;
use App\Support\Scalar;
use App\Support\Session\SessionInterface;

/**
 * Authenticates a single environment-configured credential pair and tracks the
 * authenticated state (with expiry) in the session store.
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

    public function isAuthenticated(): bool
    {
        if ($this->config->bypass) {
            return true;
        }

        if ($this->session->get(self::KEY_AUTHENTICATED) !== true) {
            return false;
        }

        $expiresAt = Scalar::int($this->session->get(self::KEY_EXPIRES_AT, 0));
        if (time() >= $expiresAt) {
            $this->logout();

            return false;
        }

        return true;
    }

    public function attempt(string $username, string $password): bool
    {
        $userOk = hash_equals($this->config->username, $username);
        $passOk = hash_equals($this->config->password, $password);
        if (!$userOk || !$passOk) {
            // Deliberately does not regenerate. Nothing has been granted, so
            // there is nothing to protect, and rotating on failure would let an
            // unauthenticated caller churn identifiers at will.
            return false;
        }

        // Before the session is marked authenticated, not after: an identifier
        // known to somebody else before the user logged in must be worthless
        // afterwards, and the pre-login identifier should never hold the
        // authenticated flag at any instant. Contents carry across.
        $this->session->regenerate();

        $this->session->set(self::KEY_AUTHENTICATED, true);
        $this->session->set(self::KEY_EXPIRES_AT, time() + $this->config->sessionDuration);

        return true;
    }

    public function logout(): void
    {
        $this->session->clear();
    }
}
