<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * The gallery's dimmed loading state must stay deferred: applied only after a
 * grace period and held for a minimum, so a fast view change never dims. That
 * is browser timing behavior, and this repo has no JS test runner — adding one
 * for a timing tweak would be out of all proportion.
 *
 * What is worth catching cheaply is the specific regression: someone
 * reintroducing a synchronous `classList.add('is-loading')` at a call site,
 * which would restore the flicker while leaving the tracker in place and
 * looking correct. So these assertions check that the busy tracker remains the
 * sole owner of the class. They prove nothing about the actual timing; that is
 * verified by hand against the :dev image.
 */
final class GalleryLoadingIndicationTest extends TestCase
{
    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    public function testTimingConstantsAreNamedAndTunableInOnePlace(): void
    {
        $source = $this->gallerySource();

        self::assertMatchesRegularExpression(
            '/var LOADING_GRACE_MS = \d+;/',
            $source,
            'The grace period before the gallery dims must stay a named constant.'
        );
        self::assertMatchesRegularExpression(
            '/var LOADING_MIN_MS = \d+;/',
            $source,
            'The minimum time the dim stays up must stay a named constant.'
        );
    }

    public function testLoadingClassIsAppliedInExactlyOnePlace(): void
    {
        $source = $this->gallerySource();

        // More than one means a call site is dimming the gallery directly,
        // which is precisely the flicker this change removed.
        self::assertSame(
            1,
            substr_count($source, "classList.add('is-loading')"),
            "'is-loading' must be applied only by the busy tracker's grace timer."
        );
    }

    public function testOnlyTheBusyTrackerMutatesTheLoadingClass(): void
    {
        $source = $this->gallerySource();

        // The tracker spans from its first function to the next one after it.
        // Anchoring on both ends keeps the assertion honest if code is added
        // between them.
        $start = strpos($source, 'function beginBusy(');
        $end   = strpos($source, 'function extractResults(');
        self::assertIsInt($start, 'beginBusy() must exist.');
        self::assertIsInt($end, 'extractResults() must follow the busy tracker.');
        self::assertGreaterThan($start, $end);

        $tracker = substr($source, $start, $end - $start);

        $mutationsInFile    = preg_match_all("/classList\.(?:add|remove)\('is-loading'\)/", $source);
        $mutationsInTracker = preg_match_all("/classList\.(?:add|remove)\('is-loading'\)/", $tracker);

        self::assertGreaterThan(0, $mutationsInFile);
        self::assertSame(
            $mutationsInFile,
            $mutationsInTracker,
            "Every 'is-loading' mutation must live inside beginBusy()/endBusy()."
        );
    }

    public function testBothNavigationHelpersGoThroughTheTracker(): void
    {
        $source = $this->gallerySource();

        // load() and submitForm() — the two entry points for an in-place view
        // change. Both must bracket their fetch with the tracker.
        self::assertSame(
            2,
            substr_count($source, 'beginBusy();'),
            'load() and submitForm() must both mark themselves busy.'
        );
        self::assertSame(
            2,
            substr_count($source, 'endBusy();'),
            'load() and submitForm() must both release in their finally block.'
        );
    }
}
