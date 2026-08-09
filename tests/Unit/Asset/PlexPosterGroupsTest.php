<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * Shape tripwires for the Plex Posters group list, a sibling of
 * DesignTokenContractTest and StickyToolbarTest.
 *
 * The trap these guard is one that shipped twice without anything failing.
 * The groups are rendered by Alpine's `x-if`, which leaves the <template> in the
 * DOM and inserts each group *after* it — so `.plex-group + .plex-group` has a
 * <template> between every pair and matches nothing at all. The spacing simply
 * never applied, twice, and no test, no linter, and no rendered page reported
 * it: the CSS is valid and the selector is ordinary.
 */
final class PlexPosterGroupsTest extends TestCase
{
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
            '/\.plex-group\s*[+~]\s*\.plex-group/',
            $this->declarations(),
            'x-if leaves a <template> between the rendered groups, so a sibling '
            . 'combinator matches nothing. Space them with a gap on .plex-groups.'
        );
    }

    public function testGroupsAreSpacedByAGapOnTheContainer(): void
    {
        $container = $this->rule('.plex-groups');

        self::assertMatchesRegularExpression('/display:\s*flex/', $container);
        self::assertMatchesRegularExpression('/flex-direction:\s*column/', $container);
        self::assertMatchesRegularExpression('/gap:\s*\d/', $container, 'The gap is what separates the groups.');
    }

    /**
     * The offered group runs to dozens of posters, and the heading is the only
     * thing naming what you are looking at.
     */
    public function testHeadingsStickToTheTopOfTheScroller(): void
    {
        $heading = $this->rule('.plex-group__heading');

        self::assertMatchesRegularExpression('/position:\s*sticky/', $heading);
        self::assertMatchesRegularExpression('/top:\s*0/', $heading);
    }

    /**
     * Posters scroll underneath a sticky heading, so its background has to be
     * opaque — and the same colour as the panel, or the strip reads as a tint
     * laid over the dialog.
     */
    public function testStickyHeadingsAreOpaqueAgainstThePanel(): void
    {
        $heading = $this->rule('.plex-group__heading');

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
            $this->stackingOrder('.plex-group__heading'),
        );
    }

    /**
     * Every `plexPosters.x` the template reads must exist in the component's
     * state.
     *
     * Nothing else catches this. The template is Twig, the state is JavaScript,
     * and no gate in this project reads both — PHPStan does not see either, and
     * a stale field is valid Twig and valid JS. Alpine's failure mode makes it
     * worse than a blank space: reading `.length` off an undefined field throws
     * mid-expression, so the *rest* of the handler never runs. Renaming one
     * array left the tab's own click handler pointing at the old name, which
     * silently stopped the fetch and left the tab permanently empty.
     */
    public function testTheTemplateOnlyReadsStateTheComponentDefines(): void
    {
        $template = file_get_contents(dirname(__DIR__, 3) . '/templates/gallery.html.twig');
        $script = file_get_contents(dirname(__DIR__, 3) . '/public/assets/gallery.js');
        self::assertIsString($template);
        self::assertIsString($script);

        $matched = preg_match('/plexPosters:\s*\{([^}]*)\}/', $script, $state);
        self::assertSame(1, $matched, 'The plexPosters state initialiser must remain findable.');

        preg_match_all('/(\w+)\s*:/', $state[1], $defined);
        preg_match_all('/plexPosters\.(\w+)/', $template, $used);

        foreach (array_unique($used[1]) as $field) {
            self::assertContains(
                $field,
                $defined[1],
                sprintf('gallery.html.twig reads plexPosters.%s, which the component never defines.', $field),
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
