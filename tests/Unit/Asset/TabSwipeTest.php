<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test — the same standing as
 * {@see TrayDismissalTest}, which pins the app's other touch gesture.
 *
 * A horizontal drag on the gallery moves between adjacent categories. Almost
 * everything that makes it work is invisible in review and unobservable without
 * a real device: whether a listener was registered passively, whether a layout
 * property is read inside a frame callback, whether a pinned panel kept the
 * width it had a moment earlier. This repo has no browser in CI and no JS test
 * runner, so none of that can be *executed* here.
 *
 * What can be caught cheaply is a later edit quietly undoing the arrangement the
 * gesture depends on. Each assertion below stands for a specific failure that
 * has a plausible innocent-looking edit behind it, and every one of them was
 * demonstrated failing against its own bug before being committed. The gesture
 * itself is verified by hand against the :dev image on real hardware.
 *
 * What this file CANNOT catch, and what therefore lives in CLAUDE.md instead: a
 * new overlay added without an entry in the gesture's refusal list, and a second
 * code path for changing category.
 */
final class TabSwipeTest extends TestCase
{
    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    /**
     * Source with comments stripped. This project explains itself heavily, and
     * every prose paragraph below names the very constructs these assertions
     * search for — so a test reading the raw file would match its own
     * documentation and pass while the code said something else entirely.
     */
    private function code(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', (string) preg_replace('#^\s*//.*$#m', '', $this->gallerySource()));
    }

    /**
     * The body of a named function declaration, brace-matched.
     */
    private function functionBody(string $name): string
    {
        $code = $this->code();
        $start = strpos($code, 'function ' . $name . '(');
        self::assertIsInt($start, sprintf('Expected a function named "%s".', $name));

        $open = strpos($code, '{', $start);
        self::assertIsInt($open);

        $depth = 0;
        $length = strlen($code);
        for ($i = $open; $i < $length; $i++) {
            if ($code[$i] === '{') {
                $depth++;
            } elseif ($code[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($code, $open + 1, $i - $open - 1);
                }
            }
        }

        self::fail(sprintf('Could not find the end of "%s".', $name));
    }

    /**
     * The declarations of one rule, found by a selector at the head of a
     * selector list. These rules contain no nested braces.
     */
    private function rule(string $selector): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());
        $pattern = '/^[ \t]*' . preg_quote($selector, '/') . '\s*(?:,[^{}]*)?\{([^{}]*)\}/m';
        self::assertSame(1, preg_match($pattern, $css, $m), sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    /**
     * The touchmove listener must be non-passive at its only registration.
     *
     * This is the single most likely way the gesture ships broken on the
     * platform it matters most on, and it fails silently. `preventDefault()` has
     * to be available on the FIRST move that crosses the lock distance: a
     * listener registered passive cannot call it at all, and on iOS a touch
     * sequence whose early moves went uncancelled has already been handed to the
     * scroller, where later attempts to cancel are ignored with nothing logged.
     * The gesture then works in every desktop emulator and on no iPhone.
     *
     * The innocent-looking edit is someone noticing a non-passive touch listener
     * in a performance audit and "fixing" it.
     */
    public function testTheTouchmoveListenerIsRegisteredNonPassively(): void
    {
        $code = $this->code();

        // Scoped to the gallery root, which is what separates this gesture's
        // listener from the tray dismissal drag's — that one is registered on
        // `document` and is deliberately passive, because it never cancels.
        self::assertSame(
            1,
            substr_count($code, "root.addEventListener('touchmove'"),
            'The swipe must register exactly one touchmove listener on the gallery '
            . 'root, so there is one place for this guarantee to hold.',
        );

        $at = strpos($code, "root.addEventListener('touchmove'");
        self::assertIsInt($at);
        $registration = substr($code, $at, 900);

        self::assertStringContainsString(
            'passive: false',
            $registration,
            'The touchmove listener must be registered { passive: false } from the '
            . 'outset. A passive listener cannot preventDefault at all, and on iOS a '
            . 'touch whose early moves went uncancelled is already the scroller\'s.',
        );
        self::assertStringNotContainsString(
            'passive: true',
            $registration,
            'The touchmove listener must not be passive.',
        );
    }

    /**
     * The axis is decided once and never re-arbitrated.
     *
     * A gesture that re-decides mid-drag can hand a moving, pinned page back to
     * the scroller halfway through. The shape that guarantees it is the early
     * return: once the axis is 'y', the handler leaves immediately for the rest
     * of the touch's life.
     */
    public function testTheAxisIsDecidedOnceAndHeld(): void
    {
        $code = $this->code();
        $at = strpos($code, "root.addEventListener('touchmove'");
        self::assertIsInt($at);
        $handler = substr($code, $at, 1800);

        self::assertMatchesRegularExpression(
            '/swipeAxis === \'y\'[^\n]*\breturn\b/',
            $handler,
            'A touch already assigned to the vertical axis must return immediately, '
            . 'for the rest of its life, however it subsequently moves.',
        );
        self::assertStringContainsString(
            'swipeAxis === null',
            $handler,
            'The axis must be decided only while it is still undecided.',
        );
    }

    /**
     * The lock distance and the commit distance measure different things.
     *
     * They were one number in the app this gesture was ported from, and the
     * result was panels that could not move until the gesture was already
     * decided. The lock has to be small so something moves immediately; the
     * commit has to be large so a stray sideways nudge does not change category.
     */
    public function testTheLockDistanceAndTheCommitDistanceAreSeparate(): void
    {
        $code = $this->code();

        self::assertSame(1, preg_match('/SWIPE_AXIS_LOCK_PX\s*=\s*([\d.]+)/', $code, $lock));
        self::assertSame(1, preg_match('/SWIPE_COMMIT_FRACTION\s*=\s*([^;]+);/', $code, $commit));

        self::assertLessThanOrEqual(
            16.0,
            (float) ($lock[1] ?? '999'),
            'The axis lock must be small: nothing can move until the gesture is '
            . 'claimed, and a touch left uncancelled too long is the scroller\'s.',
        );
        self::assertStringNotContainsString(
            'SWIPE_AXIS_LOCK_PX',
            $commit[1] ?? '',
            'The commit distance must not be derived from the axis lock. They '
            . 'measure different things and are tuned against different failures.',
        );
    }

    /**
     * The gesture is refused when the touch begins, and names every case.
     *
     * Refusing at touchend is adequate only while nothing has moved. A drag that
     * discovers the conflict later has already suppressed the browser's handling
     * and pinned both grids out of the scroller.
     */
    public function testTheGestureIsRefusedAtTouchstart(): void
    {
        $start = $this->functionBody('swipeRefused');

        foreach (['.sheet', '.modal', '.viewer', '.tabs'] as $surface) {
            self::assertStringContainsString(
                $surface,
                $start,
                sprintf('A touch beginning on "%s" belongs to it, not to the gesture.', $surface),
            );
        }

        $code = $this->code();
        $at = strpos($code, "root.addEventListener('touchstart'");
        self::assertIsInt($at);
        $handler = substr($code, $at, 700);

        self::assertStringContainsString(
            'swipeRefused',
            $handler,
            'The refusal must be evaluated in the touchstart handler, not at release.',
        );
        self::assertStringContainsString(
            'touches.length !== 1',
            $handler,
            'A second contact point is a pinch or a zoom and belongs to the browser.',
        );
    }

    /**
     * One answer to "is an overlay open", shared with the page scroll lock.
     *
     * Two independent readings will drift, and the cost of them disagreeing is
     * this gesture fighting a tray — the tray dismissal drag lives on the other
     * axis of the very same touches.
     */
    public function testTheOverlayCheckIsSharedWithTheScrollLock(): void
    {
        $code = $this->code();

        self::assertSame(
            1,
            substr_count($code, 'function anyOverlayOpen('),
            'anyOverlayOpen must have exactly one definition. A second reading of '
            . '"is an overlay open" will drift from the first.',
        );
        // The call sites, not the occurrences: the definition line contains the
        // name too, so counting occurrences would still read 2 with one caller
        // gone — which is exactly the drift this test exists to catch.
        self::assertStringContainsString(
            'anyOverlayOpen()',
            $this->functionBody('swipeRefused'),
            'The swipe must refuse using the scroll lock\'s own answer. A second '
            . 'reading of "is an overlay open" will drift from the first, and the '
            . 'cost is this gesture fighting a tray.',
        );
        self::assertStringContainsString(
            'anyOverlayOpen()',
            $this->functionBody('sync'),
            'The page scroll lock must keep using the shared function too.',
        );
    }

    /**
     * A pinned panel keeps the horizontal box it had in flow.
     *
     * A fixed element does not inherit .container's padding, so pinning to the
     * viewport edges widens the grid by both gutters the instant a thumb lands
     * and narrows it again on release. In the app this was ported from that bug
     * shipped invisibly for weeks, because a scale applied elsewhere happened to
     * cancel it; there is no scale here to hide it.
     *
     * The innocent-looking edit is "the stylesheet should own positioning".
     */
    public function testThePinnedRuleDoesNotDeclareItsOwnHorizontalBox(): void
    {
        $rule = $this->rule('.swipe-pinned');

        self::assertStringContainsString('position: fixed', $rule);
        foreach (['left', 'right', 'width'] as $property) {
            self::assertDoesNotMatchRegularExpression(
                '/(^|[;{\s])' . $property . '\s*:/',
                $rule,
                sprintf(
                    '.swipe-pinned must not declare "%s". The gesture writes top/left/width '
                    . 'inline from the box it measured while the panel was still in flow; '
                    . 'anything declared here overrides that and resizes the grid mid-gesture.',
                    $property,
                ),
            );
        }

        self::assertStringContainsString(
            'pinPanel(',
            $this->functionBody('beginSwipe'),
            'The gesture must write the measured box itself.',
        );
        $pin = $this->functionBody('pinPanel');
        foreach (['top', 'left', 'width'] as $property) {
            self::assertStringContainsString(
                'style.' . $property,
                $pin,
                sprintf('The pin must write "%s" from the measurement.', $property),
            );
        }
    }

    /**
     * No lift: the panels slide, and that is all they do.
     *
     * A 0.94 scale about the centre of the viewport drops the top row of a tall
     * phone about 23px the instant the gesture is claimed, uneased, before
     * anything has slid anywhere. It reads as the page glitching and it is the
     * first thing anyone notices. It was shipped, received exactly that way, and
     * removed.
     */
    public function testTheMovingPanelsAreNeverScaled(): void
    {
        foreach (['.swipe-shift', '.swipe-settling'] as $selector) {
            self::assertStringNotContainsString(
                'scale(',
                $this->rule($selector),
                sprintf('"%s" must not scale a moving panel; the gesture is a slide.', $selector),
            );
        }

        $shift = $this->rule('.swipe-shift');
        // Not [^)]* — the first argument is itself a var(), so a lazy match to
        // the end of the declaration is the only correct read.
        self::assertSame(
            1,
            preg_match('/transform:\s*translate3d\((.*?)\)\s*;/s', $shift, $m),
            '.swipe-shift must move its panel with a translate3d.',
        );
        $parts = array_map('trim', explode(',', $m[1] ?? ''));
        self::assertCount(3, $parts);
        self::assertSame(
            '0',
            $parts[1],
            'The offset must be horizontal only. A vertical component in this '
            . 'transform is the lift arriving by another name.',
        );
    }

    /**
     * The tracking callback reads no layout.
     *
     * Everything the drag needs — the origin, the viewport width, the park
     * offset, the captured scroll — was read once when the gesture was claimed.
     * A layout read inside the frame callback is paid on every frame of the
     * drag, on the frames that can least afford it.
     */
    public function testTheTrackingLoopReadsNoLayout(): void
    {
        $body = $this->functionBody('trackSwipe');

        foreach ([
            'getBoundingClientRect',
            'getComputedStyle',
            'offsetTop',
            'offsetLeft',
            'offsetWidth',
            'offsetHeight',
            'clientWidth',
            'clientHeight',
            'scrollTop',
            'innerWidth',
        ] as $read) {
            self::assertStringNotContainsString(
                $read,
                $body,
                sprintf(
                    'trackSwipe must not read "%s". Every value it needs was captured '
                    . 'when the gesture was claimed; a read here costs a forced layout '
                    . 'on every frame of the drag.',
                    $read,
                ),
            );
        }
    }

    /**
     * The single measurement precedes every style write in setup.
     *
     * The rule is not "never measure" — it is "never measure something you just
     * invalidated". One rect read while layout is still clean is free; the same
     * read after a write forces a synchronous re-layout, and it would land on
     * the gesture's opening frame.
     */
    public function testSetupMeasuresBeforeItWrites(): void
    {
        $body = $this->functionBody('beginSwipe');

        $measure = strpos($body, 'getBoundingClientRect');
        self::assertIsInt($measure, 'Setup must measure the outgoing panel.');
        self::assertSame(
            1,
            substr_count($body, 'getBoundingClientRect'),
            'Setup must measure once. A second read is a forced layout.',
        );

        self::assertSame(
            1,
            preg_match('/\.(?:style\.|classList\.add|setProperty)/', substr($body, 0, $measure)) === 1 ? 0 : 1,
            'Every style write in setup must come after the measurement, so the '
            . 'read happens while layout is still clean.',
        );
    }

    /**
     * A committed drag changes category through the same routine a tap uses.
     *
     * A category change has to leave seven things right — the active tab, the
     * results, the title, a history entry, the carried-over search, the scroll
     * position, and infinite scroll re-armed. Two paths that must agree about
     * seven things will stop agreeing.
     */
    public function testTheCommitGoesThroughTheSharedCategoryChange(): void
    {
        self::assertStringContainsString(
            'switchCategory(',
            $this->functionBody('flushCommit'),
            'The commit must call switchCategory rather than reimplementing the change.',
        );

        // Applied exactly once, from one place. flushCommit has two callers —
        // the settle timer and a second drag beginning during that settle — and
        // the guard is what stops the second one changing category twice. Losing
        // it is not visibly different until someone flicks quickly.
        $flush = $this->functionBody('flushCommit');
        self::assertStringContainsString(
            'live.applied',
            $flush,
            'flushCommit must be idempotent: it has two callers by design.',
        );
        // Occurrences minus the definition, which carries the name too — the same
        // trap that let an earlier version of testTheOverlayCheckIsShared… pass
        // with a caller already removed.
        $code = $this->code();
        self::assertSame(
            2,
            substr_count($code, 'flushCommit(') - substr_count($code, 'function flushCommit('),
            'flushCommit must keep both callers — the settle timer, and a new '
            . 'gesture landing the commit the old one is still owed. Dropping the '
            . 'second silently loses a category change on a quick second flick.',
        );

        $code = $this->code();
        self::assertSame(
            1,
            substr_count($code, 'function switchCategory('),
            'There must be exactly one way to change category.',
        );

        $switch = $this->functionBody('switchCategory');
        foreach (['syncActiveTab', 'scrollTo', 'load('] as $duty) {
            self::assertStringContainsString(
                $duty,
                $switch,
                sprintf('switchCategory owes "%s" to both of its callers.', $duty),
            );
        }
    }

    /**
     * The traversal order is read from the rendered tabs.
     *
     * A hardcoded list would be a second statement of something the template
     * already makes, and the two would disagree the first time a category was
     * added or reordered — with the gesture moving to a neighbour the tab bar
     * does not show beside it.
     */
    public function testTheCategoryOrderComesFromTheRenderedTabs(): void
    {
        $code = $this->code();
        $at = strpos($code, 'var categoryPaths');
        self::assertIsInt($at, 'The traversal order must exist.');
        $decl = substr($code, $at, 500);

        self::assertStringContainsString(
            "querySelectorAll('.tab')",
            $decl,
            'The order must be read from the rendered tabs.',
        );
        foreach (['movies', 'tv-shows', 'tv-seasons', 'collections'] as $category) {
            self::assertStringNotContainsString(
                "'" . $category . "'",
                $decl,
                sprintf(
                    'The traversal order must not name "%s". A hardcoded list drifts '
                    . 'from the tab bar the first time a category moves.',
                    $category,
                ),
            );
        }
    }

    /**
     * Teardown clears everything the gesture set, and every exit runs through it.
     *
     * A drag takes both grids out of the document's scroller. Leaving that in
     * place because a touch was cancelled by an incoming call hands the viewer a
     * page that will not scroll, with nothing on screen to explain it.
     */
    public function testTeardownClearsEverythingTheGestureSet(): void
    {
        $body = $this->functionBody('endSwipe');

        foreach ([
            'cancelAnimationFrame' => 'a pending frame callback',
            'clearTimeout' => 'the settle timer',
            'removeEventListener' => 'the transitionend listener',
            'removeChild' => 'the incoming panel',
            'swipe-pinned' => 'the pinning',
            'is-swiping' => 'the horizontal containment',
            'setupInfinite' => 'infinite scroll for whichever grid is now on screen',
        ] as $needle => $what) {
            self::assertStringContainsString(
                $needle,
                $body,
                sprintf('Teardown must clear %s.', $what),
            );
        }

        $code = $this->code();
        foreach (['touchcancel', 'resize', 'orientationchange'] as $interruption) {
            self::assertStringContainsString(
                "addEventListener('" . $interruption . "'",
                $code,
                sprintf(
                    'A gesture interrupted by "%s" must still be resolved; without it '
                    . 'the page simply stops scrolling.',
                    $interruption,
                ),
            );
        }
    }

    /**
     * The held neighbours are an optimisation, and the currency check reads both
     * things that can stale one.
     *
     * Wrongly discarding a good copy costs one fetch nobody needed. Wrongly
     * trusting a stale one shows a grid that does not match the viewer's
     * search — a wrong library that looks like a working one.
     */
    public function testAHeldCopyIsCheckedAgainstBothTheSearchAndTheLibrary(): void
    {
        $body = $this->functionBody('cachedView');

        self::assertStringContainsString('liveQuery()', $body, 'A held copy must be checked against the live search term.');
        self::assertStringContainsString('libraryMutations', $body, 'A held copy must be checked against the library mutation counter.');

        $code = $this->code();
        self::assertGreaterThanOrEqual(
            1,
            substr_count($code, 'noteLibraryMutation()'),
            'Something must bump the mutation counter, or no held copy is ever stale.',
        );
    }

    /**
     * A cache miss shows a placeholder; it never refuses the gesture.
     *
     * The gesture must be fully correct with the cache permanently empty. If a
     * miss could refuse it, the cache would have stopped being an optimisation
     * and become the thing that decides whether the app responds to a thumb.
     */
    public function testACacheMissStillBeginsTheGesture(): void
    {
        $body = $this->functionBody('beginSwipe');

        self::assertStringContainsString(
            'fetchIncoming(',
            $body,
            'A miss must fetch during the gesture rather than abandoning it.',
        );
        self::assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*!\s*held\s*\)\s*\{\s*return/',
            $body,
            'A missing held copy must never be a reason to refuse the gesture.',
        );

        self::assertStringContainsString(
            'swipe-placeholder',
            $body . $this->functionBody('buildIncomingPane'),
            'The incoming panel must show the placeholder template on a miss.',
        );
    }

    /**
     * Reduced motion keeps the follow and drops the settle.
     *
     * The app-wide rule removes motion the application performs at the viewer. A
     * panel moving because a thumb is moving it is not that, and freezing it
     * would leave the gesture with no feedback rather than less. What the rule
     * does take is the travel after the finger has lifted.
     */
    public function testReducedMotionFlattensTheSettleAndNotTheFollow(): void
    {
        $settle = $this->functionBody('settleDuration');
        self::assertMatchesRegularExpression(
            '/reducedMotion\(\)\s*\)\s*\{\s*return 0/',
            $settle,
            'Under reduced motion the settle must be instant.',
        );

        $track = $this->functionBody('trackSwipe');
        self::assertStringNotContainsString(
            'reducedMotion',
            $track,
            'The follow must NOT consult reduced motion. The viewer is moving the '
            . 'panels themselves; suppressing that leaves the gesture with no '
            . 'feedback at all rather than with less.',
        );

        self::assertStringNotContainsString(
            'transition',
            $this->rule('.swipe-shift'),
            'The tracked offset must not be a transition. It is written per frame, '
            . 'and a transition here would both lag the finger and be flattened by '
            . 'the app-wide reduced-motion rule.',
        );
    }

    /**
     * The two panels are exactly one viewport apart.
     *
     * Parking the incoming panel closer and moving it slower — the familiar
     * platform parallax — puts the two grids on top of each other for the whole
     * gesture, and which is drawn above the other is then decided by document
     * order rather than by the direction of travel. It reads as one direction
     * winning every time, whichever way the thumb goes.
     */
    public function testTheIncomingPanelIsParkedAFullViewportAway(): void
    {
        $body = $this->functionBody('parkOffset');

        self::assertMatchesRegularExpression(
            '/return\s+direction\s*>\s*0\s*\?\s*width\s*:\s*-width/',
            $body,
            'The incoming panel must park a FULL viewport out, so the two panels '
            . 'tile the screen edge to edge and never overlap.',
        );

        $track = $this->functionBody('trackSwipe');
        self::assertMatchesRegularExpression(
            '/park\s*\+\s*live\.offset/',
            $track,
            'Both panels must move at the same rate, one viewport apart. A fraction '
            . 'applied to either is the parallax that makes them overlap.',
        );
    }
}
