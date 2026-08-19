<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * The settings screen's vertical rhythm is three nested grids and nothing else:
 * the screen spaces its blocks, a section spaces its questions, a field spaces
 * the parts of one question. Each states a gap; none of their children carries a
 * vertical margin. That arrangement is what this pins.
 *
 * It exists because of the bug it replaced, which is the kind no review catches.
 * `.field:first-of-type { margin-top: 0 }` reads as "the first field" and means
 * "the first <div> among its siblings" — `:first-of-type` matches on element
 * type, not on class. Five sections opened with their <h2>, so the first <div>
 * genuinely was the first field and the rule looked correct for as long as
 * anyone cared to check. The sixth opened with a paragraph of explanation, which
 * made its first checkbox the first <div>, which deleted the space above it and
 * left the control touching the prose. Nothing failed; it just looked wrong, in
 * one section, on one screen.
 *
 * A gap has no such case — it applies only between children — so the fix was to
 * delete the selector rather than correct it to `:first-child`. The assertion
 * that it never returns is the most valuable one here, because the next person
 * to reach for it will be reaching for something that reads fine.
 *
 * What the gaps look like is verified by hand against the :dev image; this pins
 * the arrangement that produces them.
 */
final class FormRhythmTest extends TestCase
{
    /**
     * The steps the settings screen uses, smallest first. The scale carries five;
     * these are the three that set this screen's nesting, and their order is the
     * thing that has to hold.
     */
    private const NESTING = ['--space-xs', '--space-md', '--space-lg'];

    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    private function withoutComments(): string
    {
        // This stylesheet explains itself heavily, and it names its own selectors
        // in prose. Without this, a rule discussed in a comment is matched as
        // though it were declared.
        return (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());
    }

    /**
     * The declarations of one rule, found by a selector appearing at the head of
     * a selector list. These rules contain no nested braces.
     */
    private function rule(string $selector): string
    {
        $pattern = '/^[ \t]*' . preg_quote($selector, '/') . '\s*(?:,[^{}]*)?\{([^{}]*)\}/m';
        $matched = preg_match($pattern, $this->withoutComments(), $m);
        self::assertSame(1, $matched, sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    private function token(string $name): int
    {
        $matched = preg_match(
            '/^\s*' . preg_quote($name, '/') . ':\s*(\d+)px;/m',
            $this->withoutComments(),
            $m
        );
        self::assertSame(1, $matched, sprintf('Expected "%s" to be declared once, in pixels.', $name));

        return (int) $m[1];
    }

    public function testTheSpacingScaleIsPartOfTheTokenContract(): void
    {
        $root = $this->rule(':root');

        foreach (['--space-2xs', '--space-xs', '--space-sm', '--space-md', '--space-lg'] as $step) {
            self::assertStringContainsString(
                $step . ':',
                $root,
                sprintf('%s must be declared on :root beside the elevation, radius and motion scales.', $step),
            );
        }
    }

    public function testTheNestingStaysDistinguishable(): void
    {
        // A reader tells a field boundary from a section boundary by the space
        // alone. Collapse two steps together and the screen goes back to reading
        // as one undifferentiated column, which no other assertion here notices.
        $previous = 0;

        foreach (self::NESTING as $step) {
            $value = $this->token($step);
            self::assertGreaterThan(
                $previous,
                $value,
                sprintf('%s must be larger than the step below it, or the nesting stops being legible.', $step),
            );
            $previous = $value;
        }
    }

    public function testEachStackStatesItsSpacingAsAGapFromTheScale(): void
    {
        // Every level of the nesting: the screen, the form inside it, a section,
        // and a field. The first two share one rule; both names are checked so
        // splitting them later cannot quietly drop one.
        foreach (['.settings', '.settings__form', '.form-section', '.field'] as $selector) {
            $declarations = $this->rule($selector);

            self::assertStringContainsString(
                'display: grid',
                $declarations,
                sprintf('%s must stack its children as a grid so the gap is the only source of space.', $selector),
            );
            self::assertMatchesRegularExpression(
                '/gap:\s*var\(--space-/',
                $declarations,
                sprintf('%s must draw its gap from the spacing scale rather than a literal.', $selector),
            );
        }
    }

    public function testTheFirstOfTypeDefectDoesNotComeBack(): void
    {
        // The whole reason this file exists. `:first-of-type` selects on element
        // type, so `.field:first-of-type` means "the first <div>", not "the first
        // field" — correct only while every section happens to open with its
        // heading, and silently wrong the moment one opens with a paragraph.
        //
        // Asserted against the raw stylesheet, comments and all: the comments
        // above .field and the spacing scale both name the selector to explain
        // why it is gone, and a rule reintroducing it would hide among them.
        self::assertDoesNotMatchRegularExpression(
            '/^[ \t]*\.field:first-of-type\b/m',
            $this->withoutComments(),
            'A gap applies only between children, so no rule may suppress the first field\'s spacing.',
        );
    }

    public function testFieldsCarryNoMarginOfTheirOwn(): void
    {
        // The section owns the space between its questions. A margin here is
        // added to a gap that is already correct, and the two then have to be
        // kept in step by hand — which is how nine different values accumulated.
        self::assertStringNotContainsString(
            'margin',
            $this->rule('.field'),
            'The space between fields belongs to .form-section\'s gap, not to .field.',
        );
        self::assertStringNotContainsString(
            'margin',
            $this->rule('.form-section'),
            'The space between sections belongs to .settings__form\'s gap, not to .form-section.',
        );
    }

    public function testTheSharedStatsRuleIsOverriddenOnThisScreen(): void
    {
        // .stats is the gallery's count line and keeps its own margins there. It
        // is declared further down this file than the reset, so at equal
        // specificity it wins — and puts 8px and 16px back around the settings
        // screen's intro line. Named explicitly rather than weakened globally.
        self::assertStringContainsString(
            'margin: 0',
            $this->rule('.settings > .stats'),
            'The screen\'s intro line must take the grid gap, not .stats\' gallery margins.',
        );
    }
}
