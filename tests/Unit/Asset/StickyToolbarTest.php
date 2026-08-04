<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test — the sibling of TrayDismissalTest.
 *
 * On a phone the gallery toolbar is pinned, which turns three ordinary-looking
 * declarations into load-bearing ones. Drop the background and posters scroll
 * straight through the bar, because .toolbar has none of its own. Drop the
 * negative side margins and they show through a 14px channel down each edge,
 * where .container's gutters sit outside the bar's own background. Raise the
 * z-index above the tab bar or the trays and an open overlay no longer covers
 * it. Each of those reads as a rendering bug rather than a missing rule, and
 * none is visible from a desktop viewport.
 *
 * Whether the bar actually stays put while scrolling is verified by hand against
 * the :dev image; this pins the arrangement that lets it.
 */
final class StickyToolbarTest extends TestCase
{
    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    /**
     * The phone rules live in one `@media (max-width: 640px)` block at the end of
     * the stylesheet, so it can restyle components defined above it at equal
     * specificity.
     */
    private function mobileBlock(): string
    {
        $css = $this->stylesheet();
        $start = strpos($css, '@media (max-width: 640px) {');
        self::assertIsInt($start, 'The mobile block must remain a single @media (max-width: 640px) section.');

        return substr($css, $start);
    }

    /**
     * Everything above the mobile block: the base rules the phone overrides win
     * against. Used to prove a declaration is phone-only.
     */
    private function baseBlock(): string
    {
        $css = $this->stylesheet();
        $start = strpos($css, '@media (max-width: 640px) {');
        self::assertIsInt($start, 'The mobile block must remain a single @media (max-width: 640px) section.');

        return substr($css, 0, $start);
    }

    /**
     * The declarations of one rule, found by a selector appearing at the head of
     * a selector list. These rules contain no nested braces.
     *
     * Comments go first: this stylesheet explains itself heavily, and a selector
     * named in prose would otherwise be matched as though it were a rule.
     */
    private function rule(string $css, string $selector): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        // Anchored to the start of a selector line so a descendant selector is
        // never mistaken for the rule it overrides.
        $pattern = '/^[ \t]*' . preg_quote($selector, '/') . '\s*(?:,[^{}]*)?\{([^{}]*)\}/m';
        $matched = preg_match($pattern, $css, $m);
        self::assertSame(1, $matched, sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    private function zIndex(string $css, string $selector): int
    {
        $matched = preg_match('/z-index:\s*(\d+)/', $this->rule($css, $selector), $m);
        self::assertSame(1, $matched, sprintf('Expected "%s" to declare a z-index.', $selector));

        return (int) $m[1];
    }

    public function testToolbarIsPinnedOnAPhone(): void
    {
        $toolbar = $this->rule($this->mobileBlock(), '.toolbar');

        // `sticky` rather than `fixed`: it keeps the bar in flow, so the grid
        // below needs no compensating padding kept in sync with the bar's height.
        self::assertStringContainsString(
            'position: sticky',
            $toolbar,
            'Search and sort must stay reachable at any scroll position.',
        );
        self::assertStringContainsString(
            'top: 0',
            $toolbar,
            'A sticky element without an inset never pins.',
        );
    }

    public function testPinnedToolbarHidesThePostersPassingUnderIt(): void
    {
        $toolbar = $this->rule($this->mobileBlock(), '.toolbar');

        // .toolbar has no background of its own, so without this the posters
        // scroll visibly through the pinned bar.
        self::assertStringContainsString(
            'background: var(--bg)',
            $toolbar,
            'The pinned toolbar must be opaque.',
        );
    }

    public function testPinnedToolbarBleedsPastTheContentGutters(): void
    {
        $mobile = $this->mobileBlock();

        // .container's side padding sits outside the toolbar's own background,
        // so without the bleed a strip of poster shows down each edge as it
        // scrolls past. The padding puts the contents back on the content grid.
        $gutter = preg_match('/padding:\s*\d+px\s+(\d+)px/', $this->rule($mobile, '.container'), $m);
        self::assertSame(1, $gutter, 'The mobile container must declare a side gutter.');
        $side = (int) $m[1];

        $toolbar = $this->rule($mobile, '.toolbar');
        self::assertMatchesRegularExpression(
            '/margin:\s*0\s+-' . $side . 'px/',
            $toolbar,
            sprintf('The pinned toolbar must bleed past the container\'s %dpx gutter.', $side),
        );
        self::assertMatchesRegularExpression(
            '/padding:\s*\d+px\s+' . $side . 'px/',
            $toolbar,
            'The bleed must be matched by equal padding, or the contents leave the content grid.',
        );
    }

    public function testEveryOverlayCoversThePinnedToolbar(): void
    {
        $css = $this->stylesheet();

        $toolbar = $this->zIndex($this->mobileBlock(), '.toolbar');
        $tabs = $this->zIndex($this->mobileBlock(), '.tabs');
        $sheet = $this->zIndex($css, '.sheet');

        // The toolbar is page chrome, not an overlay: it only has to cover the
        // poster grid. The scale above it (tabs 40, trays 50, dialogs 55, viewer
        // 60) is asserted in TrayDismissalTest.
        self::assertGreaterThan($toolbar, $tabs, 'The bottom tab bar must cover the pinned toolbar.');
        self::assertGreaterThan($toolbar, $sheet, 'An open tray must cover the pinned toolbar.');
    }

    public function testDesktopToolbarStillScrollsWithThePage(): void
    {
        // Pinning is a phone affordance. The desktop toolbar sits in a 960px
        // column with the whole viewport to scroll; pinning it there would only
        // spend vertical space.
        self::assertStringNotContainsString(
            'position: sticky',
            $this->rule($this->baseBlock(), '.toolbar'),
            'The desktop toolbar must keep scrolling with the page.',
        );
    }
}
