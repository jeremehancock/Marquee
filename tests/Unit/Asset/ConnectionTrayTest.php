<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * The Plex connection opens as a tray on a phone rather than navigating to
 * /connect. Three parts of that arrangement read as arbitrary until you know what
 * each is holding up:
 *
 *   - The bridge attribute goes on the ANCHOR BRANCH ONLY. connection_status()
 *     draws the status two ways — a link, and a span for the page you are already
 *     on — and the bridge works by cancelling a navigation. Marking the span
 *     offers a tray as the route to the screen already on display, from an
 *     element with no href to fall back to when there is no tray to open.
 *   - The tray is fetched on EVERY open. /connect asks the Plex server its name
 *     when it renders, and the connection can be forgotten in another tab. A
 *     `connectLoaded` flag added by analogy with the import tray — the one tray
 *     genuinely fetched once — would pin both to the first open.
 *   - Disconnect NAVIGATES, and nothing in the tray intercepts it. That looks
 *     like an omission beside the import and settings trays, which both bind
 *     their form. It is the decision: disconnecting is what the connection gate
 *     turns a user away for, so closing back onto the gallery strands them on a
 *     page about to bounce them, without the confirmation.
 *
 * None of that is verifiable without a browser and this repo has no JS test
 * runner. These assertions pin the arrangement; the behavior itself is verified by
 * hand on a phone against the :dev image. See also SettingsTrayTest,
 * ImportTrayReuseTest and OrphansTrayRescanTest, the three trays this one is
 * modelled on and deliberately differs from.
 */
final class ConnectionTrayTest extends TestCase
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

    private function navMacros(): string
    {
        $path = dirname(__DIR__, 3) . '/templates/partials/_nav_macros.html.twig';
        $source = file_get_contents($path);
        self::assertIsString($source, '_nav_macros.html.twig must be readable at ' . $path);

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

    /**
     * The connection status body, as connection_status() draws it for a page that
     * is not the connection screen.
     */
    private function statusAnchor(): string
    {
        $matched = preg_match('/<a class="conn-status".*?>/s', $this->navMacros(), $m);
        self::assertSame(1, $matched, 'connection_status() must draw the status as a link.');

        return $m[0];
    }

    private function statusCurrentSpan(): string
    {
        $matched = preg_match('/<span class="conn-status conn-status--current".*?>/s', $this->navMacros(), $m);
        self::assertSame(1, $matched, 'connection_status() must draw a current-page form of the status.');

        return $m[0];
    }

    /**
     * The link has to be intercepted for any of the rest to happen, and it is
     * intercepted on the same terms Import, Orphans and Settings are: a touch
     * device, on a page that actually holds the trays. Elsewhere the status still
     * navigates, which is what keeps it working on desktop and on /plex,
     * /orphans and /settings.
     */
    public function testTheStatusIsInterceptedLikeTheOtherTrayDestinations(): void
    {
        $source = $this->gallerySource();

        self::assertStringContainsString('a[data-connect]', $source);
        self::assertStringContainsString("'data-connect': 'gallery:connect'", $source);
        // The gating the four share. Losing it would turn the status into a tray on
        // a desktop pointer, or on a page with nowhere to put one.
        self::assertStringContainsString('if (!isTouch() || !document.querySelector(\'[data-gallery]\')) { return; }', $source);
    }

    /**
     * The decision this whole test file exists for.
     *
     * The bridge cancels a click and dispatches an event instead. On the span that
     * marks the current page there is no navigation to cancel and no href to fall
     * back to — so a status marked there would offer a tray as the way to the
     * screen already on display, and do nothing at all where no tray exists.
     */
    public function testOnlyTheLinkFormOfTheStatusCarriesTheBridgeAttribute(): void
    {
        self::assertStringContainsString('data-connect', $this->statusAnchor());
        self::assertStringNotContainsString('data-connect', $this->statusCurrentSpan());
    }

    /**
     * The status is drawn by one macro rendered in two placements — the desktop
     * header bar and the phone actions tray — so there is exactly one of these to
     * find. A second would mean the placements had been forked.
     */
    public function testTheStatusIsMarkedInOnePlaceForBothPlacements(): void
    {
        // Counted over the markup only. The file explains at length why the
        // attribute goes where it does, and a count that included the comments
        // would forbid the explanation.
        $markup = preg_replace('/\{#.*?#\}/s', '', $this->navMacros());
        self::assertIsString($markup);

        self::assertSame(
            1,
            substr_count($markup, 'data-connect'),
            'The connection status is drawn once for both placements; a second '
            . 'marking means the desktop header and the phone tray have diverged.',
        );
    }

    public function testTheTrayIsFetchedOnEveryOpenRatherThanCached(): void
    {
        $open = $this->method('openConnect');

        self::assertStringContainsString("_loadTray('/connect', 'connectBody')", $open);
        // The import tray's fetch-once flag, deliberately absent. /connect asks the
        // Plex server its name as it renders, and the connection can be forgotten
        // in another tab, so this one decays where the import form does not.
        //
        // Matched on the flag being declared or assigned rather than merely named:
        // gallery.js says in a comment why it has no such flag, and a test that
        // banned the word outright would forbid explaining the decision.
        self::assertDoesNotMatchRegularExpression(
            '/connectLoaded\s*[:=]/',
            $this->gallerySource(),
            'The connection tray is fetched on every open; a loaded-flag would pin '
            . 'the server name and the connection state to the first open.',
        );
    }

    public function testClosingTheTrayDiscardsTheLoadedBody(): void
    {
        $close = $this->method('closeConnect');

        self::assertStringContainsString('this.connectOpen = false', $close);
        // Without this a reopen shows the previous connection state for as long as
        // the new fetch takes — which is the one thing this tray exists to report.
        self::assertStringContainsString("innerHTML = ''", $close);
    }

    /**
     * A failed fetch has to leave a way through rather than an empty sheet. Same
     * shape as the import tray's fallback, pointing at the page the tray borrows.
     */
    public function testAFailedLoadOffersThePageInstead(): void
    {
        $open = $this->method('openConnect');
        $failure = substr($open, (int) strpos($open, '.catch('));

        self::assertStringContainsString('href="/connect"', $failure);
        self::assertStringContainsString('connectLoading = false', $open);
    }

    /**
     * Disconnecting is the one tray action that must leave the gallery, so nothing
     * here may bind or intercept that form. The two delegated handlers already let
     * it past — it carries no `js-mutate` class, and the tray body is a nested
     * scope — and the tray itself must not add a third.
     */
    public function testDisconnectIsLeftToNavigate(): void
    {
        $open = $this->method('openConnect');

        self::assertStringNotContainsString('addEventListener', $open);
        self::assertStringNotContainsString('sign-out', $open);
        // The delegated submit handler only claims forms that opt in, which is what
        // lets a plain POST inside a tray navigate.
        self::assertStringContainsString(
            "form.classList.contains('js-mutate')",
            $this->gallerySource(),
        );
    }

    /**
     * The connection screen can carry the superseded-variable notices as well as
     * the two paragraphs contrasting disconnecting with logging out, so it takes
     * the taller presentation the application reserves for a tray holding a whole
     * page — the same one Import, Orphans and Settings use.
     */
    public function testTheTrayUsesTheTallPresentation(): void
    {
        $template = $this->galleryTemplate();

        $matched = preg_match(
            '#<div class="sheet" x-show="connectOpen".*?</div>\s*</div>\s*</div>#s',
            $template,
            $m,
        );
        self::assertSame(1, $matched, 'The gallery must render a connection tray.');

        self::assertStringContainsString('sheet__panel--tall', $m[0]);
        self::assertStringContainsString('x-ref="connectBody"', $m[0]);
        // The Disconnect form is the fragment's own to submit, not the gallery
        // delegation's to double-handle.
        self::assertStringContainsString('data-nested-scope', $m[0]);
    }

    /**
     * All three ways out, and all three routed through closeConnect() rather than
     * through a bare `connectOpen = false`. A dismissal that only clears the flag
     * leaves the stale body in the DOM, which is the reopen-shows-the-old-server
     * failure closeConnect exists to prevent — and it would leave two of the three
     * exits behaving differently from the third.
     *
     * The grab handle is asserted here as well as by the panel-to-grip ratio in
     * GalleryTest: swipe-down is the exit a phone reaches for first, and the ratio
     * test would still pass if this tray were the one panel without a grip only
     * because some other panel had grown a second.
     */
    public function testEveryWayOutGoesThroughTheSameHandler(): void
    {
        $template = $this->galleryTemplate();

        $matched = preg_match(
            '#<div class="sheet" x-show="connectOpen".*?</div>\s*</div>\s*</div>#s',
            $template,
            $m,
        );
        self::assertSame(1, $matched, 'The gallery must render a connection tray.');

        self::assertStringContainsString('@keydown.escape.window="closeConnect()"', $m[0]);
        self::assertStringContainsString('<div class="sheet__backdrop" @click="closeConnect()">', $m[0]);
        self::assertStringContainsString('class="sheet__grip"', $m[0]);
        // A flag cleared anywhere but in the handler is a fourth exit with none of
        // the handler's cleanup.
        self::assertStringNotContainsString('connectOpen = false', $m[0]);
    }

    /**
     * The bridge dispatches an event; something has to listen. Bound on the
     * gallery root beside the other three, so a tray that exists but is never
     * opened cannot happen.
     */
    public function testTheDispatchedEventOpensTheTray(): void
    {
        self::assertStringContainsString(
            '@gallery:connect.window="openConnect()"',
            $this->galleryTemplate(),
        );
    }
}
