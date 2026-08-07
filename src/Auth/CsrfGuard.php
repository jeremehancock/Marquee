<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\Scalar;
use App\Support\Session\SessionInterface;

/**
 * The secret that proves a state-changing request came from a page Marquee
 * rendered.
 *
 * One token per session, generated on first use and kept for the session's
 * life. Per-request rotation would defend against replaying a token already
 * read from a response — which requires the ability to read the response, at
 * which point same-origin is lost and the token is the smaller problem. It
 * would also make two tabs invalidate each other and leave the back button on a
 * page whose token is dead, which for a single-user self-hosted tool is a bad
 * trade.
 *
 * `matches()` never generates. A session with no token refuses every candidate,
 * so the check fails closed rather than minting the very value it is about to
 * compare against.
 */
final class CsrfGuard
{
    private const KEY = 'csrf_token';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    /**
     * The session's token, generating and storing one if it has none.
     */
    public function token(): string
    {
        $existing = Scalar::stringOrNull($this->session->get(self::KEY));
        if ($existing !== null) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->set(self::KEY, $token);

        return $token;
    }

    /**
     * Whether a submitted value is this session's token.
     *
     * Compared with `hash_equals` so that a wrong guess cannot be refined by
     * timing the comparison.
     */
    public function matches(mixed $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        $expected = Scalar::stringOrNull($this->session->get(self::KEY));
        if ($expected === null) {
            return false;
        }

        return hash_equals($expected, $candidate);
    }
}
