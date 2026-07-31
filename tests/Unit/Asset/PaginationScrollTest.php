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

    public function testTheSmoothScrollStaysLocalToPaging(): void
    {
        // Exactly one mention, inside the helper: the tab switch and the scroll
        // lock's restore both scroll the document too, and both must stay
        // instant.
        self::assertSame(
            1,
            substr_count($this->gallerySource(), "'smooth'"),
            'Only paging animates its scroll; every other programmatic scroll must stay instant.'
        );
        self::assertStringContainsString(
            "'smooth'",
            $this->scrollHelper(),
            'That one smooth scroll must be the paging helper.'
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
