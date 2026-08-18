<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * Settings opens as a tray on a phone rather than navigating to its own page. The
 * arrangement that makes that work is unusually easy to undo by tidying, because
 * the pieces read as arbitrary until you know what each is holding up:
 *
 *   - The save posts with `redirect: 'manual'`, and an OPAQUE REDIRECT IS THE
 *     SUCCESS BRANCH. Anyone reading it as an error will "fix" it into following
 *     the redirect, and two things break at once — the wasted GET consumes the
 *     "Settings saved." flash the reloaded gallery is there to render, and the
 *     success test starts keying off a 200 that means the opposite.
 *   - Success reloads the whole page. Closing the tray and dispatching
 *     gallery:refresh looks equivalent and is not: refresh redraws the grid but
 *     not the header, so a changed site title would sit stale above a correctly
 *     re-sorted gallery.
 *   - The tray is fetched on EVERY open, unlike the import tray. Adding a
 *     `settingsLoaded` flag by analogy would pin the library list and the
 *     superseded-variable notice to whatever they were the first time.
 *
 * None of that is verifiable without a browser and this repo has no JS test
 * runner. These assertions pin the arrangement; the behavior itself is verified by
 * hand on a phone against the :dev image. See also ImportTrayReuseTest and
 * OrphansTrayRescanTest, which pin the two trays this one deliberately differs
 * from.
 */
final class SettingsTrayTest extends TestCase
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

    private function galleryTemplate(): string
    {
        $path = dirname(__DIR__, 3) . '/templates/gallery.html.twig';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.html.twig must be readable at ' . $path);

        return $source;
    }

    /**
     * The link has to be intercepted for any of the rest to happen, and it is
     * intercepted on the same terms Import and Orphans are: a touch device, on a
     * page that actually holds the trays. Elsewhere Settings still navigates.
     */
    public function testTheSettingsLinkIsInterceptedLikeTheOtherTrayDestinations(): void
    {
        $source = $this->gallerySource();

        self::assertStringContainsString('a[data-settings]', $source);
        self::assertStringContainsString("'data-settings': 'gallery:settings'", $source);
        // The gating the three share. Losing it would turn Settings into a tray on
        // a desktop pointer, or on a page with nowhere to put one.
        self::assertStringContainsString('if (!isTouch() || !document.querySelector(\'[data-gallery]\')) { return; }', $source);
    }

    public function testTheTrayIsFetchedOnEveryOpenRatherThanCached(): void
    {
        $open = $this->method('openSettings');

        self::assertStringContainsString("_loadTray('/settings', 'settingsBody')", $open);
        // The import tray's fetch-once flag, deliberately absent. The library list
        // comes from Plex and the superseded notice from the environment, so this
        // form decays where the import form does not.
        //
        // Matched on the flag being declared or assigned rather than merely named:
        // gallery.js says in a comment why it has no such flag, and a test that
        // banned the word outright would forbid explaining the decision.
        self::assertDoesNotMatchRegularExpression(
            '/settingsLoaded\s*[:=]/',
            $this->gallerySource(),
            'The settings tray is fetched on every open; a loaded-flag would pin the '
            . 'library list and the superseded notice to the first open.',
        );
    }

    public function testClosingTheTrayDiscardsTheLoadedForm(): void
    {
        $close = $this->method('closeSettings');

        self::assertStringContainsString('this.settingsOpen = false', $close);
        // A tray reopened after a rejected save must ask the server what the
        // settings are, not redisplay the submission that was refused.
        self::assertStringContainsString("innerHTML = ''", $close);
        // And it must not strand the progress overlay over a tray nobody can see.
        self::assertStringContainsString('this.settingsSaving = false', $close);
    }

    /**
     * The decision this whole test file exists for.
     */
    public function testTheSaveDoesNotFollowTheRedirectItUsesAsItsSignal(): void
    {
        $save = $this->method('saveSettings');

        self::assertStringContainsString("redirect: 'manual'", $save);
        self::assertStringContainsString("r.type === 'opaqueredirect'", $save);
        // Following it would pull the "Settings saved." flash out of the session in
        // a response that is then thrown away, leaving the reloaded gallery with
        // nothing to say.
        self::assertStringNotContainsString('redirect: \'follow\'', $save);
        self::assertStringNotContainsString('.redirected', $save);
    }

    public function testASavedChangeReloadsThePageRatherThanRefreshingTheGrid(): void
    {
        $save = $this->method('saveSettings');

        self::assertStringContainsString('window.location.reload()', $save);
        self::assertStringContainsString('self.closeSettings()', $save);
        // The near-miss: gallery:refresh redraws the grid but not the header, so a
        // changed site title would sit stale over a correctly re-sorted gallery.
        self::assertStringNotContainsString('gallery:refresh', $save);
    }

    public function testAnInvalidSaveKeepsTheTrayOpenWithItsErrors(): void
    {
        $save = $this->method('saveSettings');

        self::assertStringContainsString('self._swapSettingsForm(html)', $save);
        // Exactly one reload, on the success branch. A second one anywhere in here
        // would throw away the errors the user is meant to read.
        self::assertSame(
            1,
            substr_count($save, 'location.reload()'),
            'Only a valid save may reload; an invalid one must leave the tray standing.',
        );
    }

    public function testAFailedSaveLiftsTheOverlayAndSaysSo(): void
    {
        $save = $this->method('saveSettings');
        $failure = substr($save, (int) strpos($save, '.catch('));

        // Without this the overlay stays over the tray with no way past it.
        self::assertStringContainsString('settingsSaving = false', $failure);
        self::assertStringContainsString('notify(', $failure);
        self::assertStringNotContainsString('location.reload()', $failure);
    }

    /**
     * The re-rendered form has to arrive on exactly the terms the first load did —
     * same region of the page, same strip, same re-init — or the errors land in a
     * tray that no longer looks like the one the user was filling in.
     */
    public function testTheReRenderedFormReusesTheTrayInjectorRatherThanRepeatingIt(): void
    {
        $swap = $this->method('_swapSettingsForm');

        self::assertStringContainsString("_injectTray(html, 'settingsBody')", $swap);
        // A second copy of the strip would drift from _loadTray's the first time
        // either was touched.
        self::assertStringNotContainsString('DOMParser', $swap);
        self::assertStringContainsString('_bindSettingsForm(target)', $swap);
        // The summary alert the form re-renders with sits at the top.
        self::assertStringContainsString('scrollTop = 0', $swap);
    }

    /**
     * The handler goes with the markup it was bound to, so a swapped-in form that
     * is never re-bound submits by navigating — out of the tray, to the page the
     * tray exists to avoid.
     */
    public function testTheSubmitHandlerIsReboundAfterEverySwap(): void
    {
        $bind = $this->method('_bindSettingsForm');

        self::assertStringContainsString('form[action="/settings"]', $bind);
        self::assertStringContainsString('e.preventDefault()', $bind);
        self::assertStringContainsString('self.saveSettings(form)', $bind);

        // Called from both entry points: the first load and every re-render.
        self::assertStringContainsString('_bindSettingsForm', $this->method('openSettings'));
        self::assertStringContainsString('_bindSettingsForm', $this->method('_swapSettingsForm'));
    }

    /**
     * A settings form is six sections long, so it takes the taller presentation the
     * application reserves for a tray holding a whole page — the same one Import
     * and Orphans use. The short panel would show about a section and a half.
     */
    public function testTheTrayUsesTheTallPresentation(): void
    {
        $template = $this->galleryTemplate();

        $matched = preg_match(
            '#<div class="sheet" x-show="settingsOpen".*?</div>\s*</div>\s*</div>#s',
            $template,
            $m,
        );
        self::assertSame(1, $matched, 'The gallery must render a settings tray.');

        self::assertStringContainsString('sheet__panel--tall', $m[0]);
        self::assertStringContainsString('x-ref="settingsBody"', $m[0]);
        // Its own component's clicks and submits are not the gallery delegation's
        // to double-handle.
        self::assertStringContainsString('data-nested-scope', $m[0]);
    }
}
