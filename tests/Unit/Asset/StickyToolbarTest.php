<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test — the sibling of TrayDismissalTest.
 *
 * The gallery's controls are pinned on both form factors, but not the same ones:
 * a phone pins .toolbar alone, because its category tabs are already permanently
 * on screen as a bottom bar, while a pointer/desktop screen pins .gallery-head —
 * the tabs and the toolbar together. Pinning turns several ordinary-looking
 * declarations into load-bearing ones. Drop the background and posters scroll
 * straight through the bar, because neither .toolbar nor .tabs has one of its
 * own. Drop the phone's negative side margins and they show through a 14px
 * channel down each edge, where .container's gutters sit outside the bar's own
 * background. Raise the z-index above the tab bar or the trays and an open
 * overlay no longer covers it. Each of those reads as a rendering bug rather
 * than a missing rule.
 *
 * The sharpest trap is the wrapper. A sticky element cannot travel outside its
 * containing block, so wrapping the phone's already-sticky .toolbar in a short
 * .gallery-head would cut its range to that wrapper's height and unpin it after
 * about one tab bar of scrolling — a phone regression caused entirely by desktop
 * CSS, and invisible from a desktop viewport. `display: contents` on the phone is
 * what prevents it, which is why it is asserted here rather than assumed.
 *
 * Whether the bars actually stay put while scrolling is verified by hand against
 * the :dev image; this pins the arrangement that lets them.
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

    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    /**
     * A consequence of pinning the toolbar, which is why it is asserted here.
     *
     * Before the toolbar was pinned, searching from part-way down the gallery
     * meant scrolling back up to reach the search box, and that scroll was what
     * put the user at the top of the results. Pinning removed the trip and with
     * it the side effect: the new matches would swap in underneath a scroll
     * offset belonging to the previous, longer list, leaving the user somewhere
     * in the middle of the results or past the end of them entirely.
     */
    public function testSearchReturnsToTheTopOfTheResults(): void
    {
        $source = $this->gallerySource();

        $matched = preg_match(
            '/search\.addEventListener\(\x27input\x27.*?\n            \}\);/s',
            $source,
            $m,
        );
        self::assertSame(1, $matched, 'The live-search input handler must be findable.');
        $handler = $m[0];

        self::assertStringContainsString(
            'scrollToTopOfGallery()',
            $handler,
            'A new result set is read from the top, the same as paging and switching category.',
        );
        // Going through the shared helper is the point: it carries the
        // reduced-motion branch, which a bare scrollTo here would bypass.
        self::assertStringNotContainsString(
            'window.scrollTo',
            $handler,
            'The reset must go through the shared helper, not a second implementation.',
        );
    }

    public function testDesktopPinsTheTabsAndToolbarTogether(): void
    {
        $head = $this->rule($this->baseBlock(), '.gallery-head');

        self::assertStringContainsString(
            'position: sticky',
            $head,
            'Search, sort and every category must stay reachable at any scroll position.',
        );
        self::assertStringContainsString(
            'top: 0',
            $head,
            'A sticky element without an inset never pins.',
        );
    }

    public function testPinnedDesktopControlsHideThePostersPassingUnderThem(): void
    {
        // Neither .tabs nor .toolbar has a background, so the wrapper has to
        // supply one or the grid scrolls visibly through the pinned block. No
        // gutter bleed is needed as it is on a phone: the poster grid sits inside
        // .container's padding box, so nothing ever renders beside this block.
        self::assertStringContainsString(
            'background: var(--bg)',
            $this->rule($this->baseBlock(), '.gallery-head'),
            'The pinned desktop controls must be opaque.',
        );
    }

    public function testDesktopToolbarIsNotPinnedIndependentlyOfTheWrapper(): void
    {
        // Two siblings both stuck to top: 0 would land on top of each other. The
        // wrapper is the sticky one; .toolbar must stay in flow inside it.
        self::assertStringNotContainsString(
            'position: sticky',
            $this->rule($this->baseBlock(), '.toolbar'),
            'Pinning the desktop toolbar as well would stack it under the tabs.',
        );
    }

    public function testEveryOverlayCoversThePinnedDesktopControls(): void
    {
        $css = $this->stylesheet();

        $head = $this->zIndex($this->baseBlock(), '.gallery-head');
        $sheet = $this->zIndex($css, '.sheet');
        $modal = $this->zIndex($css, '.modal');
        $viewer = $this->zIndex($css, '.viewer');

        self::assertGreaterThan($head, $sheet, 'An open tray must cover the pinned controls.');
        self::assertGreaterThan($head, $modal, 'A dialog must cover the pinned controls.');
        self::assertGreaterThan($head, $viewer, 'The fullscreen viewer must cover the pinned controls.');
    }

    /**
     * The one that matters most, and the one whose absence is hardest to spot: it
     * is a phone bug with no phone-side cause, introduced by a wrapper added for
     * desktop. See the class docblock.
     */
    public function testTheWrapperLeavesTheBoxTreeOnAPhone(): void
    {
        self::assertStringContainsString(
            'display: contents',
            $this->rule($this->mobileBlock(), '.gallery-head'),
            'The wrapper must not become the phone toolbar\'s containing block, '
            . 'or its sticky range collapses to the wrapper\'s own height.',
        );
    }
}
