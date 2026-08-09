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
        //
        // Matched on tokens rather than on literal values: the fade's length is
        // --dur-fade's to set, and pinning a number here would fail this test
        // whenever the motion scale is retuned, for no reason. What the test is
        // actually for is the membership of the two lists.
        //
        // The easing is part of the match and has to be. --dur-fade is also what
        // .alert--fading uses, so duration alone matches two unrelated rules and
        // the helper — which requires exactly one — resolves to whichever comes
        // first in the file. Arriving eases differently from leaving, so the pair
        // is unambiguous.
        $hidden = $this->selectorsDeclaring(
            'transition: opacity var(--dur-fade) var(--ease-entrance)',
        );
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

    /**
     * This used to assert the opposite, and the inversion is the point.
     *
     * Reduced motion was once a list of five selectors, so every caller of the
     * shimmer and the lazy fade had to be named in it — and a caller added to the
     * treatment but not to that list went on animating for the people who asked
     * it not to, with nothing failing to say so. The list is a blanket rule now,
     * so a new caller is covered the moment it exists and naming it would be
     * redundant. What is left to protect is the exemption: the shimmer reports
     * that a poster is on its way, and a frozen shimmer says it never will be.
     */
    public function testReducedMotionCoversEverythingAndExemptsProgress(): void
    {
        $block = $this->reducedMotionBlock();

        // The blanket itself. Without the pseudo-element arms the shimmer would
        // not be reached at all, since every caller of it draws on ::before.
        foreach (['*', '*::before', '*::after'] as $selector) {
            self::assertMatchesRegularExpression(
                '/^\s*' . preg_quote($selector, '/') . '\s*[,{]/m',
                $block,
                'Reduced motion must stay a blanket rule: a list of selectors is '
                . 'wrong by default every time something new is animated.',
            );
        }

        // Collapsed, not removed. `transition: none` suppresses transitionend and
        // a zero-length animation can skip animationend, so anything awaiting
        // either would wait forever.
        self::assertStringContainsString('animation-duration: 0.01ms', $block);
        self::assertStringContainsString('transition-duration: 0.01ms', $block);
        self::assertStringNotContainsString(
            'transition: none',
            $block,
            'Collapsing to zero costs the transitionend event.',
        );

        // Every caller of the shimmer keeps it, or one of the three places a
        // poster is awaited stops reporting that it is coming.
        foreach ([
            '.card__frame::before' => 'the gallery card',
            '.find-item__frame::before' => 'the Find Posters candidate cell',
            '.viewer__placeholder' => 'the full-screen views',
        ] as $selector => $what) {
            self::assertStringContainsString(
                $selector,
                $block,
                sprintf('The shimmer for %s must stay exempt: a frozen placeholder '
                    . 'reads as a poster that is never arriving.', $what),
            );
        }

        self::assertStringContainsString(
            '.spinner',
            $block,
            'A stopped spinner over a running import reads as an import that hung.',
        );
    }

    /**
     * The whole `@media (prefers-reduced-motion: reduce)` section, braces matched.
     *
     * Read by counting braces rather than by taking a fixed number of characters:
     * the block carries nested rules now, so a fixed window ends inside the first
     * of them and silently stops seeing the rest.
     */
    private function reducedMotionBlock(): string
    {
        $css = $this->declarations();
        $start = strpos($css, '@media (prefers-reduced-motion: reduce) {');
        self::assertIsInt($start, 'The reduced-motion block must remain a single section.');

        $depth = 0;
        for ($i = (int) strpos($css, '{', $start); $i < strlen($css); $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($css, $start, $i - $start + 1);
                }
            }
        }

        self::fail('The reduced-motion block is unterminated.');
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

    public function testFullScreenPlaceholderIsOutOfFlow(): void
    {
        $css = $this->declarations();

        // A browser sizes the <img> box from the image's dimensions long before
        // the image arrives. Left in flow, that transparent box sits beside the
        // placeholder in a centred row and shoves it sideways for the whole of a
        // slow download — which is the jarring shift this rule exists to prevent.
        self::assertMatchesRegularExpression(
            '/\.viewer__placeholder\s*\{[^{}]*position: absolute/',
            $css,
            'The full-screen placeholder must stay out of flow, or the loading image displaces it.'
        );
        self::assertMatchesRegularExpression(
            '/\.viewer__stage\s*\{[^{}]*position: relative/',
            $css,
            'The stage must remain the placeholder\'s positioning context.'
        );
    }

    public function testFullScreenPlaceholderIsCentredRatherThanTopAnchored(): void
    {
        // The placeholder sets both block insets, but its height comes from the
        // aspect ratio as soon as max-width clamps the box. That is
        // over-constrained, and the browser honours `top` — pinning it to the top
        // of the stage. Wherever width is the limiting axis, which is every phone,
        // that is nowhere near where the poster lands: measured in Chromium at a
        // 390x1400 viewport, the placeholder sat at top=24 against the image's
        // top=318. Auto block margins centre it, matching the image to the pixel.
        self::assertMatchesRegularExpression(
            '/\.viewer__placeholder\s*\{[^{}]*margin-block: auto/',
            $this->declarations(),
            'The full-screen placeholder must centre itself, or it sits above where the poster lands.'
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

        // The change-poster preview sets its source and its resolved state in one
        // literal, so the two cannot come apart — but only while there is exactly
        // one such literal. A second place assigning `src` on its own would be the
        // stale-poster flash again, in the overlay all three tabs now share.
        self::assertSame(
            1,
            preg_match_all('/src: src,/', $source),
            'The change-poster preview must have exactly one place that sets its source.'
        );
        self::assertMatchesRegularExpression(
            '/src: src,\s*loaded: false,/',
            $source,
            'Opening the preview must clear its resolved state alongside the source.'
        );
    }
}
