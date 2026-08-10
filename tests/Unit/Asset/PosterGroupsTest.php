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

    private function rule(string $selector): string
    {
        $matched = preg_match(
            '/(?<![\w.-])' . preg_quote($selector, '/') . '\s*\{([^{}]*)\}/',
            $this->declarations(),
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
     */
    public function testTheGroupStackOwnsTheScrollAndNotTheGridsInsideIt(): void
    {
        $container = $this->rule('.poster-groups');

        self::assertMatchesRegularExpression('/max-height:\s*\d/', $container);
        self::assertMatchesRegularExpression('/overflow-y:\s*auto/', $container);

        $nested = $this->rule('.poster-groups .find-grid');

        self::assertMatchesRegularExpression('/max-height:\s*none/', $nested);
        self::assertMatchesRegularExpression('/overflow:\s*visible/', $nested);
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
