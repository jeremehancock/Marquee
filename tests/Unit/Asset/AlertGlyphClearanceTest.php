<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, in the family of {@see TraySurfaceTest}.
 *
 * `.alert` paints its icon as an absolutely positioned `::before` rather than
 * markup, which is what keeps one stylesheet serving both the bare `<p>` flashes
 * and the multi-paragraph notices on the connection screen. The cost is that the
 * icon is out of flow: nothing pushes text away from it, and the only thing
 * keeping the two apart is `.alert`'s left padding.
 *
 * That makes the padding load-bearing and invisible. `.plex-connection__obsolete`
 * said `padding: 16px 18px`, which reads as a complete and tidy declaration and
 * is not — the shorthand reset the 44px left padding to 18px, and the first line
 * of every notice on that screen ran underneath the warning triangle. It shipped
 * that way because the notices only appear on an upgraded install with a stale
 * compose file, which is not the state anyone develops against.
 *
 * So the assertion is about a shorthand, not about a number. Any rule that
 * overrides `.alert`'s padding must state all four sides, because a two-value
 * shorthand cannot help but clear the side the glyph occupies. Stated that way
 * the bug cannot come back by re-tuning the spacing.
 */
final class AlertGlyphClearanceTest extends TestCase
{
    /**
     * How wide the glyph's column is: `.alert::before` is 19px wide and sits at
     * a left offset. Anything narrower than the offset plus the glyph puts text
     * over the icon.
     */
    private const GLYPH_WIDTH = 19;

    public function testTheAlertReservesRoomForItsGlyph(): void
    {
        $sides = $this->paddingSidesOf('.alert');

        self::assertCount(
            4,
            $sides,
            '.alert must state all four padding sides: its left padding is the room the '
            . 'absolutely positioned ::before glyph stands in, and a shorter shorthand hides that.',
        );

        self::assertGreaterThan(
            $this->offset('.alert::before', 'left') + self::GLYPH_WIDTH,
            $this->pixels($sides[3]),
            '.alert must pad further from the left than its glyph reaches, or text runs under the icon.',
        );
    }

    /**
     * The variant that got this wrong. It is taller and more padded than a
     * flash, so it restates the padding — and restating it is exactly where the
     * left side goes missing.
     */
    public function testTheConnectionNoticeKeepsThatRoomWhenItRepadsItself(): void
    {
        $sides = $this->paddingSidesOf('.plex-connection__obsolete');

        self::assertCount(
            4,
            $sides,
            'A two- or three-value shorthand here resets .alert\'s left padding and puts the '
            . 'first line of the notice underneath the warning glyph. State all four sides.',
        );

        self::assertGreaterThan(
            $this->offset('.plex-connection__obsolete::before', 'left') + self::GLYPH_WIDTH,
            $this->pixels($sides[3]),
            'The notice must clear its own glyph.',
        );
    }

    /**
     * The glyph is pinned to the first line rather than centred, so a variant
     * that changes its top padding has to move the glyph with it — otherwise the
     * icon drifts off the line it is pointing at.
     */
    public function testTheNoticeMovesItsGlyphToMatchItsOwnTopPadding(): void
    {
        $alertTop = $this->pixels($this->paddingSidesOf('.alert')[0]);
        $noticeTop = $this->pixels($this->paddingSidesOf('.plex-connection__obsolete')[0]);

        $drift = $this->offset('.plex-connection__obsolete::before', 'top')
            - $this->offset('.alert::before', 'top');

        self::assertSame(
            $noticeTop - $alertTop,
            $drift,
            'The glyph sits a fixed distance below the top padding. A variant that pads '
            . 'differently must shift the glyph by the same amount or it leaves the first line.',
        );
    }

    /**
     * The padding sides a selector declares, in source order.
     *
     * @return list<string>
     */
    private function paddingSidesOf(string $selector): array
    {
        $value = $this->capture($this->rule($selector), '/padding:\s*([^;]+);/');
        self::assertNotSame('', $value, $selector . ' must declare padding');

        return array_values(array_filter(preg_split('/\s+/', trim($value)) ?: []));
    }

    private function offset(string $selector, string $property): int
    {
        $value = $this->capture($this->rule($selector), '/' . $property . ':\s*(-?[\d.]+)px/');
        self::assertNotSame('', $value, $selector . ' must declare ' . $property);

        return (int) $value;
    }

    private function pixels(string $value): int
    {
        return (int) rtrim(trim($value), 'px');
    }

    /**
     * The declaration block for one selector, matched on the selector standing
     * alone so `.alert` does not also match `.alert--warning`.
     */
    private function rule(string $selector): string
    {
        $block = $this->capture(
            $this->stylesheet(),
            '/(?:^|[},])\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m',
        );
        self::assertNotSame('', $block, 'No rule found for ' . $selector);

        return $block;
    }

    /**
     * The first capture group, or an empty string when the pattern did not match.
     *
     * One accessor so that every caller gets a plain string rather than a match
     * array whose shape has to be re-narrowed at each use.
     */
    private function capture(string $subject, string $pattern): string
    {
        if (preg_match($pattern, $subject, $match) !== 1) {
            return '';
        }

        return $match[1] ?? '';
    }

    private function stylesheet(): string
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/public/assets/app.css');
        self::assertNotFalse($css);

        return $css;
    }
}
