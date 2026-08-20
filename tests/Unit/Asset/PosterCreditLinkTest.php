<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * The credit link shown on a candidate whose supplying service requires one.
 *
 * Some of the artwork Find Posters returns is licensed on terms discharged by
 * linking back to the service from where the image is shown, and the poster
 * source signals that per poster by sending a `page` address. Showing that link
 * is an obligation: artwork carrying the address must not be displayed without
 * it.
 *
 * Both places the link appears are drawn by Alpine from JSON, so no rendering
 * assertion can reach them — the markup is the only artefact a test can hold. It
 * is held here for the same reason DisabledStateTest holds its bindings: the
 * failure is silent, and the fix for a silent failure is to write it down
 * somewhere that fails.
 *
 * What this cannot catch is a *new* surface that displays a candidate without
 * its credit. There are two today.
 */
final class PosterCreditLinkTest extends TestCase
{
    private function gallery(): string
    {
        $path = dirname(__DIR__, 3) . '/templates/gallery.html.twig';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.html.twig must be readable at ' . $path);

        return $source;
    }

    private function script(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    /**
     * Comments stripped, for the reason DisabledStateTest gives: the template
     * explains this binding at length directly above it, and a substring search
     * would otherwise find the explanation instead of the code.
     */
    private function markup(): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', $this->gallery());
    }

    public function testTheGridCellCreditsACandidateThatCarriesAPage(): void
    {
        $markup = $this->markup();

        self::assertStringContainsString('class="find-item__credit"', $markup);
        self::assertStringContainsString('x-show="poster.page"', $markup);
        self::assertStringContainsString(':href="poster.page"', $markup);
    }

    public function testTheFullScreenPreviewCreditsItToo(): void
    {
        $markup = $this->markup();

        self::assertStringContainsString('class="viewer__credit"', $markup);
        self::assertStringContainsString('x-show="preview.page"', $markup);
        self::assertStringContainsString(':href="preview.page"', $markup);
    }

    /**
     * **The guard that matters.**
     *
     * The condition is the presence of the address and never the name of the
     * service that sent it. The poster source decides which of its providers owe
     * a link back; TVmaze is simply the only one populating the field today, and
     * a name test here would be wrong the moment that changes — silently, since
     * the posters would still appear, just uncredited.
     *
     * `source` is not even published to the page for this reason, so writing the
     * check that way would take a payload change too. This asserts the negative
     * because the positive above would still pass beside it.
     */
    public function testTheCreditIsNotConditionedOnWhichServiceSuppliedThePoster(): void
    {
        $markup = $this->markup();

        foreach (['tvmaze', 'tmdb', 'thetvdb', 'fanart'] as $slug) {
            self::assertStringNotContainsStringIgnoringCase(
                $slug,
                $markup,
                'The Find Posters markup must not name a provider. The credit link is driven by '
                . 'the presence of poster.page, so that a service the source adds later is credited '
                . 'without a client release.',
            );
        }
    }

    /**
     * Opening the link must not be a way to leave the app by accident, and must
     * not double as choosing the poster. The anchor is a sibling of the image
     * that carries the preview click, and stops the event.
     */
    public function testTheGridCreditDoesNotHijackTheCandidatesOwnAction(): void
    {
        $markup = $this->markup();

        self::assertStringContainsString('@click.stop', $markup);
        self::assertMatchesRegularExpression(
            '/<img class="find-item__img"[^>]*@click="openPreview\(/s',
            $markup,
            'The thumbnail itself must keep the press that opens the preview.',
        );
    }

    /**
     * Every credit link leaves the application, so each opens in a new browsing
     * context and neither hands the opener over.
     */
    public function testBothCreditLinksOpenSafelyInANewContext(): void
    {
        $markup = $this->markup();

        preg_match_all('/<a class="(?:find-item|viewer)__credit".*?>/s', $markup, $anchors);

        self::assertCount(2, $anchors[0], 'Expected exactly the two credit links.');

        foreach ($anchors[0] as $anchor) {
            self::assertStringContainsString('target="_blank"', $anchor);
            self::assertStringContainsString('rel="noopener"', $anchor);
        }
    }

    /**
     * A candidate with no page renders no control at all — not a disabled one.
     *
     * `aria-disabled` is the project's rule for a control that is switched off,
     * but that rule is about a control which exists and is momentarily
     * unavailable. This one has no such state: either the source sent an address
     * or the link is not part of the interface. Announcing an inert link on
     * every TMDB poster would be noise, and `x-show` is what keeps it absent.
     */
    public function testTheCreditIsOmittedRatherThanDisabled(): void
    {
        $markup = $this->markup();

        preg_match_all('/<a class="(?:find-item|viewer)__credit".*?>/s', $markup, $anchors);

        foreach ($anchors[0] as $anchor) {
            self::assertStringNotContainsString('aria-disabled', $anchor);
            self::assertStringContainsString('x-show=', $anchor);
        }
    }

    /**
     * The preview is shared by all four tabs, so its credit is only correct if
     * the state it reads is cleared between posters. A stale page would credit
     * the previous candidate's service on the current one — worse than no link,
     * because it is a false attribution.
     */
    public function testThePreviewPageIsClearedWhenThePreviewCloses(): void
    {
        $script = $this->script();

        self::assertMatchesRegularExpression(
            '/closePreview: function \(\) \{.*?page: \x27\x27.*?\}/s',
            $script,
            'closePreview() must clear page along with the rest of the preview state.',
        );
    }

    /**
     * Only Find Posters passes a page. The other three tabs preview an address
     * the user supplied or artwork Plex holds, neither of which carries a credit
     * obligation, and the default keeps them from inheriting one.
     */
    public function testOnlyTheFoundCandidatePassesAPageIntoThePreview(): void
    {
        $markup = $this->markup();

        self::assertMatchesRegularExpression(
            '/openPreview\(poster\.url, \x27find\x27,[^)]*poster\.page\)/',
            $markup,
            'The Find Posters grid must hand the candidate page to the preview.',
        );
    }
}
