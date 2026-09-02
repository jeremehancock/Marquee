<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * Paging swaps the grid in place, so the browser never navigates and never
 * resets the scroll position — the user stays parked on the pagination control
 * they just clicked while a whole new page renders above them. The fix returns
 * the view to the top, smoothly, starting at click time so the motion overlaps
 * the fetch.
 *
 * Where the viewport actually ends up, and whether it glides or jumps, is
 * browser behavior; this repo has no JS test runner, and the animation is
 * verified by hand against the :dev image. What these assertions pin is the
 * arrangement that makes it work, and one thing that is easy to break from a
 * distance: the smooth scroll must stay local to this one interaction. A global
 * `scroll-behavior: smooth` would animate every programmatic scroll, including
 * the overlay scroll lock's restore, which would show the page sliding back into
 * place each time a tray is dismissed.
 */
final class PaginationScrollTest extends TestCase
{
    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    /**
     * The delegated handler's pagination branch, from the link lookup to the
     * `return` that ends it.
     */
    private function paginationBranch(): string
    {
        $source = $this->gallerySource();

        $start = strpos($source, "var pageLink = e.target.closest('.pagination a');");
        self::assertIsInt($start, 'The delegated click handler must keep a branch for pagination links.');

        $end = strpos($source, 'return;', $start);
        self::assertIsInt($end, 'The pagination branch must end in a return.');

        return substr($source, $start, $end - $start);
    }

    /**
     * The body of the scroll helper. It is a module-level function, so its
     * closing brace is the first one at that indent.
     */
    private function scrollHelper(): string
    {
        $source = $this->gallerySource();

        $start = strpos($source, 'function scrollToTopOfGallery() {');
        self::assertIsInt($start, 'The scroll to top must stay a named helper, so one place owns the reduced-motion check.');

        $end = strpos($source, "\n    }", $start);
        self::assertIsInt($end, 'The scroll helper must remain a module-level function.');

        return substr($source, $start, $end - $start);
    }

    public function testPagingReturnsTheViewToTheTop(): void
    {
        self::assertStringContainsString(
            'scrollToTopOfGallery()',
            $this->paginationBranch(),
            'Following a pagination link must return the view to the top of the gallery.'
        );
    }

    public function testTheScrollStartsBeforeTheNewPageIsFetched(): void
    {
        $branch = $this->paginationBranch();

        $scroll = strpos($branch, 'scrollToTopOfGallery()');
        $load = strpos($branch, 'load(');
        self::assertIsInt($scroll);
        self::assertIsInt($load);

        // Scrolling after the swap would leave the old page sitting still for a
        // network round trip, then jump — and would start the animation at the
        // exact moment the grid is being replaced.
        self::assertLessThan(
            $load,
            $scroll,
            'The scroll must be issued before the fetch so it overlaps the load rather than following it.'
        );
    }

    public function testReducedMotionDropsTheAnimationButNotTheScroll(): void
    {
        $helper = $this->scrollHelper();

        // Read inside the helper, not cached at startup: a user who changes the
        // system setting mid-session gets the new behavior without a reload.
        self::assertStringContainsString(
            "matchMedia('(prefers-reduced-motion: reduce)')",
            $helper,
            'The scroll helper must consult the reduced-motion preference on each call.'
        );
        self::assertStringContainsString(
            "'auto'",
            $helper,
            'Reduced motion must resolve to an instant scroll, not a smooth one.'
        );
        // Arriving at the top is the requirement; only the animation is a
        // preference, so the helper must always scroll.
        self::assertStringContainsString(
            'window.scrollTo({ top: 0,',
            $helper,
            'The helper must scroll to the top whatever the motion preference.'
        );
    }

    /**
     * Changing category has to leave the reader at the top of the new view, and
     * on a phone that takes two separate things — which is the whole reason this
     * is pinned.
     *
     * `window.scrollTo(0, 0)` covers the ordinary case. It does nothing at all
     * when an overlay still holds the scroll lock, because the lock pins the body
     * with `position: fixed` and a pinned document has no scroll to set. The lock
     * then restores the offset it captured when the overlay opened, and that
     * offset is the last word on where the page ends up.
     *
     * So a category change also has to tell the lock that the offset no longer
     * describes anything. Reachable from the phone action sheet: Related posters
     * closes the sheet and lands on the All view, and without the reset the reader
     * arrives part-way down a list they have never seen.
     */
    public function testChangingCategoryResetsTheScrollLocksAnchor(): void
    {
        $source = $this->gallerySource();

        $start = strpos($source, 'function switchCategory(pathname, options) {');
        self::assertIsInt($start, 'switchCategory() must exist — it is the one way to change category.');
        $end = strpos($source, 'primeNeighbours();', $start);
        self::assertIsInt($end);
        $body = substr($source, $start, $end - $start);

        self::assertStringContainsString(
            'window.scrollTo(0, 0)',
            $body,
            'Changing category must return the view to the top.',
        );
        self::assertStringContainsString(
            "dispatch('gallery:scroll-anchor-reset'",
            $body,
            'Changing category must also clear the scroll lock\'s captured offset, which '
            . 'otherwise restores the previous view\'s position over the top of the new one.',
        );
    }

    /**
     * The other half of the same arrangement: the lock has to listen, and the
     * listener has to zero the offset the restore reads. Asserted against the
     * lock\'s own closure rather than the file, so a listener added somewhere
     * else — where it would set a different variable — does not satisfy this.
     */
    public function testTheScrollLockHonoursThatReset(): void
    {
        $source = $this->gallerySource();

        $start = strpos($source, 'var locked = false;');
        self::assertIsInt($start, 'The scroll lock must keep its locked flag.');
        $end = strpos($source, 'new MutationObserver(schedule)', $start);
        self::assertIsInt($end, 'The scroll lock must keep its observer.');
        $lock = substr($source, $start, $end - $start);

        self::assertMatchesRegularExpression(
            "/addEventListener\\(\\s*'gallery:scroll-anchor-reset'.*?scrollY = 0/s",
            $lock,
            'The scroll lock must clear the offset it would otherwise restore.',
        );
        self::assertStringContainsString(
            'window.scrollTo(0, scrollY);',
            $lock,
            'The restore this reset exists to correct must still be here.',
        );
    }

    public function testTheSmoothScrollStaysLocalToTheHelper(): void
    {
        // Exactly one mention, inside the helper: the tab switch and the scroll
        // lock's restore both scroll the document too, and both must stay
        // instant. Paging and live search share the helper, so they animate; the
        // count stays at one however many callers it gains.
        self::assertSame(
            1,
            substr_count($this->gallerySource(), "'smooth'"),
            'Only the shared helper animates its scroll; every other programmatic scroll must stay instant.'
        );
        self::assertStringContainsString(
            "'smooth'",
            $this->scrollHelper(),
            'That one smooth scroll must be the shared helper.'
        );

        $paths = glob(dirname(__DIR__, 3) . '/public/assets/*.css');
        self::assertIsArray($paths);
        self::assertNotSame([], $paths, 'The stylesheets must be readable.');

        foreach ($paths as $path) {
            $css = file_get_contents($path);
            self::assertIsString($css, $path . ' must be readable.');

            // Guarded against `overscroll-behavior`, which is unrelated and used
            // legitimately by the trays.
            self::assertDoesNotMatchRegularExpression(
                '/(?<![-\w])scroll-behavior\s*:/',
                $css,
                basename($path) . ' must not declare scroll-behavior: a global rule would animate the'
                . ' scroll lock restoring the page after every tray dismissal.'
            );
        }
    }
}
