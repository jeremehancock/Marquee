<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * Three places show a poster that has to be waited for: the gallery card, the
 * Find Posters candidate cell, and the two full-screen views. They deliberately
 * share one treatment — a shimmer holding the space, then a fade once the image
 * resolves — expressed as selector lists on single rules rather than as three
 * copies. The cheap regression to catch is a later edit restyling one caller and
 * quietly dropping it out of those lists, which reads as "nothing changed" in
 * review while that one place goes back to snapping in over a blank space.
 *
 * The viewers add a failure mode a card cannot have: one <img> is re-pointed at
 * every poster, so its resolved flag has to be cleared as the source is set.
 * Miss that and the second poster opened is revealed before it has loaded — the
 * previous poster, briefly, in its place.
 *
 * Whether any of it actually paints correctly is browser timing, and this repo
 * has no JS test runner. These assertions pin the arrangement; the appearance is
 * verified by hand against the :dev image.
 */
final class LazyImagePresentationTest extends TestCase
{
    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }

    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    /**
     * Comments go first in both helpers below: this stylesheet explains itself
     * heavily, and a selector or declaration named in prose would otherwise be
     * matched as though it were code.
     */
    private function declarations(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $this->stylesheet());
    }

    /** The selector list of the one rule whose declarations contain $declaration. */
    private function selectorsDeclaring(string $declaration): string
    {
        $pattern = '/(?:^|\})\s*([^{}]*)\{[^{}]*' . preg_quote($declaration, '/') . '/';
        $matched = preg_match($pattern, $this->declarations(), $m);
        self::assertSame(1, $matched, sprintf('Expected a rule declaring "%s".', $declaration));

        return $m[1];
    }

    /** The selector list of the one rule whose selectors include $selector. */
    private function selectorsAlongside(string $selector): string
    {
        $pattern = '/(?:^|\})\s*([^{}]*' . preg_quote($selector, '/') . '[^{}]*)\{/';
        $matched = preg_match($pattern, $this->declarations(), $m);
        self::assertSame(1, $matched, sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    public function testEveryWaitingPosterDrawsTheSameShimmer(): void
    {
        $shimmering = $this->selectorsDeclaring('animation: shimmer');

        foreach ([
            '.card__frame::before' => 'the gallery card',
            '.find-item__frame::before' => 'the Find Posters candidate cell',
            '.viewer__placeholder' => 'the full-screen views',
        ] as $selector => $what) {
            self::assertStringContainsString(
                $selector,
                $shimmering,
                sprintf('The placeholder for %s must stay on the shared shimmer rule.', $what)
            );
        }
    }

    public function testEveryLazyImageShipsHiddenAndIsRevealedByTheSameClass(): void
    {
        // `opacity: 0` and the `is-loaded` reveal are a pair: an image on one list
        // but not the other is either permanently invisible or never faded.
        $hidden = $this->selectorsDeclaring('transition: opacity 0.4s ease');
        $revealed = $this->selectorsAlongside('.viewer img.is-loaded');

        foreach (['.card__image', '.find-item__img', '.viewer img'] as $selector) {
            self::assertStringContainsString(
                $selector,
                $hidden,
                sprintf('"%s" must ship hidden with the other lazy-loaded images.', $selector)
            );
            self::assertStringContainsString(
                $selector . '.is-loaded',
                $revealed,
                sprintf('"%s" must be revealed by the shared is-loaded marker.', $selector)
            );
        }
    }

    public function testReducedMotionCoversEveryCallerOfTheTreatment(): void
    {
        $css = $this->declarations();
        $start = strpos($css, '@media (prefers-reduced-motion: reduce) {');
        self::assertIsInt($start, 'The reduced-motion block must remain a single section.');
        $block = substr($css, $start, 400);

        // A shimmer is an animation and a fade is a transition; adding a caller to
        // one without adding it here reintroduces motion for the people who asked
        // for none.
        foreach (['.find-item__frame::before', '.viewer__placeholder', '.find-item__img', '.viewer img'] as $selector) {
            self::assertStringContainsString(
                $selector,
                $block,
                sprintf('"%s" must opt out of motion along with the poster cards.', $selector)
            );
        }
    }

    public function testCandidateCellReservesItsSpaceBeforeTheImageArrives(): void
    {
        // Without a sized frame the cells collapse, every candidate lands inside
        // the visible area, and native lazy loading has nothing left to defer.
        self::assertMatchesRegularExpression(
            '/\.find-item__frame\s*\{[^{}]*aspect-ratio: 2 \/ 3/',
            $this->declarations(),
            'The candidate frame must hold the cell at poster proportions.'
        );
    }

    public function testViewerResolvedStateIsClearedWhereverItsSourceIsSet(): void
    {
        $source = $this->gallerySource();

        // The clear must precede the assignment, and there must be no second
        // place that points a viewer at a poster without it — that is the
        // stale-poster flash. (Clearing the source on close is not one: a viewer
        // being emptied has nothing to reveal early.)
        self::assertSame(
            1,
            preg_match_all('/this\.viewer = url/', $source),
            'The full-screen viewer must have exactly one place that sets its source.'
        );
        self::assertMatchesRegularExpression(
            '/this\.viewerLoaded = false;\s*this\.viewer = url/',
            $source,
            'Opening the viewer must clear its resolved state before setting the source.'
        );

        self::assertSame(
            1,
            preg_match_all('/this\.finder\.preview = url/', $source),
            'The Find Posters preview must have exactly one place that sets its source.'
        );
        self::assertMatchesRegularExpression(
            '/this\.finder\.previewLoaded = false;\s*this\.finder\.preview = url/',
            $source,
            'Opening the preview must clear its resolved state before setting the source.'
        );
    }
}
