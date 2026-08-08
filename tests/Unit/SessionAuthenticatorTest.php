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
    private function make(int $duration = 3600): array
    {
        $session = new ArraySession();
        $authenticator = new SessionAuthenticator(new AuthConfig(sessionDuration: $duration), $session);

        return [$authenticator, $session];
    }

    public function testNobodyIsAuthenticatedByDefault(): void
    {
        [$auth] = $this->make();

        self::assertFalse($auth->isAuthenticated());
    }

    public function testEstablishingASessionAuthenticatesIt(): void
    {
        [$auth] = $this->make();

        $auth->establish();

        self::assertTrue($auth->isAuthenticated());
    }

    public function testExpiredSessionIsNotAuthenticated(): void
    {
        [$auth, $session] = $this->make();
        $auth->establish();

        $session->set('expires_at', time() - 1);

        self::assertFalse($auth->isAuthenticated());
    }

    /**
     * The window slides: a session ends after a period of disuse rather than a
     * fixed interval after signing in. With a thirty-day default an absolute
     * deadline would eject a user mid-session for no reason they could observe.
     */
    public function testUseRenewsTheWindow(): void
    {
        [$auth, $session] = $this->make(duration: 3600);
        $auth->establish();

        // Most of the window has gone by without the session being touched.
        $session->set('expires_at', time() + 5);
        self::assertTrue($auth->isAuthenticated());

        // That single check pushed the deadline back out to a full window.
        self::assertGreaterThan(time() + 3500, $session->get('expires_at'));
    }

    public function testASessionOutlivesItsDurationWhileItIsUsed(): void
    {
        [$auth, $session] = $this->make(duration: 60);
        $auth->establish();

        // Four windows' worth of elapsed time, used once per window.
        for ($i = 0; $i < 4; ++$i) {
            $session->set('expires_at', time() + 1);
            self::assertTrue($auth->isAuthenticated(), 'window ' . $i);
        }

        self::assertTrue($auth->isAuthenticated());
    }

    public function testAnIdleSessionExpires(): void
    {
        [$auth, $session] = $this->make(duration: 60);
        $auth->establish();

        $session->set('expires_at', time() - 1);

        self::assertFalse($auth->isAuthenticated());
        // Expiry clears the session rather than merely reporting it.
        self::assertFalse($session->has('authenticated'));
    }

    public function testLogoutClearsAuthentication(): void
    {
        [$auth] = $this->make();
        $auth->establish();

        $auth->logout();

        self::assertFalse($auth->isAuthenticated());
    }

    /**
     * Session fixation: an identifier known to somebody else before the user
     * signed in must be worthless afterwards.
     */
    public function testEstablishingASessionIssuesANewIdentifier(): void
    {
        [$auth, $session] = $this->make();

        self::assertSame(0, $session->regenerations());
        $auth->establish();
        self::assertSame(1, $session->regenerations());
    }

    /**
     * Regeneration carries the session's contents across. An in-flight Plex
     * authorization request lives in the session, so losing it here would break
     * the very sign-in that is establishing this session.
     */
    public function testSessionContentsSurviveTheNewIdentifier(): void
    {
        [$auth, $session] = $this->make();
        $session->set('plex_pin_code', 'ABCD');

        $auth->establish();

        self::assertSame('ABCD', $session->get('plex_pin_code'));
        self::assertTrue($auth->isAuthenticated());
    }

    /**
     * A zero or negative duration would expire every session the instant it was
     * created, locking the install out of a login that is now the only way in.
     */
    public function testAnUnusableDurationIsFloored(): void
    {
        putenv('SESSION_DURATION=0');
        $config = AuthConfig::fromEnv();
        putenv('SESSION_DURATION');

        self::assertGreaterThanOrEqual(60, $config->sessionDuration);
    }

    public function testTheDefaultSessionIsThirtyDays(): void
    {
        putenv('SESSION_DURATION');
        self::assertSame(2592000, AuthConfig::fromEnv()->sessionDuration);
    }
}
