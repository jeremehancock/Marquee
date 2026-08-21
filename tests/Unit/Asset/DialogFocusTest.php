<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Shape tripwires for dialog focus, the sibling of DisabledStateTest.
 *
 * Be clear about what this can and cannot do. It reads source — there is no JS
 * runner in this repo — so it cannot press Tab, cannot observe where focus
 * lands, and cannot prove that any of the behaviour it guards actually works.
 * That is a keyboard pass against the :dev image, exactly as ImportTrayReuseTest
 * says of its own subject.
 *
 * What it can do is pin the arrangement the behaviour rests on, and the pieces
 * it pins are each one edit away from silently undoing the whole change:
 *
 * - A dialog panel that cannot receive focus. `tabindex="-1"` is the only reason
 *   the manager can put focus on a panel at all. A tenth dialog added without it
 *   would open, be found, and be handed focus that goes nowhere — and nothing
 *   about the page would look wrong.
 * - A manager keyed on a list instead of on the role. Discovery by
 *   `[role="dialog"]` is what makes a new overlay work by being marked up
 *   correctly rather than by being registered. Replacing it with a list of
 *   panel classes renders identically and quietly reintroduces the registry.
 * - A focus move without `preventScroll`. Invisible except on a page scrolled
 *   away from the top, where it fights the scroll lock.
 * - The suppressed focus ring widening past a dialog panel into a rule that
 *   silences controls.
 *
 * The one thing asserted in the negative is the fullscreen poster viewer, which
 * declares no role on purpose: it holds nothing focusable. A test is the only
 * place that distinction survives, because an overlay missing an attribute looks
 * the same whether the omission was reasoned or forgotten.
 */
final class DialogFocusTest extends TestCase
{
    /**
     * Every template that declares a dialog. Named rather than globbed: a
     * template that starts declaring one and is not added here would go
     * unexamined, and this file failing to look at something is the failure mode
     * a tripwire has to be loudest about.
     *
     * @return list<string>
     */
    private const DIALOG_TEMPLATES = [
        'gallery.html.twig',
        'orphans.html.twig',
        'partials/_menu.html.twig',
        'partials/_overlays.html.twig',
        'partials/_support.html.twig',
    ];

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Template source with Twig comments removed.
     *
     * Comments go first for the reason DesignTokenContractTest gives, and here
     * it is not a nicety: these templates explain themselves at length, and
     * several of the comments discuss the very attributes being counted. The
     * note in _overlays.html.twig saying why the viewer declares no
     * `role="dialog"` contains the string `role="dialog"`.
     */
    private function markup(string $name): string
    {
        $path = $this->root() . '/templates/' . $name;
        $source = file_get_contents($path);
        self::assertIsString($source, $name . ' must be readable at ' . $path);

        return (string) preg_replace('/\{#.*?#\}/s', '', $source);
    }

    /**
     * The single opening tag $pattern matches, or a failure naming what was
     * looked for.
     *
     * A helper rather than an inline `preg_match` at each call site because the
     * alternative narrows nothing: a test that reads `$match[0]` after asserting
     * the array is non-empty is a test that fatals instead of failing on the day
     * the markup it looks for is renamed. `fail()` returns never, so the value
     * this returns is a string by construction.
     */
    private function tagMatching(string $pattern, string $template): string
    {
        if (preg_match($pattern, $this->markup($template), $match) !== 1) {
            self::fail(sprintf('Expected %s to contain a tag matching %s.', $template, $pattern));
        }

        return $match[0];
    }

    private function script(): string
    {
        $path = $this->root() . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    /**
     * The focus manager alone, cut out of gallery.js by the section banners the
     * file already organises itself with.
     *
     * Scoped rather than whole-file, so an assertion about what the manager must
     * not contain cannot be satisfied or broken by unrelated code elsewhere in a
     * two-thousand-line file.
     */
    private function focusManager(): string
    {
        $source = $this->script();
        $start = strpos($source, '// ---- Keyboard focus while an overlay is open ----');
        self::assertIsInt(
            $start,
            'The focus manager is found by its section banner. Renaming the banner is fine; '
            . 'update this test with it, because everything below examines the wrong text otherwise.'
        );

        $end = strpos($source, '// ---- ', $start + 10);
        self::assertIsInt($end, 'Expected another section to follow the focus manager.');

        return substr($source, $start, $end - $start);
    }

    /**
     * The focus manager with its comments removed.
     *
     * Comments go first for the reason DesignTokenContractTest gives, and this
     * block earns it twice over: it is written to explain itself, and several of
     * the things asserted below are things it must not *do* while discussing
     * them at length. It says in so many words that it does not filter on
     * `aria-disabled` and why, which is the exact string the assertion looks for.
     */
    private function focusManagerCode(): string
    {
        $manager = $this->focusManager();

        return (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $manager);
    }

    /**
     * Every opening tag that declares `role="dialog"`, across all four
     * templates, as a "template:tag" description => tag pair.
     *
     * @return array<string, string>
     */
    private function dialogTags(): array
    {
        $found = [];
        foreach (self::DIALOG_TEMPLATES as $name) {
            preg_match_all('/<[^<>]*role="dialog"[^<>]*>/s', $this->markup($name), $matches);
            foreach ($matches[0] as $index => $tag) {
                $found[$name . ' #' . ($index + 1)] = $tag;
            }
        }

        return $found;
    }

    /**
     * A floor, not a count. Every assertion about dialogs below runs inside a
     * loop over dialogTags(), so a regex that stopped matching would turn this
     * whole file into a test that passes by examining nothing — the one way a
     * tripwire fails silently.
     *
     * Eleven is what the application declares today: six in the gallery, the
     * preview, two confirms, the poster actions tray and the menu tray. Raise it
     * when a twelfth is added; never lower it to make a failure go away.
     */
    public function testThereAreDialogsToExamine(): void
    {
        self::assertGreaterThanOrEqual(11, count($this->dialogTags()));
    }

    /**
     * The attribute the whole change rests on. Without it the manager's
     * `focus()` on a panel is a no-op — and one that leaves no trace, because
     * the dialog still opens and still looks right.
     */
    public function testEveryDialogPanelCanReceiveFocus(): void
    {
        foreach ($this->dialogTags() as $where => $tag) {
            self::assertStringContainsString(
                'tabindex="-1"',
                $tag,
                sprintf(
                    'The dialog at %s cannot be focused, so opening it leaves a keyboard user '
                    . 'on the page behind the backdrop. Negative, so it never joins the tab order.',
                    $where
                )
            );
        }
    }

    /**
     * `aria-modal` is what tells assistive technology the page behind is not
     * there. It carries that on its own until `inert` lands — see design
     * Decision 7 — so a dialog declaring the role without it is worse off than
     * one declaring neither.
     *
     * The name matters for the same reason focus lands on the panel rather than
     * on a control inside it: the panel's name is the announcement that an
     * overlay opened at all.
     */
    public function testEveryDialogIsModalAndNamed(): void
    {
        foreach ($this->dialogTags() as $where => $tag) {
            self::assertStringContainsString(
                'aria-modal="true"',
                $tag,
                sprintf('The dialog at %s does not claim the page behind it.', $where)
            );
            self::assertMatchesRegularExpression(
                '/aria-label="[^"]+"/',
                $tag,
                sprintf(
                    'The dialog at %s has no accessible name, so opening it announces nothing.',
                    $where
                )
            );
        }
    }

    /**
     * The surface that had none of the three, and the reason two switched-off
     * controls fixed by draw-the-disabled-state were unreachable anyway: the
     * manager takes its subjects from the role, so an overlay that never
     * declared one was never a place focus could go.
     *
     * Asserted by name as well as by the loop above, because this one was
     * modal in behaviour for a year while declaring nothing, and the loop would
     * stop covering it the moment someone removed the attributes rather than
     * the overlay.
     */
    public function testThePreviewOverlayIsDeclaredADialog(): void
    {
        $tag = $this->tagMatching('/<div class="viewer viewer--preview"[^<>]*>/s', 'gallery.html.twig');

        self::assertStringContainsString('role="dialog"', $tag);
        self::assertStringContainsString('aria-modal="true"', $tag);
        self::assertStringContainsString('tabindex="-1"', $tag);
        self::assertMatchesRegularExpression('/aria-label="[^"]+"/', $tag);
    }

    /**
     * The decision, not the oversight.
     *
     * The fullscreen poster viewer holds a poster with `alt=""` and no controls
     * at all, so there is nothing to move focus to and nothing to keep it on.
     * Declaring it a dialog would put it in the manager's hands and trap focus
     * on an element the user cannot act on.
     *
     * That is not the same as the viewer being well served — it announces
     * nothing and offers no visible way out — but the fix there is a name and a
     * close control, which changes what is on screen and belongs to
     * poster-library. Until that is made, the missing attribute is load-bearing.
     */
    public function testTheFullscreenViewerIsNotDeclaredADialog(): void
    {
        $tag = $this->tagMatching('/<div class="viewer"[^<>]*>/s', 'partials/_overlays.html.twig');

        self::assertStringNotContainsString(
            'role="dialog"',
            $tag,
            'The fullscreen viewer holds nothing focusable. Declaring it a dialog hands the '
            . 'focus manager an overlay it can only trap focus in.'
        );
    }

    /**
     * Discovery by attribute is what makes an overlay added later work by being
     * marked up correctly rather than by being registered somewhere — including
     * the orphans confirmation, which is injected into a tray at runtime and
     * exists in no list anyone could have written.
     *
     * A list of panel classes would render identically and pass every other
     * assertion in this file, which is exactly why this one is here.
     */
    public function testTheManagerIsKeyedOnTheRoleRatherThanAList(): void
    {
        $manager = $this->focusManagerCode();

        self::assertStringContainsString(
            '[role="dialog"]',
            $manager,
            'The focus manager must find its dialogs by the role they declare.'
        );

        foreach (['sheet__panel', 'modal__panel', 'viewer--preview'] as $class) {
            self::assertStringNotContainsString(
                $class,
                $manager,
                sprintf(
                    'The focus manager names %s, which is a registry of panels by another '
                    . 'spelling. An overlay added later would not be managed.',
                    $class
                )
            );
        }
    }

    /**
     * No dialog may be named in the manager. Each name is an accessible label
     * today; a manager that mentions one has started special-casing a particular
     * overlay, which is the first step back to per-dialog wiring.
     *
     * @return array<string, array{string}>
     */
    public static function dialogNames(): array
    {
        return [
            'Change poster' => ['Change poster'],
            'Preview poster' => ['Preview poster'],
            'Sort order' => ['Sort order'],
            'Import from Plex' => ['Import from Plex'],
            'Orphaned posters' => ['Orphaned posters'],
            'Settings' => ['Settings'],
            'Plex Connection' => ['Plex Connection'],
            'Poster actions' => ['Poster actions'],
            'Support development' => ['Support development'],
        ];
    }

    #[DataProvider('dialogNames')]
    public function testTheManagerSpecialCasesNoIndividualDialog(string $name): void
    {
        self::assertStringNotContainsString(
            $name,
            $this->focusManagerCode(),
            sprintf('The focus manager special-cases the %s dialog.', $name)
        );
    }

    /**
     * Every focus move, without exception.
     *
     * While an overlay is open the body is pinned by the scroll lock above, and
     * an unguarded focus scroll fights it. The failure is invisible on a page at
     * the top and invisible in review, so it is asserted against every call
     * rather than counted: a new `.focus()` added later is the thing that goes
     * wrong, not the two that exist now.
     */
    public function testEveryFocusMovePreventsScrolling(): void
    {
        $manager = $this->focusManagerCode();

        preg_match_all('/\.focus\(([^)]*)\)/', $manager, $matches);
        self::assertGreaterThanOrEqual(
            2,
            count($matches[0]),
            'Expected the focus manager to move focus.'
        );

        foreach ($matches[1] as $arguments) {
            self::assertStringContainsString(
                'preventScroll: true',
                $arguments,
                'A focus move without preventScroll scrolls a page the scroll lock has pinned.'
            );
        }
    }

    /**
     * The manager restores focus when an overlay *starts* closing, not when its
     * exit animation finishes — the same signal, and the same reason, as the
     * scroll lock's release. Anything else contradicts the standing requirement
     * that dismissal is never delayed by its own animation.
     */
    public function testDismissalIsNotWaitedOut(): void
    {
        self::assertStringContainsString(
            'overlay-closing',
            $this->focusManagerCode(),
            'A closing overlay stays displayed for the length of its leave transition. Without '
            . 'this class it still counts as open, and focus is held inside a dialog the user '
            . 'has already dismissed.'
        );
    }

    /**
     * A switched-off control staying reachable is the whole point of how this
     * application marks them, and a trap that skipped them would undo
     * draw-the-disabled-state inside every dialog at once — silently, because
     * such a control looks identical either way.
     */
    public function testTheTrapDoesNotSkipSwitchedOffControls(): void
    {
        self::assertStringNotContainsString(
            'aria-disabled',
            $this->focusManagerCode(),
            'The focus manager filters on aria-disabled, which takes switched-off controls out '
            . 'of the tab ring inside a dialog. They are reachable on purpose.'
        );
    }

    /**
     * The focus ring suppressed on a dialog panel, and nowhere near a control.
     *
     * The panel is announced, not operated, so a ring around the whole thing is
     * noise — but this stylesheet has no global focus rule, every indicator
     * being scoped to a control, so a rule here that lost its second attribute
     * would be the first thing in the file able to silence one. Both attributes
     * together is a combination only a dialog panel carries.
     */
    public function testTheSuppressedFocusRingCannotReachAControl(): void
    {
        $path = $this->root() . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        $declarations = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $declarations, $rules, PREG_SET_ORDER);

        $suppressors = [];
        foreach ($rules as $rule) {
            if (!str_contains($rule[2], 'outline: none')) {
                continue;
            }
            foreach (explode(',', $rule[1]) as $selector) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $selector));
                if ($selector !== '' && str_contains($selector, 'dialog')) {
                    $suppressors[] = $selector;
                }
            }
        }

        self::assertNotSame(
            [],
            $suppressors,
            'The dialog panel takes focus on open, so its default focus ring must be suppressed.'
        );

        foreach ($suppressors as $selector) {
            self::assertStringContainsString(
                "[tabindex='-1']",
                $selector,
                sprintf(
                    '%s suppresses a focus ring on anything declaring a dialog role, not only on '
                    . 'the panel that takes focus. Keep both attributes on the selector.',
                    $selector
                )
            );
        }
    }

    /**
     * The two menus that open an overlay hand focus to their own trigger before
     * they close.
     *
     * This looks like a tidy-up and is not. Both menus hide themselves on the
     * flush after the click, and hiding a *focused* element hands its focus to
     * the document body; the manager reads `document.activeElement` a frame later
     * to decide where to put focus back when the overlay is dismissed, and it
     * declines to restore an origin chain rooted at the body — deliberately, that
     * being what a touch tap leaves behind. So the sequence without these calls
     * is: open Support Development from the keyboard, land in the dialog
     * correctly, press Escape, and be left on the body with the next Tab
     * resuming at the top of the page. Measured, not reasoned: removing the ⋯
     * panel's call moves the post-dismissal `activeElement` from the trigger to
     * `<body>`.
     *
     * The trigger refs are asserted alongside the calls because an `$refs` name
     * that resolves to nothing fails silently — `undefined.focus()` throws inside
     * an Alpine handler and takes the menu-closing with it.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function overlayOpeningMenus(): array
    {
        return [
            'desktop overflow menu' => ['layout.html.twig', 'navmenu__panel menu__body', 'moreTrigger'],
            'phone actions tray' => ['partials/_menu.html.twig', 'sheet__body menu__body', 'menuTrigger'],
        ];
    }

    #[DataProvider('overlayOpeningMenus')]
    public function testAMenuThatOpensAnOverlayHandsFocusBackToItsTrigger(
        string $template,
        string $container,
        string $ref,
    ): void {
        $tag = $this->tagMatching('/<div class="' . preg_quote($container, '/') . '"[^<>]*>/s', $template);

        self::assertStringContainsString(
            '$refs.' . $ref . '.focus()',
            $tag,
            sprintf(
                'Choosing an entry in %s must move focus to its trigger before the menu '
                . 'hides, or the focus manager has only the body to remember.',
                $container
            )
        );

        // The ref itself, wherever the trigger is declared. Both triggers live in
        // layout.html.twig; the tray is a partial of it and shares its scope.
        self::assertStringContainsString(
            'x-ref="' . $ref . '"',
            $this->markup('layout.html.twig'),
            sprintf('$refs.%s must resolve to a control in the topbar scope.', $ref)
        );
    }
}
