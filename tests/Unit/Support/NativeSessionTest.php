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
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheCookieIsHttpOnlyAndSameSiteLax(): void
    {
        (new NativeSession())->start();

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
        (new NativeSession())->start();

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
        (new NativeSession())->start();

        self::assertSame('1', ini_get('session.use_strict_mode'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegeneratingReplacesTheIdentifierAndKeepsTheContents(): void
    {
        $session = new NativeSession();
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
        (new NativeSession())->regenerate();

        self::assertSame(PHP_SESSION_NONE, session_status());
    }
}
