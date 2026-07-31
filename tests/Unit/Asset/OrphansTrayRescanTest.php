<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * The orphans tray must scan again every time it is opened: its contents are a
 * statement about what Plex holds right now, and a reopened tray showing the
 * previous scan reads as current while being stale, which invites deleting a
 * poster that is no longer an orphan.
 *
 * The fix has an obvious-looking alternative that is wrong in a way nothing at
 * the call site reveals. Clearing the loaded flag on close would make each
 * reopen re-run the tray loader, which re-runs Alpine.initTree over the
 * fragment, which re-binds the window listener the orphans component registers
 * on init and never removes. Reopens would accumulate live listeners over
 * discarded components, and one still holding a pending delete would fire it on
 * a later, unrelated confirmation — a duplicate delete on the destructive path.
 *
 * That is browser behavior and this repo has no JS test runner. What is worth
 * catching cheaply is someone "simplifying" the re-scan back into a reload. So
 * these assertions pin both halves: that a reopen re-scans, and that it does not
 * go through the loader to do it.
 */
final class OrphansTrayRescanTest extends TestCase
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

    public function testReopeningTheOrphansTrayIsNotShortCircuited(): void
    {
        $open = $this->method('openOrphans');

        // The old guard returned early whenever the tray had ever been loaded,
        // which suppressed both the re-scan and the spinner.
        self::assertStringNotContainsString(
            'if (this.orphansLoaded || this.orphansLoading) { return; }',
            $open,
            'A tray that has been loaded before must re-scan on reopen, not return early.'
        );
        // A scan already in flight is still a reason to do nothing.
        self::assertStringContainsString(
            'if (this.orphansLoading) { return; }',
            $open,
            'A load already in flight must still short-circuit.'
        );
    }

    public function testReopeningRescansWithoutReloadingTheTray(): void
    {
        $open = $this->method('openOrphans');

        self::assertStringContainsString(
            'this._rescanOrphans()',
            $open,
            'Reopening a loaded orphans tray must re-run the scan.'
        );
        // The whole point: the re-scan path must not reach _loadTray, because
        // that re-inits the fragment and re-binds its window listener.
        self::assertSame(
            1,
            substr_count($open, '_loadTray('),
            'The orphans tray must be loaded in exactly one place — the first open.'
        );
    }

    public function testTheRescanDrivesTheTraysOwnLoadingState(): void
    {
        $rescan = $this->method('_rescanOrphans');

        // Without this the tray re-scans invisibly and still looks stale.
        self::assertStringContainsString('page.loading = true', $rescan);
        self::assertStringContainsString('page.loading = false', $rescan);
        self::assertStringContainsString('page.reload()', $rescan);
    }

    public function testTheRescanFallsBackWhenTheComponentIsUnreachable(): void
    {
        $rescan = $this->method('_rescanOrphans');

        // A failed first load leaves an error message where the component would
        // be. Returning false there sends the caller back to a full load rather
        // than throwing on a missing method.
        self::assertStringContainsString('return false;', $rescan);
        self::assertStringContainsString("typeof page.reload !== 'function'", $rescan);
    }

    public function testTheRescanDoesNotRaceASecondScan(): void
    {
        $rescan = $this->method('_rescanOrphans');

        // A rapid close/reopen must not put two scans on the same results node.
        self::assertStringContainsString(
            'if (page.loading) { return true; }',
            $rescan,
            'A scan already running must be left alone, and still counts as handled.'
        );
    }

    public function testTheImportTrayStillLoadsOnce(): void
    {
        // The asymmetry is deliberate: a configuration form does not go stale,
        // a scan result does. This pins the intended difference so neither tray
        // gets "made consistent" with the other by mistake.
        self::assertStringContainsString(
            'if (this.importLoaded || this.importLoading) { return; }',
            $this->method('openImport'),
            'The import tray holds a form, which does not decay; it stays fetch-once.'
        );
    }
}
