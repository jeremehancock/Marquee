<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\CsrfGuard;
use App\Support\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CsrfGuardTest extends TestCase
{
    public function testTheTokenIsStableWithinASession(): void
    {
        $guard = new CsrfGuard(new ArraySession());

        self::assertSame($guard->token(), $guard->token());
    }

    public function testTheTokenIsNotTriviallyGuessable(): void
    {
        $token = (new CsrfGuard(new ArraySession()))->token();

        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testDifferentSessionsGetDifferentTokens(): void
    {
        $one = (new CsrfGuard(new ArraySession()))->token();
        $two = (new CsrfGuard(new ArraySession()))->token();

        self::assertNotSame($one, $two);
    }

    /**
     * Two guards over the same session are the same session, so they agree.
     * This is what makes the token a page renders usable by the request it
     * arrives on.
     */
    public function testGuardsSharingASessionShareTheToken(): void
    {
        $session = new ArraySession();

        self::assertSame(
            (new CsrfGuard($session))->token(),
            (new CsrfGuard($session))->token(),
        );
    }

    public function testTheRightTokenMatches(): void
    {
        $guard = new CsrfGuard(new ArraySession());

        self::assertTrue($guard->matches($guard->token()));
    }

    public function testAWrongTokenDoesNotMatch(): void
    {
        $guard = new CsrfGuard(new ArraySession());
        $guard->token();

        self::assertFalse($guard->matches('nope'));
        self::assertFalse($guard->matches(str_repeat('a', 64)));
    }

    /**
     * One session's token must be useless against another's, or the check
     * proves nothing about which browser sent the request.
     */
    public function testAnotherSessionsTokenDoesNotMatch(): void
    {
        $mine = new CsrfGuard(new ArraySession());
        $mine->token();
        $theirs = (new CsrfGuard(new ArraySession()))->token();

        self::assertFalse($mine->matches($theirs));
    }

    public function testNullAndEmptyAndNonStringsDoNotMatch(): void
    {
        $guard = new CsrfGuard(new ArraySession());
        $guard->token();

        self::assertFalse($guard->matches(null));
        self::assertFalse($guard->matches(''));
        self::assertFalse($guard->matches(['a']));
        self::assertFalse($guard->matches(42));
    }

    /**
     * Fails closed: a session that has never been issued a token refuses every
     * candidate rather than minting the value it is about to compare against.
     */
    public function testASessionWithNoTokenMatchesNothing(): void
    {
        $guard = new CsrfGuard(new ArraySession());

        self::assertFalse($guard->matches('anything'));
        self::assertFalse($guard->matches(''));
    }
}
