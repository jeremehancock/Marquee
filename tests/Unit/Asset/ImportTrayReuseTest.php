<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * Importing is repetitive by construction: the form takes one content type per
 * run, so populating a library means running it once for movies, once for shows,
 * once for seasons, once for collections. The tray therefore stays open when a
 * run completes, with the form rewound to step one, so a repeat costs a tap
 * rather than a trip back through the menu to reopen it.
 *
 * Two parts of that are easy to undo without anything at the call site
 * complaining. Rewinding has to reach four pieces of state that live in three
 * different places — `type` and `sections` on the form's Alpine component,
 * `importing` driving the tray-contained progress overlay, and the `force`
 * checkbox, which no x-model reaches and which `form.reset()` cannot fix because
 * x-model would write the stale values straight back. And the tray staying open
 * makes a stuck `importing` flag load-bearing: it would leave the overlay over
 * the tray with the submit button disabled for good, where before the tray
 * closed and the whole fragment was discarded.
 *
 * None of that is verifiable without a browser and this repo has no JS test
 * runner. These assertions pin the arrangement; the behavior itself is verified
 * by hand on a phone against the :dev image. See also OrphansTrayRescanTest,
 * which pins the deliberate difference between this tray and the orphans one.
 */
final class ImportTrayReuseTest extends TestCase
{
    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    /**
     * The body of one object-literal method, matched to its closing brace at the
     * method's own indentation. These methods contain no line starting at that
     * same depth until they end.
     */
    private function method(string $name): string
    {
        $source = $this->gallerySource();
        $pattern = '/^(\s+)' . preg_quote($name, '/') . ': function \([^)]*\) \{\n(.*?)^\1\},$/ms';
        $matched = preg_match($pattern, $source, $m);
        self::assertSame(1, $matched, sprintf('Expected a "%s" method in gallery.js.', $name));

        return $m[2];
    }

    public function testACompletedImportLeavesTheTrayOpen(): void
    {
        $run = $this->method('runImport');

        self::assertStringNotContainsString(
            'importOpen = false',
            $run,
            'A completed import must leave the tray standing so another import is a tap away.'
        );
    }

    public function testACompletedImportRewindsTheFormRatherThanDiscardingIt(): void
    {
        $run = $this->method('runImport');

        // _resetImport empties the tray body. Calling it while the tray is open
        // would leave the user staring at an empty tray.
        self::assertStringNotContainsString(
            '_resetImport()',
            $run,
            'Discarding the fragment belongs to tray dismissal, not to a completed import.'
        );
        self::assertStringContainsString(
            '_rewindImportForm()',
            $run,
            'A completed import must put the still-loaded form back to step one.'
        );
    }

    public function testACompletedImportStillReportsAndRefreshes(): void
    {
        $run = $this->method('runImport');

        self::assertStringContainsString('self.notify(', $run);
        self::assertStringContainsString("dispatch('gallery:refresh', {})", $run);
    }

    public function testTheRewindCoversEveryPieceOfFormState(): void
    {
        $rewind = $this->method('_rewindImportForm');

        // Steps two and three are x-show="type", so clearing the type collapses
        // the form back to step one.
        self::assertStringContainsString("data.type = ''", $rewind);
        // Not implied by clearing the type: the radios clear `sections` on
        // change, so a stale library selection would reappear the moment the
        // same type was picked again.
        self::assertStringContainsString('data.sections = []', $rewind);
        // Lifts the tray-contained progress overlay and re-enables the button.
        self::assertStringContainsString('data.importing = false', $rewind);
    }

    public function testTheRewindClearsTheUnboundForceCheckbox(): void
    {
        $rewind = $this->method('_rewindImportForm');

        // "Re-download unchanged posters" is the one control the form's
        // component does not own, so no Alpine assignment reaches it. Left set,
        // the next import would re-download everything without being asked to.
        self::assertStringContainsString('input[name="force"]', $rewind);
        self::assertStringContainsString('force.checked = false', $rewind);

        // And form.reset() is not the shortcut it looks like: it would clear the
        // DOM, and x-model would write the stale type and sections back over it.
        self::assertStringNotContainsString('.reset()', $rewind);
    }

    public function testTheRewindScrollsTheActualScroller(): void
    {
        $rewind = $this->method('_rewindImportForm');

        // Rewinding collapses steps two and three, so a tray left scrolled down
        // to the Import button would land on whitespace. .sheet__body is the
        // scroller; $refs.importBody is a plain div nested inside it, and
        // setting scrollTop on that does nothing at all.
        self::assertStringContainsString(".closest('.sheet__body')", $rewind);
        self::assertStringContainsString('scrollTop = 0', $rewind);
    }

    public function testAFailedImportKeepsTheSelectionsButLiftsTheOverlay(): void
    {
        $run = $this->method('runImport');
        $failure = substr($run, (int) strpos($run, '.catch('));

        // The selections are exactly what a retry would otherwise have to
        // re-enter, so a failure must not rewind.
        self::assertStringNotContainsString('_rewindImportForm', $failure);
        self::assertStringContainsString('importing = false', $failure);
        self::assertStringContainsString('Import failed.', $failure);
    }

    public function testClearingTheProgressFlagDoesNotDependOnAFinallyBlock(): void
    {
        $run = $this->method('runImport');

        // The old arrangement cleared `importing` in a `finally` guarded on
        // `self._importForm` — which the success path had already nulled via
        // _resetImport, so on success the flag was never actually cleared. That
        // was invisible only because the fragment was thrown away with it. With
        // the tray staying open it would strand the overlay over the tray, so
        // each outcome clears the flag on its own path instead.
        self::assertStringNotContainsString('.finally(', $run);
    }

    public function testTheComponentLookupIsGuarded(): void
    {
        $lookup = $this->method('_importData');

        // A tray whose first load failed has an error message where the form
        // would be. Returning null there keeps a completed import reporting its
        // result instead of throwing on the way.
        self::assertStringContainsString('window.Alpine.$data', $lookup);
        self::assertStringContainsString('return null;', $lookup);
        self::assertStringContainsString('catch (e)', $lookup);
    }

    public function testDismissingTheTrayStillDiscardsTheForm(): void
    {
        $close = $this->method('closeImport');

        // The counterpart to staying open on completion: closing the tray by
        // any route still throws the fragment away, so a reopen fetches a fresh
        // form rather than resuming the last session's tray.
        self::assertStringContainsString('this._resetImport()', $close);
        self::assertStringContainsString('this.importOpen = false', $close);
    }
}
