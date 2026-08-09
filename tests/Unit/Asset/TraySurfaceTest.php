<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test — the sibling of StickyToolbarTest.
 *
 * Both halves of this file guard the same mistake seen twice: a component
 * authored for a full page is reused inside a tray, and something that was
 * correct on the page follows it in.
 *
 * The first half is elevation. A panel is a surface — background, border, radius,
 * and the shadow that traces its edge. Inside a tray the panel is flattened so
 * the tray's own surface shows through, and the shadow has to go with it: left
 * behind, it outlines a rectangle that is no longer painted, which is a halo
 * around nothing rather than depth. The declaration that prevents it is a lone
 * `box-shadow: none` in a rule that already reads as a tidy-up, so it is exactly
 * the line someone removes while cleaning. Both sides are asserted, because the
 * contract is that the shadow follows the surface — not that panels never have
 * one. On its own page the panel keeps its elevation, and should.
 *
 * The second half is stacking, and it is the one worth having. The orphan count
 * bar was `class="toolbar"`, so at phone width it inherited the gallery's pinned
 * search bar wholesale: `position: sticky`, the page's own `--chrome-tint` over
 * the tray's surface, a gutter bleed solved against .container's 14px where
 * .sheet__body pads 16px, and `z-index: 30`. That last one is the trap. A tray's
 * progress overlay is deliberately demoted to `z-index: 5` so it covers the tray
 * instead of the screen, and .sheet__panel opens no stacking context, so 30 beat
 * it outright. The bar was drawn over the "Checking Plex for orphans…" spinner —
 * and a backdrop filter samples only what is behind it, so the overlay's dim and
 * blur never touched the one element standing on top of it.
 *
 * That fault is invisible on the path anyone checks. A first open scans an empty
 * body, so there is no bar to punch through; only a reopen, which leaves the
 * previous result standing while it rescans, shows it. A tripwire is the cheap
 * way to catch it, since this repo has no JS test runner and the symptom needs a
 * phone, a populated library, and a second open to reproduce.
 *
 * So the assertions below are about absences. The bar is required to declare no
 * position, no stacking order and no background of its own, anywhere in the
 * stylesheet. Stated that way the layering bug cannot come back by re-tuning:
 * there is no number to get wrong, because there is no number.
 *
 * Whether any of this looks right is verified by hand against the :dev image;
 * this pins the arrangement that lets it.
 */
final class TraySurfaceTest extends TestCase
{
    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    /**
     * Comments are stripped everywhere below. This stylesheet explains itself
     * heavily, and a property or selector named in prose would otherwise be
     * matched as though it were a declaration.
     */
    private function withoutComments(string $css): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /** Everything above the phone block: the rules the phone overrides win against. */
    private function baseBlock(): string
    {
        $css = $this->stylesheet();
        $start = strpos($css, '@media (max-width: 640px) {');
        self::assertIsInt($start, 'The mobile block must remain a single @media (max-width: 640px) section.');

        return substr($css, 0, $start);
    }

    /**
     * The declarations of one rule, found by a selector at the head of a selector
     * list. These rules contain no nested braces.
     */
    private function rule(string $css, string $selector): string
    {
        $css = $this->withoutComments($css);
        // Anchored to the start of a selector line so a descendant selector is
        // never mistaken for the rule it overrides.
        $pattern = '/^[ \t]*' . preg_quote($selector, '/') . '\s*(?:,[^{}]*)?\{([^{}]*)\}/m';
        $matched = preg_match($pattern, $css, $m);
        self::assertSame(1, $matched, sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    public function testAPanelFlattenedInsideATrayCastsNoShadow(): void
    {
        $reset = $this->rule($this->stylesheet(), '.sheet__body .panel');

        // The rule exists to let the tray's own surface show through the panel.
        self::assertStringContainsString('background: none', $reset);
        self::assertStringContainsString('border: none', $reset);

        // The one that gets removed as housekeeping. Without it the Import form
        // and the orphans empty state each cast an --elev-2 halo tracing an edge
        // that is no longer drawn — daylight between the shadow and anything
        // visible, which reads as a rendering fault rather than as depth.
        self::assertStringContainsString(
            'box-shadow: none',
            $reset,
            'A panel stripped of its background and border must lose its elevation '
            . 'in the same place. A shadow with no surface to cast it outlines a '
            . 'rectangle the user cannot see.',
        );
    }

    /**
     * The other side of the contract, and the reason the rule above is a reset
     * rather than a change to .panel itself. Elevation is not the mistake; drawing
     * it where no surface is drawn is.
     */
    public function testTheSamePanelKeepsItsElevationOnItsOwnPage(): void
    {
        $panel = $this->rule($this->baseBlock(), '.panel');

        self::assertStringContainsString(
            'box-shadow: var(--elev-2)',
            $panel,
            'On its own page a panel paints its background and border, so the '
            . 'shadow traces a real edge and belongs there.',
        );
    }

    /**
     * Every rule whose selector names the orphan bar, wherever it is written.
     *
     * Matched across the whole stylesheet rather than per block on purpose: the
     * assertions below are that certain declarations appear *nowhere*, and a
     * future `.sheet__body .orphans__bar` in the phone block is exactly the edit
     * that would reintroduce them while a base-block-only check stayed green.
     *
     * @return list<string>
     */
    private function orphanBarRules(): array
    {
        $css = $this->withoutComments($this->stylesheet());

        // `[^{}]` cannot cross a brace, so @media headers are skipped and what is
        // left is each innermost declaration block with its selector list.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        $rules = [];
        foreach ($matches as $match) {
            if (str_contains($match[1], '.orphans__bar')) {
                $rules[] = $match[2];
            }
        }

        self::assertNotEmpty($rules, 'The orphan bar must have a rule of its own in app.css.');

        return $rules;
    }

    public function testTheOrphanBarIsLaidOutButNotPositioned(): void
    {
        // It is still a bar: the count sits at one end and Delete all at the other.
        self::assertStringContainsString(
            'justify-content: space-between',
            $this->rule($this->baseBlock(), '.orphans__bar'),
            'The count and the delete control sit at opposite ends of the bar.',
        );

        foreach ($this->orphanBarRules() as $rule) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:^|\s)position\s*:/',
                $rule,
                'The orphan bar renders into the orphans tray as well as onto '
                . '/orphans, and a tray is not a page. Pinned, it carried the '
                . 'gallery toolbar\'s gutter bleed — solved against .container\'s '
                . '14px, where .sheet__body pads 16px — and left a 2px channel of '
                . 'tray surface down each edge. It is also deliberately not pinned '
                . 'on /orphans: the list has nothing to keep in reach, and '
                . '"Delete all orphans" is not a control to hold under the thumb.',
            );

            self::assertDoesNotMatchRegularExpression(
                '/(?:^|\s)background(?:-color)?\s*:/',
                $rule,
                'A background here is the page\'s colour painted over the tray\'s. '
                . 'Letting --surface through is what makes the bar part of the tray '
                . 'it is in.',
            );
        }
    }

    /**
     * The assertion this file exists for. See the class docblock: the symptom
     * needs a phone, a populated library and a *second* open of the tray, which
     * is precisely the path a quick check skips.
     */
    public function testNothingInTheOrphanBarOutranksTheTraysOwnOverlay(): void
    {
        // Demoted from the app-wide overlay tier so it covers the tray rather than
        // the screen. Read rather than hard-coded: if it is ever raised, the
        // message below should quote what it actually became.
        $matched = preg_match(
            '/z-index:\s*(\d+)/',
            $this->rule($this->stylesheet(), '.sheet .overlay'),
            $m,
        );
        self::assertSame(1, $matched, 'A tray\'s progress overlay must declare its own z-index.');
        $overlay = (int) $m[1];

        foreach ($this->orphanBarRules() as $rule) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:^|\s)z-index\s*:/',
                $rule,
                sprintf(
                    'The orphan bar must declare no stacking order at all. A tray\'s '
                    . 'progress overlay sits at %d so it covers the tray and not the '
                    . 'screen, and .sheet__panel opens no stacking context, so any '
                    . 'z-index here competes with it directly. Drawn above it, the bar '
                    . 'receives neither the dim nor the blur — a backdrop filter '
                    . 'samples only what is behind — and stands over the spinner as '
                    . 'though it were still live. Declaring nothing is what makes that '
                    . 'unreachable; a lower number would only make it unlikely.',
                    $overlay,
                ),
            );
        }
    }

    /**
     * The gallery's pinned toolbar is deliberately not part of this. Giving the
     * orphan bar its own class is what kept it that way — scoping the phone
     * `.toolbar` rule instead would have meant rewriting StickyToolbarTest, whose
     * matcher is anchored to a bare `.toolbar`, for a change about the orphans
     * tray.
     */
    public function testTheGalleryToolbarIsUntouchedByTheOrphanBar(): void
    {
        $css = $this->withoutComments($this->stylesheet());

        self::assertSame(
            1,
            preg_match('/^[ \t]*\.toolbar\s*\{/m', $css),
            'The gallery keeps exactly one base .toolbar rule; the orphan bar must '
            . 'not have been folded back into it.',
        );

        self::assertStringNotContainsString(
            '.orphans__bar',
            $this->rule($this->baseBlock(), '.toolbar'),
            'The two bars are separate on purpose. Sharing a class is what let the '
            . 'phone toolbar rules reach the orphan bar in the first place.',
        );
    }
}
