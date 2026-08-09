<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * Shape tripwires for the design token contract, the sibling of StickyToolbarTest
 * and LazyImagePresentationTest.
 *
 * A token contract only holds while everything actually draws from it. Nothing
 * fails when a rule reaches for a literal instead: the surface simply stops
 * following when a scale is retuned, and it goes on looking right until the day
 * someone changes the scale and one panel out of nine does not move with it. That
 * drift is invisible in review — a hand-written `border-radius: 12px` reads as
 * perfectly ordinary CSS — which is why it is asserted here rather than trusted.
 *
 * These tests are about membership and agreement, never about values. Retuning a
 * shadow, a radius, or a duration must not fail anything here; that is the whole
 * point of the tokens existing.
 */
final class DesignTokenContractTest extends TestCase
{
    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    /**
     * Comments go first: this stylesheet explains itself heavily, and a token or
     * declaration named in prose would otherwise be matched as though it were
     * code. Several of the comments discuss the very declarations asserted below.
     */
    private function declarations(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());
    }

    /** The :root block, where every token must be declared. */
    private function root(): string
    {
        $matched = preg_match('/:root\s*\{([^{}]*)\}/', $this->declarations(), $m);
        self::assertSame(1, $matched, 'The token contract must remain a single :root block.');

        return $m[1];
    }

    /**
     * Every rule declaring $property, as value => the individual selectors that
     * declare it.
     *
     * Selector lists are split, so a rule shared by three selectors is reported
     * as three. Otherwise ".modal__panel" and
     * ".modal__panel, .modal__panel--narrow, .modal__panel--wide" are different
     * keys, and an exemption granted to one silently fails to cover the other.
     *
     * @return array<string, list<string>>
     */
    private function rulesDeclaring(string $property): array
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $this->declarations(), $matches, PREG_SET_ORDER);

        $found = [];
        foreach ($matches as $rule) {
            if (preg_match('/(?:^|;)\s*' . preg_quote($property, '/') . ':\s*([^;]+)/', $rule[2], $m) !== 1) {
                continue;
            }
            foreach (explode(',', $rule[1]) as $selector) {
                $selector = trim(preg_replace('/\s+/', ' ', $selector) ?? '');
                if ($selector !== '') {
                    $found[trim($m[1])][] = $selector;
                }
            }
        }

        return $found;
    }

    public function testEveryScaleIsDeclared(): void
    {
        $root = $this->root();

        foreach ([
            '--elev-1' => 'the elevation scale',
            '--elev-5' => 'the elevation scale',
            '--radius-sm' => 'the radius scale',
            '--radius-pill' => 'the radius scale',
            '--dur-fast' => 'the motion scale',
            '--dur-exit' => 'the motion scale',
            '--ease-standard' => 'the easing set',
            '--ease-entrance' => 'the easing set',
            '--ease-exit' => 'the easing set',
            '--chrome-tint' => 'the translucency set',
            '--chrome-blur' => 'the translucency set',
            '--backdrop-tint' => 'the translucency set',
            '--backdrop-blur' => 'the translucency set',
            '--muted-on-chrome' => 'the translucency set',
        ] as $token => $scale) {
            self::assertStringContainsString(
                $token . ':',
                $root,
                sprintf('%s is incomplete without %s.', $scale, $token),
            );
        }
    }

    /**
     * Depth is the token the eye notices going wrong first, and the one most
     * easily written by hand: any plausible-looking `box-shadow` renders.
     */
    public function testEveryShadowComesFromTheElevationScale(): void
    {
        foreach ($this->rulesDeclaring('box-shadow') as $value => $selectors) {
            if (str_contains($value, 'var(--elev-')) {
                continue;
            }

            // Two kinds of exception, each for a reason the scale cannot express.
            //
            // A status light is a spread-only ring at zero offset — a glow, not
            // depth. Putting it on the scale would place it on the layering
            // ladder, where a dot beside a nav link does not belong.
            $glows = ['.conn-dot--ok', '.conn-dot--off'];

            // A surface docked to the bottom edge casts upward. The scale's
            // downward offsets would throw its shadow off-screen and leave the one
            // edge anyone sees flat against the page.
            // .modal__panel's two width modifiers are named because the mobile
            // block restyles all three as one rule.
            $upward = [
                '.sheet__panel',
                '.tabs',
                '.modal__panel',
                '.modal__panel--narrow',
                '.modal__panel--wide',
            ];

            foreach ($selectors as $selector) {
                self::assertContains(
                    $selector,
                    [...$glows, ...$upward],
                    sprintf(
                        '"%s" declares a hand-written box-shadow. Depth belongs to '
                        . '--elev-*, or the exception belongs in this list with its reason.',
                        $selector,
                    ),
                );

                if (in_array($selector, $upward, true)) {
                    self::assertStringContainsString(
                        '0 -',
                        $value,
                        sprintf(
                            '"%s" is exempt only because it is docked to the bottom '
                            . 'edge and casts upward. A downward shadow here belongs '
                            . 'on the scale.',
                            $selector,
                        ),
                    );
                    continue;
                }

                self::assertMatchesRegularExpression(
                    '/^0 0 0 /',
                    $value,
                    sprintf(
                        '"%s" is exempt only because it is an offsetless ring. An '
                        . 'offset shadow here is depth and belongs on the scale.',
                        $selector,
                    ),
                );
            }
        }
    }

    public function testEveryRadiusComesFromTheScale(): void
    {
        foreach ($this->rulesDeclaring('border-radius') as $value => $selectors) {
            if (str_contains($value, 'var(--radius-') || $value === 'inherit') {
                continue;
            }

            // Circles are not on a corner scale, and the focus ring below rounds a
            // run of text rather than a surface — the scale's smallest step would
            // bow it visibly.
            self::assertContains(
                $value,
                ['50%', '2px'],
                sprintf('"%s" declares a hand-written radius.', implode(', ', $selectors)),
            );
        }
    }

    public function testEveryDurationComesFromTheScale(): void
    {
        foreach (['transition', 'animation'] as $property) {
            foreach ($this->rulesDeclaring($property) as $value => $selectors) {
                if (str_contains($value, 'var(--dur-') || str_contains($value, 'var(--tempo-')) {
                    continue;
                }

                self::fail(sprintf(
                    '"%s" declares "%s: %s" with a hand-written duration. Timing '
                    . 'belongs to --dur-* (state changes) or --tempo-* (progress).',
                    implode(', ', $selectors),
                    $property,
                    $value,
                ));
            }
        }
    }

    /**
     * The rule the elevation comment states outright, asserted because a shadow
     * picked by eye is exactly how depth and stacking come to disagree — and when
     * they do, a surface drawn above another appears to sit beneath it.
     */
    public function testElevationAgreesWithTheStackingLadder(): void
    {
        $tier = static function (string $shadow): int {
            if (preg_match('/var\(--elev-(\d)\)/', $shadow, $m) !== 1) {
                self::fail(sprintf('"%s" carries no elevation tier to compare.', $shadow));
            }

            return (int) $m[1];
        };

        // Only the token-based shadows. A surface docked to the bottom edge
        // states its shadow as a literal so it can cast upward, and the mobile
        // block restates .modal__panel that way — reading the last value for a
        // selector would pick that up and find no tier in it. Tier agreement is
        // what this test is for; the upward literals are the other test's.
        $shadows = [];
        foreach ($this->rulesDeclaring('box-shadow') as $value => $selectors) {
            if (!str_contains($value, 'var(--elev-')) {
                continue;
            }
            foreach ($selectors as $selector) {
                $shadows[$selector] = $value;
            }
        }

        foreach (['.card__frame', '.panel', '.modal__panel', '.overlay__box', '.tooltip', '.toast'] as $selector) {
            self::assertArrayHasKey(
                $selector,
                $shadows,
                sprintf('"%s" floats above the page and must carry an elevation.', $selector),
            );
        }

        // Resting content, then raised content, then dialogs, then the transient
        // notices that outrank everything. Trays sit between raised content and
        // dialogs but cast upward, so they are asserted by the shadow test above
        // rather than here.
        self::assertLessThan(
            $tier($shadows['.panel']),
            $tier($shadows['.card__frame']),
            'A card rests on the page; a panel is raised off it.',
        );
        self::assertLessThan(
            $tier($shadows['.modal__panel']),
            $tier($shadows['.panel']),
            'A dialog covers the page a panel sits on, so it must read as further from it.',
        );
        self::assertSame(
            $tier($shadows['.modal__panel']),
            $tier($shadows['.overlay__box']),
            'The progress box is a dialog by another name and shares its tier.',
        );
        self::assertLessThan(
            $tier($shadows['.tooltip']),
            $tier($shadows['.modal__panel']),
            'A tooltip is drawn over a dialog (z 100 against 55) and must sit above it in depth too.',
        );
        self::assertSame(
            $tier($shadows['.tooltip']),
            $tier($shadows['.toast']),
            'Both are transient notices over everything else.',
        );
    }

    /**
     * The one constraint on the card lift that is not a matter of taste: a card
     * that changed its own box would push every card after it in the row, and on
     * a grid that reflows into the next row. `transform` and `box-shadow` are the
     * only two properties that cannot do that.
     */
    public function testTheCardLiftCannotReflowTheGrid(): void
    {
        $matched = preg_match(
            '/\.card__frame:hover,\s*\.card__frame:focus-within\s*\{([^{}]*)\}/',
            $this->declarations(),
            $m,
        );
        self::assertSame(1, $matched, 'The poster card lift must remain findable.');

        $properties = [];
        foreach (explode(';', $m[1]) as $declaration) {
            $name = trim(strstr($declaration, ':', true) ?: '');
            if ($name !== '') {
                $properties[] = $name;
            }
        }

        self::assertContains('transform', $properties, 'The lift is a transform or it is not a lift.');
        self::assertSame(
            [],
            array_diff($properties, ['transform', 'box-shadow']),
            'Only transform and box-shadow stay out of layout. Anything else here '
            . 'moves the cards beside it and reflows the grid.',
        );

        // Scoped to devices that hover, like the overlay it arrives with: on touch
        // the hover state sticks after a tap and strands a card lifted.
        $hoverBlock = strpos($this->declarations(), '@media (hover: hover)');
        self::assertIsInt($hoverBlock);
        self::assertGreaterThan(
            $hoverBlock,
            (int) strpos($this->declarations(), '.card__frame:hover,'),
            'The lift must live inside a (hover: hover) query.',
        );
    }
}
