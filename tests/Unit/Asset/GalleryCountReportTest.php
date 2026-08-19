<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * The gallery reports its size two ways and shows one. "Showing 1–24 of 1948"
 * describes a page, so it is true only where a pager exists; "Total: 1948"
 * describes the category and is true at any scroll position. Below 640px the
 * pager is hidden and posters arrive by infinite scroll, so the range becomes
 * false twice over — it names a control that is not on screen, and it names the
 * first batch long after the reader has scrolled past it. The grid grows and
 * that line does not.
 *
 * The screen picks between them, because nothing else can. The server cannot see
 * a viewport, and a window dragged across the threshold has to follow without a
 * reload. That puts the whole decision in two CSS rules that must agree, and
 * disagreeing is silent: reveal both and a phone shows two contradictory reports
 * stacked; hide both and it shows none. Neither raises an error and neither is
 * visible from a desktop viewport, which is what this test is for.
 *
 * Both reports therefore sit in the markup at once, and that is only acceptable
 * because the hidden one is hidden with `display: none` — which drops it from the
 * accessibility tree as well as the page, so a screen reader meets exactly one
 * sentence. `visibility` or `opacity` would leave both readable and turn a fix
 * into a regression for the readers least able to notice. The mechanism is
 * asserted, not just the outcome.
 *
 * Whether the swap actually happens at the right width is verified by hand
 * against the :dev image; this pins the arrangement that lets it.
 */
final class GalleryCountReportTest extends TestCase
{
    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    /**
     * The phone rules live in one `@media (max-width: 640px)` block at the end of
     * the stylesheet, so it can restyle components defined above it at equal
     * specificity.
     */
    private function mobileBlock(): string
    {
        $css = $this->stylesheet();
        $start = strpos($css, '@media (max-width: 640px) {');
        self::assertIsInt($start, 'The mobile block must remain a single @media (max-width: 640px) section.');

        return substr($css, $start);
    }

    /**
     * Everything above the mobile block: the base rules the phone overrides win
     * against. Used to prove a declaration is phone-only.
     */
    private function baseBlock(): string
    {
        $css = $this->stylesheet();
        $start = strpos($css, '@media (max-width: 640px) {');
        self::assertIsInt($start, 'The mobile block must remain a single @media (max-width: 640px) section.');

        return substr($css, 0, $start);
    }

    /**
     * The declarations of one rule, found by a selector appearing at the head of
     * a selector list. These rules contain no nested braces.
     *
     * Comments go first: this stylesheet explains itself heavily, and a selector
     * named in prose would otherwise be matched as though it were a rule.
     */
    private function rule(string $css, string $selector): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        // Anchored to the start of a selector line so a descendant selector is
        // never mistaken for the rule it overrides.
        $pattern = '/^[ \t]*' . preg_quote($selector, '/') . '\s*(?:,[^{}]*)?\{([^{}]*)\}/m';
        $matched = preg_match($pattern, $css, $m);
        self::assertSame(1, $matched, sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    public function testAPointerScreenReportsTheRangeAndNotTheTotal(): void
    {
        $base = $this->baseBlock();

        self::assertStringContainsString(
            'display: none',
            $this->rule($base, '.stats__total'),
            'The total is the phone\'s report. A pointer screen has a pager, so it states the range.',
        );

        // The range carries no base rule of its own: it is an inline span that is
        // simply shown, and giving it one would invite a later `display` value
        // that the phone override then has to fight.
        self::assertDoesNotMatchRegularExpression(
            '/^[ \t]*\.stats__range\s*(?:,[^{}]*)?\{/m',
            (string) preg_replace('#/\*.*?\*/#s', '', $base),
            'The range is shown by default and needs no base rule; only the phone hides it.',
        );
    }

    public function testAPhoneReportsTheTotalAndNotTheRange(): void
    {
        $mobile = $this->mobileBlock();

        self::assertStringContainsString(
            'display: none',
            $this->rule($mobile, '.stats__range'),
            'With the pager hidden, a range names a control that is not on screen and a batch already scrolled past.',
        );
        self::assertStringContainsString(
            'display: inline',
            $this->rule($mobile, '.stats__total'),
            'The phone must reveal the total it is hidden from by the base rule.',
        );
    }

    public function testTheSwapTravelsWithThePagerItDependsOn(): void
    {
        $mobile = $this->mobileBlock();

        // Same block, same threshold. A second top-level `@media` for the count
        // line would be a third place 640px is written — it is already here and
        // in gallery.js's isPhone() — and the first one anybody would forget to
        // move.
        self::assertStringContainsString(
            'display: none',
            $this->rule($mobile, '.pagination'),
            'The count line is swapped because the pager is hidden; both must live in the same block.',
        );

        // Anchored to column zero. The @supports backdrop-filter fallback at the
        // end of the file carries its own nested phone block for the chrome
        // declared in this one; that is a fallback for a surface, not a second
        // home for phone layout, and it is indented inside its @supports.
        self::assertSame(
            1,
            preg_match_all('/^@media \(max-width: 640px\) \{/m', $this->stylesheet()),
            'The top-level phone rules must stay in one block, so the threshold is stated once here.',
        );
    }

    public function testTheHiddenReportIsHiddenFromAssistiveTechnologyToo(): void
    {
        // The reason both reports may sit in the markup at once. `visibility:
        // hidden` and `opacity: 0` remove a thing from view but leave it in the
        // accessibility tree, so a screen reader would hear the range and the
        // total back to back, one of them false.
        foreach (
            [
                '.stats__total' => $this->baseBlock(),
                '.stats__range' => $this->mobileBlock(),
            ] as $selector => $scope
        ) {
            $declarations = $this->rule($scope, $selector);

            self::assertStringContainsString(
                'display: none',
                $declarations,
                sprintf('%s must be hidden with display, which also removes it from the accessibility tree.', $selector),
            );
            self::assertStringNotContainsString(
                'visibility:',
                $declarations,
                sprintf('%s must not be hidden with visibility: a screen reader would still announce it.', $selector),
            );
            self::assertStringNotContainsString(
                'opacity:',
                $declarations,
                sprintf('%s must not be hidden with opacity: a screen reader would still announce it.', $selector),
            );
        }
    }
}
