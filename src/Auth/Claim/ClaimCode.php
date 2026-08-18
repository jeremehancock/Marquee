<?php

declare(strict_types=1);

namespace App\Auth\Claim;

/**
 * The code that proves someone had access to the host before they claimed the
 * install.
 *
 * 130 bits, against a specified floor of 20. The floor assumed a rate limit was
 * doing the work; 20 bits is a million codes, which the web server's allowance
 * would surrender in a fortnight of steady guessing. At this size guessing stops
 * being an attack at all, and the rate limits become what they should be —
 * defence in depth rather than the thing the install rests on.
 *
 * It costs nothing to be this long. Nobody memorises a claim code: they copy it
 * out of a file or a log line, once, and never see it again.
 *
 * Crockford base32 rather than hex or base64. It excludes the character pairs
 * that get misread aloud or across a font — I and 1, O and 0, U entirely — and
 * it is case-insensitive on the way back in, so a code read off a terminal and
 * typed into a phone survives the trip. Hyphenated into groups of four for the
 * same reason: a 26-character run is hard to check by eye and easy to lose your
 * place in.
 */
final class ClaimCode
{
    /**
     * Crockford's alphabet: no I, L, O, or U.
     */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * Characters in a code. Each is one of 32 symbols drawn uniformly, so this
     * is 5 bits apiece — 130 bits in total.
     */
    private const LENGTH = 26;

    private const GROUP_SIZE = 4;

    /**
     * A new code, drawn from the cryptographic random source.
     *
     * One symbol at a time rather than packing bits out of `random_bytes()`.
     * `random_int()` draws uniformly from the range it is given, so each
     * character is unbiased by construction and there is no modulo skew to
     * reason about — worth more here than the handful of microseconds that
     * bit-packing would save, in a function that runs once per install.
     */
    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        return implode('-', str_split($code, self::GROUP_SIZE));
    }

    /**
     * Reduce a code to the form two of them can be compared in.
     *
     * Hyphens, spaces, and case are presentation. Someone pasting a code from a
     * log line may bring a trailing newline; someone typing it may use spaces
     * instead of hyphens or lower case throughout. None of that should be the
     * difference between claiming an install and being told to check the code.
     */
    public static function normalize(string $code): string
    {
        $normalized = strtoupper($code);

        return (string) preg_replace('/[^0-9A-Z]/', '', $normalized);
    }

    /**
     * Whether a submitted code matches the stored one.
     *
     * Compared in constant time. The entropy makes timing analysis pointless
     * here, but a credential comparison that is not constant-time is a habit
     * worth not having.
     */
    public static function matches(string $submitted, string $stored): bool
    {
        $submitted = self::normalize($submitted);
        $stored = self::normalize($stored);

        if ($submitted === '' || $stored === '') {
            return false;
        }

        return hash_equals($stored, $submitted);
    }
}
