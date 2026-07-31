<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * Applying a found poster runs for seconds — the server fetches the candidate
 * at full resolution and pushes it to Plex — so the confirm button must raise a
 * progress overlay and refuse to run twice. That is browser behavior, and this
 * repo has no JS test runner; the real verification is by hand against the :dev
 * image.
 *
 * What is worth catching cheaply is the regression that would look fine in
 * review: the guard, the flag, or the `finally` quietly disappearing from
 * applyFinderSelection(), leaving the overlay markup in place and the button
 * bound to a flag nothing sets any more. So these assertions pin the shape of
 * that one function and the markup it drives. They prove nothing about what the
 * user actually sees.
 */
final class FinderApplyProgressTest extends TestCase
{
    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    private function galleryTemplate(): string
    {
        $path = dirname(__DIR__, 3) . '/templates/gallery.html.twig';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.html.twig must be readable at ' . $path);

        return $source;
    }

    /**
     * The function body, from its own name to the next method after it.
     * Anchoring on both ends keeps the assertions honest if code moves.
     */
    private function applyFunction(): string
    {
        $source = $this->gallerySource();

        $start = strpos($source, 'applyFinderSelection: function (');
        $end = strpos($source, 'copyUrl: function (');
        self::assertIsInt($start, 'applyFinderSelection() must exist.');
        self::assertIsInt($end, 'copyUrl() must follow applyFinderSelection().');
        self::assertGreaterThan($start, $end);

        return substr($source, $start, $end - $start);
    }

    public function testTheInFlightFlagIsSetBeforeTheRequest(): void
    {
        $body = $this->applyFunction();

        $flagged = strpos($body, 'this.finder.applying = true;');
        $fetched = strpos($body, 'fetch(');
        self::assertIsInt($flagged, 'The apply must mark itself in flight.');
        self::assertIsInt($fetched, 'The apply must issue a request.');
        self::assertLessThan(
            $fetched,
            $flagged,
            'The flag must be set before the request, or the first frame shows no progress.'
        );
    }

    public function testASecondActivationIsRefused(): void
    {
        $body = $this->applyFunction();

        // The overlay blocks the second tap only once it has painted; this
        // guard is what makes the refusal independent of paint timing.
        self::assertStringContainsString(
            'if (this.finder.applying) { return; }',
            $body,
            'A change already in flight must not be started again.'
        );
    }

    public function testTheFlagIsClearedOnBothPaths(): void
    {
        $body = $this->applyFunction();

        // In a finally, not in the then — a failure must lift the overlay too,
        // or the preview is stranded behind a spinner that never clears.
        self::assertMatchesRegularExpression(
            '/\.finally\(function \(\) \{ self\.finder\.applying = false; \}\)/',
            $body,
            'The in-flight flag must be cleared in a finally, so failures lift the overlay.'
        );
    }

    public function testAFailedResponseIsNotTreatedAsSuccess(): void
    {
        $body = $this->applyFunction();

        // Without this the error page is parsed as a success page and scraped
        // for its alert, so a change that failed reports as one that worked.
        self::assertStringContainsString(
            'if (!r.ok) { throw new Error(',
            $body,
            'An unsuccessful response must be reported as a failure.'
        );
    }

    public function testEveryFinderStateLiteralCarriesTheFlag(): void
    {
        $source = $this->gallerySource();

        // The finder object is rebuilt in several places (openChange,
        // findPosters and both of its outcomes). One literal missing the flag
        // would silently reset it to undefined mid-flow.
        $literals = substr_count($source, 'previewLoaded: false');
        $flags = substr_count($source, 'applying: false');

        self::assertGreaterThan(0, $literals);
        self::assertSame(
            $literals,
            $flags,
            'Every literal that rebuilds finder state must carry the applying flag.'
        );
    }

    public function testTheConfirmButtonIsDisabledWhileTheChangeRuns(): void
    {
        $template = $this->galleryTemplate();

        self::assertStringContainsString(
            'applyFinderSelection()',
            $template,
            'The confirm button must call the apply.'
        );
        self::assertStringContainsString(
            ':disabled="finder.applying"',
            $template,
            'The confirm button must be disabled while a change is in flight.'
        );
    }

    public function testTheProgressOverlayIsPresentAndBoundToTheFlag(): void
    {
        $template = $this->galleryTemplate();

        self::assertStringContainsString(
            '<div class="overlay" x-show="finder.applying"',
            $template,
            'Applying must raise a progress overlay bound to the in-flight flag.'
        );
        // The same treatment the import and orphan overlays use.
        self::assertMatchesRegularExpression(
            '/x-show="finder\.applying".*?<div class="spinner">/s',
            $template,
            'The progress overlay must show the standard spinner.'
        );
    }

    /**
     * The overlay must not be inside the change-poster modal panel: the
     * full-screen preview covers that panel, so an overlay confined to it would
     * sit underneath the thing the user is looking at.
     */
    public function testTheOverlayLivesWithTheFullScreenPreview(): void
    {
        $template = $this->galleryTemplate();

        $viewer = strpos($template, 'viewer viewer--finder');
        $overlay = strpos($template, 'x-show="finder.applying"');
        $sortTray = strpos($template, 'Sort tray (phone)');

        self::assertIsInt($viewer, 'The finder preview must exist.');
        self::assertIsInt($overlay, 'The progress overlay must exist.');
        self::assertIsInt($sortTray, 'The sort tray must follow the finder preview.');
        self::assertGreaterThan($viewer, $overlay, 'The overlay belongs to the preview, not the modal.');
        self::assertLessThan($sortTray, $overlay, 'The overlay must stay inside the preview block.');
    }
}
