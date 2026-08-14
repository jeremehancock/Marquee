<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\Session\NativeSession;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The session cookie's attributes are Marquee's decision, not the runtime's.
 *
 * Asserted by reading back what the session was configured with, which is the
 * closest a test can get to the `Set-Cookie` header without an HTTP server.
 * Each case runs in its own process: these calls touch global session state,
 * and the settings only apply to a session that has not started yet.
 */
final class NativeSessionTest extends TestCase
{
    /**
     * A duration that is nothing like either runtime default, so an assertion
     * cannot pass by coincidence: not 0 (the cookie default) and not 1440 (the
     * collection default).
     */
    private const DURATION = 98765;

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheCookieIsHttpOnlyAndSameSiteLax(): void
    {
        (new NativeSession(self::DURATION))->start();

        $params = session_get_cookie_params();

        self::assertTrue($params['httponly']);
        self::assertSame('Lax', $params['samesite']);
        self::assertSame('/', $params['path']);
    }

    /**
     * Marquee is routinely reached over plain HTTP on a LAN, and a `Secure`
     * cookie is never sent over HTTP. Setting it would lock those installs out
     * of their own login, so its absence is a decision and is asserted as one.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheCookieIsNotMarkedSecure(): void
    {
        (new NativeSession(self::DURATION))->start();

        self::assertFalse(session_get_cookie_params()['secure']);
    }

    /**
     * Regenerating on login only closes half of session fixation. This is what
     * stops an identifier being planted in the first place.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAnIdentifierTheSystemDidNotIssueIsRefused(): void
    {
        (new NativeSession(self::DURATION))->start();

        self::assertSame('1', ini_get('session.use_strict_mode'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegeneratingReplacesTheIdentifierAndKeepsTheContents(): void
    {
        $session = new NativeSession(self::DURATION);
        $session->start();
        $session->set('plex_pin_code', 'ABCD');
        $before = session_id();

        $session->regenerate();

        self::assertNotSame($before, session_id());
        self::assertSame('ABCD', $session->get('plex_pin_code'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegeneratingWithoutAStartedSessionDoesNothing(): void
    {
        (new NativeSession(self::DURATION))->regenerate();

        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    /**
     * Left to the runtime this is 0 — a browser-session cookie, discarded when
     * the window closes. The server-side session is untouched and still valid;
     * the browser has simply thrown away the only reference to it, which the
     * user experiences as being signed out for no reason.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheCookieOutlivesTheBrowserSession(): void
    {
        (new NativeSession(self::DURATION))->start();

        self::assertSame(self::DURATION, session_get_cookie_params()['lifetime']);
    }

    /**
     * Left to the runtime this is 1440 — twenty-four minutes. Against a
     * thirty-day window that default, not `SESSION_DURATION`, is what decides
     * when a user is signed out: the store deletes the session underneath a
     * session the application still considers live.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheStoreKeepsAnIdleSessionForTheConfiguredDuration(): void
    {
        (new NativeSession(self::DURATION))->start();

        self::assertSame((string) self::DURATION, ini_get('session.gc_maxlifetime'));
    }

    /**
     * Guarded the same way regenerate() is. Re-issuing a cookie for a session
     * that does not exist would emit a header naming an empty identifier.
     *
     * This is the half of extendLifetime() a CLI test can reach. The header it
     * writes when a session *is* active cannot be read back without an HTTP
     * server or xdebug, so the assertion that it is called — and with the
     * configured duration — is carried by SessionAuthenticatorTest against
     * ArraySession instead. Between the two, both halves are covered.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExtendingWithoutAStartedSessionDoesNothing(): void
    {
        (new NativeSession(self::DURATION))->extendLifetime(self::DURATION);

        self::assertSame(PHP_SESSION_NONE, session_status());
    }
}
