<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * On a phone every overlay is a tray, and a tray has exactly two ways out: drag
 * it down by the grab handle, or tap the backdrop. There is deliberately no
 * close button. That makes a handful of stylesheet declarations load-bearing in
 * a way nothing else would catch — drop `touch-action: none` from the drag
 * region, or let the panel become the scroller again, and the browser claims the
 * downward drag as a scroll, chains it out to the document, and turns it into a
 * page scroll or a pull-to-refresh. The tray then reads as stuck, which is
 * exactly the bug this change fixed.
 *
 * None of that is verifiable without a real touch device, and this repo has no
 * JS test runner. What is worth catching cheaply is a later edit quietly undoing
 * the arrangement that makes the gesture work. These assertions pin that
 * arrangement; the gesture itself is verified by hand against the :dev image.
 */
final class TrayDismissalTest extends TestCase
{
    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

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
     * The declarations of one rule, found by a selector appearing at the head of
     * a selector list. These rules contain no nested braces.
     *
     * Comments go first: this stylesheet explains itself heavily, and a selector
     * named in prose would otherwise be matched as though it were a rule.
     */
    private function rule(string $css, string $selector): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        // Anchored to the start of a selector line so a descendant selector
        // (`.modal .sheet__grip`) is never mistaken for the rule it overrides.
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

    /**
     * A tray nested inside a glass surface must be teleported out of it.
     *
     * `backdrop-filter` makes an element a containing block for its
     * fixed-position descendants — the same rule that catches `transform`,
     * `filter` and `perspective`, and the reason this is worth a tripwire: the
     * two declarations involved are in different files, neither looks wrong on
     * its own, and the CSS one is nowhere near the markup it breaks.
     *
     * The app-wide actions tray is markup inside <header class="topbar">, because
     * that is where its `menuOpen` scope lives. When the header went translucent,
     * the tray's `position: fixed; inset: 0` stopped resolving against the
     * viewport and started resolving against the header — so the full-screen tray
     * rendered squashed into the height of the bar that opened it. x-teleport
     * moves it to <body> while leaving it in scope.
     *
     * Stated as an implication rather than a flat assertion: it is the glass that
     * creates the requirement, so taking the glass off the header would release
     * it. Written this way the test explains itself when it fails.
     */
    public function testATrayInsideGlassChromeIsTeleportedOutOfIt(): void
    {
        $topbar = $this->stylesheet();
        $start = strpos($topbar, '.topbar {');
        self::assertIsInt($start, 'The header rule must remain findable.');
        $rule = substr($topbar, $start, (int) strpos($topbar, '}', $start) - $start);

        if (!str_contains($rule, 'backdrop-filter')) {
            self::markTestSkipped('The header is no longer a glass surface, so it '
                . 'is no longer a containing block for the tray inside it.');
        }

        $menu = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/_menu.html.twig');
        self::assertIsString($menu);

        self::assertStringContainsString(
            'x-teleport="body"',
            $menu,
            'The header declares backdrop-filter, which makes it a containing block '
            . 'for fixed-position descendants. The actions tray is fixed and lives '
            . 'inside it, so without x-teleport it renders inside the header\'s box '
            . 'instead of over the viewport — a phone-only break, invisible from a '
            . 'desktop viewport because the tray is hidden there.',
        );

        // The teleport has to wrap the tray, not sit inside it: Alpine moves the
        // <template>'s contents, so a template nested within .sheet would move the
        // contents and leave the fixed element behind.
        self::assertLessThan(
            (int) strpos($menu, '<div class="sheet"'),
            (int) strpos($menu, 'x-teleport="body"'),
            'The teleport must wrap the tray, or the fixed element stays put.',
        );
    }

    public function testDragRegionsOptOutOfBrowserGestures(): void
    {
        $css = $this->stylesheet();

        // Without this the browser treats a downward drag as a scroll and takes
        // the gesture over, cancelling the drag partway.
        foreach (['.sheet__grip', '.sheet__head'] as $selector) {
            self::assertStringContainsString(
                'touch-action: none',
                $this->rule($css, $selector),
                sprintf('The tray drag region "%s" must keep touch-action: none.', $selector)
            );
        }

        self::assertStringContainsString(
            'touch-action: none',
            $this->rule($this->mobileBlock(), '.modal__head'),
            'A modal presented as a tray must keep touch-action: none on its drag region.'
        );
    }

    public function testTrayPanelIsNotItsOwnScroller(): void
    {
        $mobile = $this->mobileBlock();
        $panel = $this->rule($mobile, '.modal__panel');

        // A single element cannot both scroll and carry touch-action: none. The
        // panel clips; a separate body scrolls.
        self::assertStringContainsString(
            'overflow: hidden',
            $panel,
            'The tray panel must clip, leaving the scrolling to its body.'
        );
        self::assertStringNotContainsString(
            'overflow-y: auto',
            $panel,
            'The tray panel must not become the scroller again — that is what broke the drag.'
        );
        self::assertStringContainsString(
            'overflow-y: auto',
            $this->rule($mobile, '.modal__body'),
            'The tray body must be the scrolling region.'
        );
    }

    public function testGrabHandleIsARealElementNotAPseudoElement(): void
    {
        // A ::before can never be a touch target, so a handle drawn that way
        // cannot be grabbed however inviting it looks.
        self::assertStringNotContainsString(
            '.modal__panel::before',
            $this->stylesheet(),
            'The grab handle must stay a real element (.sheet__grip) that touch can target.'
        );
    }

    /**
     * Containment is necessary and is not sufficient, and the difference is worth
     * stating here because this is the test a reader lands on when wondering
     * whether a tray's scrolling is fully accounted for.
     *
     * These assertions say a flick that reaches the end of a scroller stops
     * there. They say nothing about whether everything the tray holds can be
     * reached in the first place. Nest one scroller inside another and the two
     * combine badly: containment stops the gesture at the inner region's end
     * instead of handing the rest to the outer one, so whatever the outer region
     * holds beyond the inner box becomes unreachable — which is exactly how the
     * last row of Find Posters went missing on a phone while every assertion
     * below still passed.
     *
     * So poster-library excludes nesting outright and separately requires a
     * tray's contents to be reachable in full. PosterGroupsTest::
     * testTheTrayBodyOwnsTheScrollOnAPhone pins the one case that exists today;
     * neither test can catch a new nested scroller introduced elsewhere.
     */
    public function testTrayScrollersContainTheirOverscroll(): void
    {
        $css = $this->stylesheet();

        self::assertStringContainsString(
            'overscroll-behavior: contain',
            $this->rule($css, '.sheet__body'),
            'A tray body must not hand a finished flick to the page behind it.'
        );
        self::assertStringContainsString(
            'overscroll-behavior: contain',
            $this->rule($css, '.find-grid'),
            'The Find Posters grid must not hand a finished flick to the page behind it.'
        );
        self::assertStringContainsString(
            'overscroll-behavior: contain',
            $this->rule($this->mobileBlock(), '.modal__body'),
            'A modal presented as a tray must contain its overscroll too.'
        );
    }

    public function testTrayLeavesBackdropToTap(): void
    {
        // The backdrop is one of only two ways out, so a tray must not grow tall
        // enough to squeeze it down to a sliver. 85vh matches .sheet__panel.
        self::assertStringContainsString(
            'max-height: 85vh',
            $this->rule($this->mobileBlock(), '.modal__panel'),
            'A modal presented as a tray must be no taller than a tray.'
        );
    }

    public function testNoTrayGainsACloseButtonOnAPhone(): void
    {
        self::assertStringContainsString(
            'display: none',
            $this->rule($this->mobileBlock(), '.modal__close'),
            'Trays dismiss by handle and backdrop; the × belongs to the desktop dialog.'
        );
    }

    public function testDialogsLayerAboveTrays(): void
    {
        $css = $this->stylesheet();

        $sheet = $this->zIndex($css, '.sheet');
        $modal = $this->zIndex($css, '.modal');
        $viewer = $this->zIndex($css, '.viewer');

        // A confirmation can be raised from inside a tray — deleting an orphan
        // from the orphans tray does it — and one rendered behind the tray that
        // asked the question cannot be answered.
        self::assertGreaterThan($sheet, $modal, 'A dialog must render above the tray that raised it.');
        self::assertGreaterThan($modal, $viewer, 'The fullscreen viewer must cover both.');
    }

    public function testDragHandlerNeedsNoPerOverlaySpecialCase(): void
    {
        $source = $this->gallerySource();

        self::assertStringContainsString(
            ".closest('.sheet__grip, .sheet__head, .modal__head')",
            $source,
            'The drag gesture must keep resolving its target by the shared tray classes.'
        );
        // The old fallback existed only because the handle was a pseudo-element
        // with nothing to target. A real grip replaced it.
        self::assertStringNotContainsString(
            "contains('modal__panel')",
            $source,
            'The pseudo-element fallback is dead once every tray has a real grab handle.'
        );
    }

    public function testScrollLockRestoresTheExactOffset(): void
    {
        $source = $this->gallerySource();

        self::assertStringContainsString(
            'is-overlay-open',
            $source,
            'The scroll lock must toggle the class the stylesheet pins the page with.'
        );
        self::assertStringContainsString(
            'is-overlay-open',
            $this->stylesheet(),
            'The stylesheet must pin the page for the class the scroll lock sets.'
        );
        // An approximate restore would land the infinite-scroll sentinel back in
        // view and append posters the user never scrolled to.
        self::assertStringContainsString(
            'window.scrollTo(0, scrollY)',
            $source,
            'Closing an overlay must restore the captured offset exactly.'
        );
    }

    public function testPageIsPinnedRatherThanOverflowHidden(): void
    {
        // `overflow: hidden` on the body does not reliably stop scrolling on iOS
        // Safari, and `overscroll-behavior` is not honoured on the document there.
        self::assertStringContainsString(
            'position: fixed',
            $this->rule($this->stylesheet(), '.is-overlay-open body'),
            'The page must be pinned, which is the only lock that holds on iOS Safari.'
        );
    }
}
