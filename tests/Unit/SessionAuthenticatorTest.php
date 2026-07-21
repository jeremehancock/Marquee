<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Auth\SessionAuthenticator;
use App\Config\AuthConfig;
use App\Support\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class SessionAuthenticatorTest extends TestCase
{
    /**
     * @return array{0: SessionAuthenticator, 1: ArraySession}
     */
    private function make(bool $bypass = false, int $duration = 3600): array
    {
        $session = new ArraySession();
        $authenticator = new SessionAuthenticator(
            new AuthConfig(username: 'admin', password: 'secret', bypass: $bypass, sessionDuration: $duration),
            $session,
        );

        return [$authenticator, $session];
    }

    public function testValidCredentialsAuthenticate(): void
    {
        [$auth] = $this->make();

        self::assertTrue($auth->attempt('admin', 'secret'));
        self::assertTrue($auth->isAuthenticated());
    }

    public function testInvalidCredentialsFail(): void
    {
        [$auth] = $this->make();

        self::assertFalse($auth->attempt('admin', 'nope'));
        self::assertFalse($auth->isAuthenticated());
    }

    public function testExpiredSessionIsNotAuthenticated(): void
    {
        [$auth, $session] = $this->make(duration: 3600);
        $auth->attempt('admin', 'secret');

        $session->set('expires_at', time() - 1);

        self::assertFalse($auth->isAuthenticated());
    }

    public function testBypassIsAlwaysAuthenticated(): void
    {
        [$auth] = $this->make(bypass: true);

        self::assertTrue($auth->isAuthenticated());
    }

    public function testLogoutClearsAuthentication(): void
    {
        [$auth] = $this->make();
        $auth->attempt('admin', 'secret');

        $auth->logout();

        self::assertFalse($auth->isAuthenticated());
    }
}
