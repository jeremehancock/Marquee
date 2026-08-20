<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Shape tripwires for the off state, the sibling of DesignTokenContractTest.
 *
 * Two things are pinned here, and they fail in opposite directions.
 *
 * The first is appearance. Before this existed the stylesheet had no rule for a
 * switched-off control at all, so `disabled` changed what a control did and
 * nothing about how it read: the Import button kept its accent fill, kept
 * `cursor: pointer`, and — because CSS `:hover` matches a disabled element —
 * brightened under the pointer while doing nothing. Two comments in the codebase
 * had already been written asserting that the appearance communicated on the
 * user's behalf. Both were written in good faith about a treatment that was not
 * there, which is the whole argument for asserting it rather than trusting it.
 *
 * The second is enforcement, and it is new debt this change took on deliberately.
 * The application marks its off controls with `aria-disabled` rather than the
 * `disabled` attribute, so that a control stays in the tab order and a keyboard
 * user is told it is unavailable instead of never meeting it — and so that a
 * control which switches itself off under the user's finger does not throw their
 * focus to the document body. The cost is that `aria-disabled` announces and does
 * not enforce: the click still fires, `:active` still matches, and a form whose
 * submit button is merely aria-disabled still submits on Enter. Every one of
 * those was previously handled by the attribute for free.
 *
 * So the failure this file exists to catch is a button that looks dead and is
 * not. It cannot catch all of it. It reads source, not behaviour — there is no
 * JS runner in this repo and CSS is not unit-testable — so it pins the
 * arrangement and leaves the appearance itself to a pass against the :dev image,
 * exactly as ImportTrayReuseTest says of its own subject.
 */
final class DisabledStateTest extends TestCase
{
    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    /**
     * Comments go first, for the reason DesignTokenContractTest gives: this
     * stylesheet explains itself heavily, and several of the comments below
     * discuss the very declarations being asserted.
     */
    private function declarations(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());
    }

    private function template(string $name): string
    {
        $path = dirname(__DIR__, 3) . '/templates/' . $name;
        $source = file_get_contents($path);
        self::assertIsString($source, $name . ' must be readable at ' . $path);

        return $source;
    }

    /**
     * Every rule whose selector list matches $pattern, as selector => body.
     *
     * Selector lists are split so that a rule shared by three selectors is
     * reported as three: an exclusion added to one selector of a list would
     * otherwise read as covering its neighbours.
     *
     * @return array<string, string>
     */
    private function rulesMatching(string $pattern): array
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $this->declarations(), $matches, PREG_SET_ORDER);

        $found = [];
        foreach ($matches as $rule) {
            foreach (explode(',', $rule[1]) as $selector) {
                $selector = trim(preg_replace('/\s+/', ' ', $selector) ?? '');
                if ($selector !== '' && preg_match($pattern, $selector) === 1) {
                    $found[$selector] = $rule[2];
                }
            }
        }

        return $found;
    }

    public function testTheOffStateIsDrawnAtAll(): void
    {
        $rules = $this->rulesMatching('/^\.btn(:disabled|\[aria-disabled)/');

        self::assertNotSame(
            [],
            $rules,
            'A switched-off button must have an appearance. Without one, `disabled` '
            . 'and `aria-disabled` change what a control does and nothing about how it reads.'
        );

        $body = implode(' ', $rules);
        self::assertStringContainsString('cursor: not-allowed', $body);
        self::assertStringContainsString('var(--muted)', $body);
    }

    /**
     * The bug this shipped with once, and the reason it is asserted by agreement
     * rather than by value.
     *
     * The off state was first filled with the same token .panel and .modal__panel
     * are drawn on, so an off button dissolved into whatever it sat on: no fill,
     * no edge, just a muted label floating in the middle of the panel — which reads
     * as a caption, not as a button that cannot be used. It is invisible in review,
     * because the declaration looks perfectly reasonable on its own; it is only
     * wrong in relation to something declared a thousand lines away.
     *
     * So this compares the two rather than pinning either. Retinting panels or
     * retuning the off state must not fail here — the two colliding must.
     */
    public function testTheOffFillIsNotTheSurfaceAButtonSitsOn(): void
    {
        $offRules = $this->rulesMatching('/^\.btn(:disabled|\[aria-disabled)/');
        $panelRules = $this->rulesMatching('/^\.(panel|modal__panel)$/');

        self::assertNotSame([], $panelRules, 'Expected .panel and .modal__panel to be styled.');

        $background = static function (string $body): ?string {
            return preg_match('/(?:^|;)\s*background:\s*([^;]+)/', $body, $m) === 1
                ? trim($m[1])
                : null;
        };

        $offFill = $background(implode(';', $offRules));
        self::assertNotNull($offFill, 'The off state must declare a fill of its own.');

        foreach ($panelRules as $selector => $body) {
            $surface = $background($body);
            if ($surface === null) {
                continue;
            }
            self::assertNotSame(
                $surface,
                $offFill,
                sprintf(
                    'A switched-off button filled with %s vanishes into %s, which is drawn on '
                    . 'the same colour. The label is left floating with no button around it.',
                    $offFill,
                    $selector
                )
            );
        }
    }

    /**
     * Both forms, although nothing in the application uses the attribute today.
     * A native `<button disabled>` added later must not arrive untreated — which
     * is the entire reason the treatment is stated once instead of at each
     * control that happens to need it.
     */
    public function testTheOffStateCoversBothTheAttributeAndTheAriaState(): void
    {
        $selectors = array_keys($this->rulesMatching('/^\.btn(:disabled|\[aria-disabled)/'));

        self::assertContains('.btn:disabled', $selectors);
        self::assertContains(".btn[aria-disabled='true']", $selectors);
    }

    /**
     * The defect this change was raised for. Feedback is the application's promise
     * that pressing will do something, so an inert control that answers the
     * pointer is worse than one that never reacted at all.
     */
    public function testNoButtonOffersHoverFeedbackWhileSwitchedOff(): void
    {
        $rules = $this->rulesMatching('/(btn|button)\b.*:hover$/');

        // A floor, not a count. The assertions below are all inside a loop, so a
        // regex that stopped matching would turn this into a test that passes by
        // examining nothing — the one way a tripwire fails silently.
        self::assertGreaterThanOrEqual(6, count($rules));

        foreach ($rules as $selector => $_) {
            self::assertStringContainsString(
                ':not(:disabled)',
                $selector,
                sprintf('%s offers hover feedback to a switched-off control.', $selector)
            );
            self::assertStringContainsString(
                ":not([aria-disabled='true'])",
                $selector,
                sprintf('%s offers hover feedback to an aria-disabled control.', $selector)
            );
        }
    }

    /**
     * The one that matters most, and the one that changes nothing until someone
     * presses. A natively disabled button never matches `:active`, so this cost
     * nothing while the attribute was in use; an aria-disabled button is an
     * ordinary enabled button to the browser, and will visibly depress under a
     * press unless every press rule excludes it.
     */
    public function testNoButtonOffersPressFeedbackWhileSwitchedOff(): void
    {
        $rules = $this->rulesMatching('/(btn|button)\b.*:active$/');
        self::assertGreaterThanOrEqual(4, count($rules));

        foreach ($rules as $selector => $body) {
            $targetsOffState = str_contains($selector, "[aria-disabled='true']:")
                || str_contains($selector, ':disabled:');
            if ($targetsOffState) {
                // A rule that targets the off state on purpose, neutralising the
                // press rather than granting it. Distinguished from an exclusion
                // by the pseudo-class following it rather than negating it.
                continue;
            }
            self::assertStringContainsString(
                ':not(:disabled)',
                $selector,
                sprintf('%s gives press feedback to a switched-off control.', $selector)
            );
            self::assertStringContainsString(
                ":not([aria-disabled='true'])",
                $selector,
                sprintf('%s gives press feedback to an aria-disabled control.', $selector)
            );
        }
    }

    /**
     * `pointer-events: none` would refuse the click with no handler change at all,
     * which is exactly why it will be proposed.
     *
     * It also makes the element non-hit-testable, and `cursor: not-allowed` needs
     * hit testing — so the two cannot both take effect, and the shortcut silently
     * deletes a signal the design requires an off control to give. The tap lands on
     * whatever sits behind the control, too.
     *
     * That is the durable reason, and it is stated rather than illustrated on
     * purpose. This assertion was first justified by a tooltip it would have
     * silenced, on a control that no longer exists — a justification anchored to one
     * call site outlives the call site by exactly as long as nobody reads it.
     */
    public function testTheOffStateDoesNotSuppressPointerEvents(): void
    {
        $rules = $this->rulesMatching('/\[aria-disabled|:disabled/');

        foreach ($rules as $selector => $body) {
            self::assertStringNotContainsString(
                'pointer-events: none',
                $body,
                sprintf(
                    '%s makes the control non-hit-testable, which takes `cursor: not-allowed` '
                    . 'with it. Refusing the action belongs in the handler.',
                    $selector
                )
            );
        }
    }

    /**
     * The shortcut this change rejected: opacity alone reads as "arriving" or
     * "behind something" as readily as "off", because this stylesheet uses
     * transparency for both. It was also dead — declared once, applied nowhere.
     */
    public function testTheAbandonedOpacityShortcutIsNotReintroduced(): void
    {
        self::assertStringNotContainsString(
            '.is-disabled',
            $this->stylesheet(),
            'The off state is drawn by surface, ink, and cursor together. A bare opacity '
            . 'class is the arrangement this replaced.'
        );
    }

    /**
     * The one part of the aria-disabled switch a test can genuinely check.
     *
     * Each binding is paired with the guard that has to stand behind it, because
     * the attribute no longer refuses anything on its own. A binding added without
     * its guard is the failure mode this whole file is named for: a control that
     * reads as dead and acts as live.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function switchedOffControls(): array
    {
        return [
            'Cancel, under a running change' => [
                'gallery.html.twig',
                ":aria-disabled=\"preview.applying ? 'true' : 'false'\"",
                '@click="if (!preview.applying)',
            ],
            'Sign in with Plex' => [
                'connect.html.twig',
                ":aria-disabled=\"busy ? 'true' : 'false'\"",
                '@click="signIn()"',
            ],
            'Import' => [
                'plex.html.twig',
                ":aria-disabled=\"!ready || importing ? 'true' : 'false'\"",
                '@submit="if (!ready || importing)',
            ],
        ];
    }

    #[DataProvider('switchedOffControls')]
    public function testEverySwitchedOffControlHasAGuardBehindIt(
        string $template,
        string $binding,
        string $guard
    ): void {
        $source = $this->template($template);

        self::assertStringContainsString($binding, $source);
        self::assertStringContainsString(
            $guard,
            $source,
            'aria-disabled announces; it does not enforce. Every binding needs a guard '
            . 'at the action, or the control is pressable while reading as dead.'
        );
    }

    /**
     * A switched-off control may not keep its reason in a tooltip.
     *
     * Tooltips are a hovering-fine-pointer affordance by design, so this pattern is
     * pointer-only by construction: the control refuses, the reason is attached, and
     * a touch user gets a dimmed control and silence. It is the shape of the defect
     * rather than an instance of one — every element matching it is wrong, whatever
     * the reason says.
     *
     * Checked on the element itself rather than on the file, because a tooltip
     * elsewhere in the same template is nobody's business here. The invariant is
     * that the control which refuses does not also carry the explanation.
     *
     * Every occurrence of the binding, not the first: `preview.applying` switches off
     * two buttons that sit next to each other, so stopping at the first would check
     * "Change poster" twice and "Cancel" never — a data set that names one control
     * and inspects another is worse than no data set, because it reports as coverage.
     *
     * It cannot catch a control that carries no explanation anywhere, which is the
     * other half of the same requirement and is not testable from source.
     */
    #[DataProvider('switchedOffControls')]
    public function testNoSwitchedOffControlKeepsItsReasonInATooltip(
        string $template,
        string $binding,
        string $guard
    ): void {
        $source = $this->template($template);

        $at = strpos($source, $binding);
        self::assertIsInt($at, 'Expected to find ' . $binding . ' in ' . $template);

        while ($at !== false) {
            // The element's own tag: back to the '<' that opens it, forward to the
            // '>' that ends it. Attributes cannot nest, so this is the whole control
            // and nothing of its neighbours.
            $open = strrpos(substr($source, 0, $at), '<');
            self::assertIsInt($open);
            $close = strpos($source, '>', $at);
            self::assertIsInt($close);

            self::assertStringNotContainsString(
                'data-tooltip',
                substr($source, $open, $close - $open + 1),
                'A switched-off control cannot explain itself through a tooltip: tooltips '
                . 'are suppressed on touch, so the reason reaches a pointer user and nobody '
                . 'else. Put it where the control leads, or beside it.'
            );

            $at = strpos($source, $binding, $close);
        }
    }

    /**
     * The tab this file used to list, asserted from the other side.
     *
     * A poster with no Plex item does not make the tab unavailable — it makes it
     * empty, and an empty tab opens and says so. The distinction is the reason the
     * reason now reaches a phone at all, and it is one refactor away from being
     * undone by someone restoring what looks like a missing guard.
     */
    public function testThePlexPostersTabIsEmptyRatherThanSwitchedOff(): void
    {
        $source = $this->template('gallery.html.twig');

        self::assertStringNotContainsString(
            ':aria-disabled="change.linked',
            $source,
            'The Plex Posters tab is not switched off for an unlinked poster. Switched off, '
            . 'its reason could only be told to a pointer user.'
        );
        self::assertStringContainsString(
            '<p class="alert" x-show="!change.linked">This poster is not linked to a Plex item.</p>',
            $source,
            'The tab must state why it has nothing, in the panel, where no device is excluded.'
        );
    }

    /**
     * The guarantee that survived the tab being opened: an unlinked poster does not
     * cause a request to Plex. It moved from the control to the request, which is
     * where it belongs — what must not happen is the call, not the change of tab.
     *
     * Pinned in the function rather than in the inline @click expression because the
     * expression is the half that can be edited into looking correct while having
     * dropped its share of the condition.
     */
    public function testAnUnlinkedPosterIsNeverAskedAboutAtThePointOfAsking(): void
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source);

        self::assertStringContainsString(
            'if (!this.change.linked) { return; }',
            $source,
            'loadPlexPosters must refuse a poster with no Plex item where the request is made.'
        );
    }

    /**
     * "Change poster" is the exception to the pairing above: its guard lives in
     * applyPreview() rather than in the markup, and predates this change.
     */
    public function testTheApplyGuardStillStandsInTheScript(): void
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source);

        self::assertStringContainsString('if (this.preview.applying) { return; }', $source);
        self::assertStringContainsString('if (this.busy) { return; }', $source);
    }

    /**
     * The Import button is `type="submit"`, and the attribute was quietly doing a
     * second job nobody wrote down: a form does not submit on Enter when its
     * default button is disabled. An aria-disabled one does, so the tray's own
     * submit path needs the same refusal — preventDefault() in the form's @submit
     * does not stop the listener openImport attaches to the same element.
     */
    public function testTheTrayRefusesAnIncompleteImportToo(): void
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source);

        self::assertStringContainsString(
            'if (data && !data.ready) { return; }',
            $source,
            'runImport must refuse the same selection the form refuses.'
        );
    }

    /**
     * The invariant, not a restatement of the markup: `force` has no x-model, so
     * x-show hides it without clearing it and a hidden-but-checked box still
     * submits force=1. Both conditions reading the same getter is what makes that
     * safe by construction rather than by coincidence — a shared getter cannot
     * drift the way two hand-written expressions can.
     */
    public function testTheReDownloadOptionAndTheImportActionShareOneCondition(): void
    {
        $source = $this->template('plex.html.twig');

        self::assertStringContainsString(
            'get ready() {',
            $source,
            'The condition must be named once so the option and the action cannot drift.'
        );
        self::assertStringContainsString(
            'x-show="ready"',
            $source,
            'The re-download option must appear exactly when an import can be started.'
        );
        self::assertStringNotContainsString(
            'x-show="type"' . "\n",
            $source,
            'x-show="type" offered the option a step early, while no import could run.'
        );
    }
}
