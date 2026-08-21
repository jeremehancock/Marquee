<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Shape tripwires for the grouped poster lists, a sibling of
 * DesignTokenContractTest and StickyToolbarTest.
 *
 * Both change-dialog tabs stack grids under sticky headings and share the
 * `.poster-group*` rules: Plex Posters renders two fixed groups with `x-if`, Find
 * Posters one section per supplying service with `x-for`.
 *
 * The trap these guard is one that shipped twice without anything failing.
 * Alpine leaves the <template> in the DOM and inserts each group *after* it — so
 * `.poster-group + .poster-group` has a <template> between every pair and matches
 * nothing at all. The spacing simply never applied, twice, and no test, no
 * linter, and no rendered page reported it: the CSS is valid and the selector is
 * ordinary. `x-for` has exactly the same shape, so the second tab inherits the
 * trap along with the styling.
 */
final class PosterGroupsTest extends TestCase
{
    /**
     * Field names in an object literal — anchored to the start of the body or to
     * a comma, never a bare `word:`.
     *
     * A ternary puts a colon in the middle of a value (`d.partial ? message : ''`),
     * and an unanchored pattern reads the branch after it as another field.
     */
    private const FIELD_NAMES = '/(?:^|,)\s*(\w+)\s*:/';

    private function declarations(): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/public/assets/app.css');
        self::assertIsString($source, 'app.css must be readable.');

        // Comments first: the rules below are discussed in prose right above
        // them, including the very selector this test forbids.
        return (string) preg_replace('#/\*.*?\*/#s', '', $source);
    }

    /**
     * The phone rules live in one `@media (max-width: 640px)` block at the end
     * of the stylesheet, so it can restyle components defined above it at equal
     * specificity.
     *
     * Sliced from the comment-stripped source, never the raw file: the prose
     * above the mobile block names that very query while explaining where it
     * lives, and slicing on the raw text would cut at the sentence instead.
     */
    private function mobileBlock(): string
    {
        $css = $this->declarations();
        $start = strpos($css, '@media (max-width: 640px) {');
        self::assertIsInt($start, 'The mobile block must remain a single @media (max-width: 640px) section.');

        return substr($css, $start);
    }

    /**
     * Everything before the mobile block — the rules as a pointer device sees
     * them.
     */
    private function desktopScope(): string
    {
        $css = $this->declarations();
        $start = strpos($css, '@media (max-width: 640px) {');
        self::assertIsInt($start, 'The mobile block must remain a single @media (max-width: 640px) section.');

        return substr($css, 0, $start);
    }

    private function rule(string $selector): string
    {
        return $this->ruleIn($this->declarations(), $selector);
    }

    /**
     * The declarations of one rule within a given scope.
     *
     * Scoping matters for any selector the stylesheet states twice. `.poster-groups`
     * is one: it carries the scroll on a pointer device and gives it up at tray
     * widths, so an unscoped search silently returns whichever comes first and a
     * test could pass while asserting against the wrong presentation entirely.
     */
    private function ruleIn(string $css, string $selector): string
    {
        $matched = preg_match(
            '/(?<![\w.-])' . preg_quote($selector, '/') . '\s*\{([^{}]*)\}/',
            $css,
            $m,
        );
        self::assertSame(1, $matched, sprintf('"%s" must remain findable in app.css.', $selector));

        return $m[1];
    }

    /**
     * Any sibling combinator between two groups is the bug, whichever spacing
     * property it carries.
     */
    public function testGroupsAreNotSpacedByASiblingCombinator(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/\.poster-group\s*[+~]\s*\.poster-group/',
            $this->declarations(),
            'x-if and x-for both leave a <template> between the rendered groups, so '
            . 'a sibling combinator matches nothing. Space them with a gap on '
            . '.poster-groups.'
        );
    }

    public function testGroupsAreSpacedByAGapOnTheContainer(): void
    {
        $container = $this->rule('.poster-groups');

        self::assertMatchesRegularExpression('/display:\s*flex/', $container);
        self::assertMatchesRegularExpression('/flex-direction:\s*column/', $container);
        self::assertMatchesRegularExpression('/gap:\s*\d/', $container, 'The gap is what separates the groups.');
    }

    /**
     * A long group — Plex's offered artwork, or a well-covered title's TMDB
     * section — outruns the panel, and the heading is the only thing naming what
     * you are looking at.
     */
    public function testHeadingsStickToTheTopOfTheScroller(): void
    {
        $heading = $this->rule('.poster-group__heading');

        self::assertMatchesRegularExpression('/position:\s*sticky/', $heading);
        self::assertMatchesRegularExpression('/top:\s*0/', $heading);
    }

    /**
     * A sticky heading can only travel within its own scroll container, so the
     * scroll has to belong to the stack of groups. When `.find-grid` keeps its
     * own `max-height` and `overflow` — which it must retain for the ungrouped
     * case — each grid scrolls separately and every heading pins to its own tiny
     * box instead of the panel.
     *
     * Scoped to the desktop rules deliberately. The stack gives this scroll up
     * at tray widths; see the mobile counterpart below, which is the other half
     * of the same decision.
     */
    public function testTheGroupStackOwnsTheScrollAndNotTheGridsInsideIt(): void
    {
        $container = $this->ruleIn($this->desktopScope(), '.poster-groups');

        self::assertMatchesRegularExpression('/max-height:\s*\d/', $container);
        self::assertMatchesRegularExpression('/overflow-y:\s*auto/', $container);

        $nested = $this->ruleIn($this->desktopScope(), '.poster-groups .find-grid');

        self::assertMatchesRegularExpression('/max-height:\s*none/', $nested);
        self::assertMatchesRegularExpression('/overflow:\s*visible/', $nested);
    }

    /**
     * The other half of the rule above, and the two only make sense together:
     * exactly one thing scrolls the grouped candidates, and which thing it is
     * depends on the presentation.
     *
     * In the dialog the stack scrolls, because `.modal__body` has no `overflow`
     * of its own above 640px. In the tray `.modal__body` IS the scroller, so a
     * stack that kept its `62vh` cap becomes a second scroller nested in the
     * first — which is not merely redundant, it is unreachable content. The
     * inner scroller's `overscroll-behavior: contain` stops a flick at the end
     * of the candidates rather than handing the remainder to the tray body, so
     * the grid the panel clips below its edge cannot be scrolled to by the one
     * gesture anyone makes. The last row of Find Posters simply went missing,
     * and a dead second scrollbar sat beside the live one.
     *
     * That is why poster-library requires a tray's contents to be reachable in
     * full, and why it excludes nesting rather than only requiring containment:
     * containment is what makes the outer region unreachable.
     *
     * The cap is `vh`-relative while the tray's handle, head, tab strip and
     * safe-area inset are fixed pixels, so the shortfall grows as the viewport
     * shrinks. A cap merely tuned smaller would still clip on some phone; the
     * reset is not a tuning.
     */
    public function testTheTrayBodyOwnsTheScrollOnAPhone(): void
    {
        $container = $this->ruleIn($this->mobileBlock(), '.poster-groups');

        self::assertMatchesRegularExpression(
            '/max-height:\s*none/',
            $container,
            'A vh-relative cap inside a tray whose body already scrolls puts the '
            . 'last row below the panel edge, where no gesture reaches it.',
        );
        self::assertDoesNotMatchRegularExpression(
            '/overflow(-y)?:\s*(auto|scroll)/',
            $container,
            'The tray body is the scroller; the group stack must not be a second '
            . 'one nested inside it.',
        );
    }

    /**
     * Posters scroll underneath a sticky heading, so its background has to be
     * opaque — and the same colour as the panel, or the strip reads as a tint
     * laid over the dialog.
     */
    public function testStickyHeadingsAreOpaqueAgainstThePanel(): void
    {
        $heading = $this->rule('.poster-group__heading');

        self::assertMatchesRegularExpression('/background:\s*var\(--surface\)/', $heading);
        self::assertMatchesRegularExpression('/z-index:\s*[1-9]/', $heading);
    }

    /**
     * The "In use" badge sits inside a poster tile, which scrolls under the
     * heading. If it outranked the heading it would ride over the label.
     */
    public function testTheHeadingOutranksTheInUseBadge(): void
    {
        self::assertGreaterThan(
            $this->stackingOrder('.find-item__badge'),
            $this->stackingOrder('.poster-group__heading'),
        );
    }

    /**
     * Every `plexPosters.x` and `finder.x` the template reads must exist in the
     * component's state.
     *
     * Nothing else catches this. The template is Twig, the state is JavaScript,
     * and no gate in this project reads both — PHPStan does not see either, and
     * a stale field is valid Twig and valid JS. Alpine's failure mode makes it
     * worse than a blank space: reading `.length` off an undefined field throws
     * mid-expression, so the *rest* of the handler never runs. Renaming one
     * array left the tab's own click handler pointing at the old name, which
     * silently stopped the fetch and left the tab permanently empty.
     *
     * Both tabs are checked because both have now been through exactly that
     * rename.
     *
     * @return list<array{string}>
     */
    public static function statefulTabs(): array
    {
        return [['plexPosters'], ['finder']];
    }

    #[DataProvider('statefulTabs')]
    public function testTheTemplateOnlyReadsStateTheComponentDefines(string $component): void
    {
        $template = file_get_contents(dirname(__DIR__, 3) . '/templates/gallery.html.twig');
        $script = file_get_contents(dirname(__DIR__, 3) . '/public/assets/gallery.js');
        self::assertIsString($template);
        self::assertIsString($script);

        $matched = preg_match('/' . preg_quote($component, '/') . ':\s*\{([^}]*)\}/', $script, $state);
        self::assertSame(1, $matched, sprintf('The %s state initialiser must remain findable.', $component));

        preg_match_all(self::FIELD_NAMES, $state[1], $defined);
        preg_match_all('/' . preg_quote($component, '/') . '\.(\w+)/', $template, $used);

        self::assertNotSame([], $used[1], sprintf('The template must read some %s state.', $component));

        foreach (array_unique($used[1]) as $field) {
            self::assertContains(
                $field,
                $defined[1],
                sprintf('gallery.html.twig reads %s.%s, which the component never defines.', $component, $field),
            );
        }
    }

    /**
     * The reverse of the above, for the one field whose rename broke a tab: every
     * `finder` initialiser in the component must carry the same field names, or a
     * reset lands the tab in a state the template cannot read.
     */
    public function testEveryFinderResetCarriesTheSameFields(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/public/assets/gallery.js');
        self::assertIsString($script);

        preg_match_all('/finder\s*[:=]\s*\{([^}]*)\}/', $script, $initialisers);
        self::assertGreaterThan(1, count($initialisers[1]), 'The finder state is reset in several places.');

        $shapes = [];
        foreach ($initialisers[1] as $body) {
            preg_match_all(self::FIELD_NAMES, $body, $fields);
            $names = $fields[1];
            sort($names);
            $shapes[] = $names;
        }

        foreach ($shapes as $shape) {
            self::assertSame(
                $shapes[0],
                $shape,
                'Every finder initialiser must define the same fields; a reset that '
                . 'drops one leaves the template reading undefined state.',
            );
        }
    }

    private function stackingOrder(string $selector): int
    {
        $matched = preg_match('/z-index:\s*(\d+)/', $this->rule($selector), $m);
        self::assertSame(1, $matched, sprintf('"%s" must declare a z-index to be compared.', $selector));

        return (int) $m[1];
    }
}
