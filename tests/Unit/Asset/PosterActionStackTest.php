<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test — the sibling of StickyToolbarTest.
 *
 * A poster card's height is not chosen; it falls out of the grid's minimum column
 * width times the frame's 2:3 ratio. The action stack's height is not chosen
 * either; it falls out of the control's font size, padding and border, the gap
 * between controls, and the overlay's padding. Nothing connects the two, and the
 * overlay carries `overflow-y: auto`, so when the stack outgrows the card the
 * only symptom is that a hover quietly scrolls — no error, no failing test, and
 * nothing visible unless the viewport happens to sit at the width where the grid
 * hits its minimum.
 *
 * That is exactly what was happening before the requirement "Poster cards fit
 * their full action stack": at a 190px minimum, a poster linked to Plex showed
 * seven actions needing ~295px inside a 285px card.
 *
 * So the connection is asserted here instead. Any of these moving — a roomier
 * control, a bigger glyph, an eighth action, a smaller minimum — should fail
 * here rather than on someone's monitor.
 */
final class PosterActionStackTest extends TestCase
{
    /** Controls shown for a poster linked to Plex; the tallest the stack gets. */
    private const ACTIONS_WHEN_LINKED = 7;

    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return (string) preg_replace('#/\*.*?\*/#s', '', $source);
    }

    /** The declarations of one rule, found by its selector at the head of a line. */
    private function rule(string $selector): string
    {
        $pattern = '/^[ \t]*' . preg_quote($selector, '/') . '\s*(?:,[^{}]*)?\{([^{}]*)\}/m';
        $matched = preg_match($pattern, $this->stylesheet(), $m);
        self::assertSame(1, $matched, sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    private function number(string $css, string $property, string $unit = 'px'): float
    {
        $matched = preg_match('/' . preg_quote($property, '/') . ':\s*([\d.]+)' . preg_quote($unit, '/') . '/', $css, $m);
        self::assertSame(1, $matched, sprintf('Expected "%s" to declare %s.', $property, $property));

        return (float) $m[1];
    }

    /**
     * The glyph size lives in the template rather than the stylesheet, because it
     * is an argument to the icon macro. It still belongs to this calculation: an
     * icon taller than the control's line box would set the row height itself.
     */
    private function iconSize(): float
    {
        $path = dirname(__DIR__, 3) . '/templates/partials/gallery_results.html.twig';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery_results.html.twig must be readable.');

        $matched = preg_match('/icons\.icon\(glyph,\s*(\d+)\)/', $source, $m);
        self::assertSame(1, $matched, 'The action icon must declare its size.');

        return (float) $m[1];
    }

    /** Height of one action control, in px. */
    private function controlHeight(): float
    {
        $btn = $this->rule('.card__actions .btn');
        $fontRem = $this->number($btn, 'font-size', 'rem');
        $lineHeight = $this->number($this->rule('body'), 'line-height', '');

        // `padding: 6px 8px` — the vertical value is the first.
        $matched = preg_match('/padding:\s*([\d.]+)px/', $btn, $m);
        self::assertSame(1, $matched, 'The action control must declare padding.');
        $padY = (float) $m[1];

        // The taller of the text line box and the glyph sets the row.
        $lineBox = $fontRem * 16 * $lineHeight;

        return max($lineBox, $this->iconSize()) + (2 * $padY) + 2; // + 1px border each side
    }

    public function testTheGlyphNeverSetsTheControlHeight(): void
    {
        $btn = $this->rule('.card__actions .btn');
        $lineBox = $this->number($btn, 'font-size', 'rem') * 16
            * $this->number($this->rule('body'), 'line-height', '');

        self::assertLessThanOrEqual(
            $lineBox,
            $this->iconSize(),
            'A glyph taller than the line box makes every control taller, and the '
            . 'stack was sized on the assumption that adding icons cost no height.',
        );
    }

    public function testTheNarrowestCardFitsAFullyLinkedActionStack(): void
    {
        $stack = (self::ACTIONS_WHEN_LINKED * $this->controlHeight())
            + ((self::ACTIONS_WHEN_LINKED - 1) * $this->number($this->rule('.card__actions'), 'gap'))
            + (2 * $this->number($this->rule('.card__overlay'), 'padding'));

        // The grid's minimum column, and the frame's ratio that turns it into a height.
        $matched = preg_match('/minmax\(([\d.]+)px/', $this->rule('.grid'), $m);
        self::assertSame(1, $matched, 'The grid must declare a minimum column width.');
        $minColumn = (float) $m[1];

        $matched = preg_match('#aspect-ratio:\s*(\d+)\s*/\s*(\d+)#', $this->rule('.card__frame'), $r);
        self::assertSame(1, $matched, 'The card frame must declare its aspect ratio.');
        $frameHeight = $minColumn * ((float) $r[2] / (float) $r[1]);

        self::assertGreaterThanOrEqual(
            $stack,
            $frameHeight,
            sprintf(
                'A %.0fpx column gives a %.0fpx card, but a linked poster\'s %d actions need %.0fpx. '
                . 'The overlay would scroll. Raise the grid minimum to at least %.0fpx, or make the '
                . 'controls shorter.',
                $minColumn,
                $frameHeight,
                self::ACTIONS_WHEN_LINKED,
                $stack,
                ceil($stack / ((float) $r[2] / (float) $r[1])),
            ),
        );
    }
}
