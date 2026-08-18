<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * The header's overflow menu, pinned where it is easy to get wrong by eye.
 *
 * Two of these look like housekeeping and are not.
 *
 * `position: absolute` is the whole reason the panel renders where it is drawn.
 * `.topbar` carries `backdrop-filter`, which makes it a containing block for
 * fixed-position descendants — the rule that already cost the phone tray a
 * teleport to `<body>` (see partials/_menu.html.twig). Switch this to `fixed` and
 * the panel resolves against the header's own box rather than the viewport, which
 * on a desktop viewport is subtle enough to survive review.
 *
 * The z-index is the other. The panel opens downward over the content region,
 * where the gallery's own head is pinned at 30 — and a positioned element paints
 * above an unpositioned in-flow ancestor whatever the document order says. Without
 * its own rung the menu is simply drawn behind the gallery's controls. This is not
 * covered by DesignTokenContractTest, which compares shadows to each other and
 * never reads a z-index out of the stylesheet.
 */
final class HeaderOverflowMenuTest extends TestCase
{
    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    /**
     * The declarations of one rule, found by a selector at the head of a selector
     * list. Comments are stripped first: this stylesheet explains itself heavily,
     * and a selector named in prose would otherwise match as though it were a rule.
     */
    private function rule(string $selector): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());
        $pattern = '/^[ \t]*' . preg_quote($selector, '/') . '\s*(?:,[^{}]*)?\{([^{}]*)\}/m';
        $matched = preg_match($pattern, $css, $m);
        self::assertSame(1, $matched, sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    private function zIndex(string $selector): int
    {
        $matched = preg_match('/z-index:\s*(\d+)/', $this->rule($selector), $m);
        self::assertSame(1, $matched, sprintf('Expected "%s" to declare a z-index.', $selector));

        return (int) $m[1];
    }

    public function testThePanelIsAbsoluteAgainstItsOwnWrapperRatherThanFixed(): void
    {
        $panel = $this->rule('.navmenu__panel');

        self::assertStringContainsString('position: absolute', $panel);
        self::assertStringNotContainsString(
            'position: fixed',
            $panel,
            'The header is a containing block for fixed descendants; a fixed panel '
            . 'resolves against the header box, not the viewport.',
        );

        // Absolute positioning is only meaningful against a positioned ancestor.
        // Without this the panel would escape to the nearest one it can find.
        self::assertStringContainsString('position: relative', $this->rule('.navmenu'));
    }

    public function testThePanelIsDrawnOverThePagesOwnPinnedControls(): void
    {
        self::assertGreaterThan(
            $this->zIndex('.gallery-head'),
            $this->zIndex('.navmenu__panel'),
            'The menu opens over the content region the gallery head is pinned in, '
            . 'so it must out-stack it or it is simply drawn behind.',
        );
    }

    /**
     * Above the pinned controls, and no higher. The tab bar, the trays and the
     * dialogs all cover this menu; borrowing one of their rungs would let a header
     * popover sit over an open dialog.
     */
    public function testThePanelStaysBelowTheSurfacesThatCoverIt(): void
    {
        $panel = $this->zIndex('.navmenu__panel');

        foreach (['.sheet' => 'a tray', '.modal' => 'a dialog'] as $selector => $what) {
            self::assertLessThan(
                $this->zIndex($selector),
                $panel,
                sprintf('The header menu must be covered by %s.', $what),
            );
        }
    }

    /**
     * Every rule that applies in the narrow-desktop band, brace-matched.
     *
     * All of them, because the band is written as more than one block — the
     * connection status drops its name in one, the nav items drop their labels in
     * another — and the constraint being asserted is about the band as a whole. A
     * slice of the first block found would pass while a descendant selector sat in
     * the second, which is precisely the mistake this file exists to catch.
     */
    private function labelDroppingBand(): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());
        $query = '@media (min-width: 641px) and (max-width: 900px)';

        $band = '';
        $from = 0;
        while (($start = strpos($css, $query, $from)) !== false) {
            $depth = 0;
            for ($i = (int) strpos($css, '{', $start), $len = strlen($css); $i < $len; $i++) {
                if ($css[$i] === '{') {
                    $depth++;
                } elseif ($css[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $band .= substr($css, $start, $i - $start + 1);
                        $from = $i;
                        continue 2;
                    }
                }
            }

            self::fail('A narrow-desktop media query is unclosed.');
        }

        self::assertNotSame('', $band, 'The narrow-desktop band must exist.');

        return $band;
    }

    /**
     * The narrow-desktop fallback drops the bar's labels down to icons. The menu is
     * a list of full names at every width — the same list the phone tray shows —
     * and it is a descendant of the same container, so the fallback has to be
     * confined to the bar by a child combinator rather than reaching everything
     * under it.
     */
    public function testTheIconOnlyFallbackDoesNotReachIntoTheMenu(): void
    {
        $band = $this->labelDroppingBand();

        // The band is what it says it is, not an empty slice that would agree with
        // anything asked of it.
        self::assertStringContainsString('.nav-label', $band);
        self::assertStringContainsString('padding: 8px', $band);

        self::assertStringContainsString(
            '.topnav__desktop > .nav-item',
            $band,
            'Confined to the bar with a child combinator; a descendant selector here '
            . 'strips the labels off the overflow menu rows in this band.',
        );
        self::assertDoesNotMatchRegularExpression(
            '/^[ \t]*\.topnav__desktop \.nav-item/m',
            $band,
            'A descendant selector in this band reaches into the overflow panel.',
        );
    }
}
