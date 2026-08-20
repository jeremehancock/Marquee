<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * The two links a Find Posters candidate can carry, and the fact that they are
 * not the same link.
 *
 * - The **required credit** is a badge on the poster in the results grid, shown
 *   when the poster source marks a candidate as requiring attribution. Some of
 *   the artwork Find Posters returns is licensed on terms discharged only by
 *   linking back from where the image is shown, and marked artwork must not be
 *   displayed without it. Not our decision to make.
 * - The **provenance link** sits in the full-screen preview and is shown for any
 *   candidate the source gave an address for, which is nearly all of them. It is
 *   a product decision, and could be moved or dropped tomorrow.
 *
 * Keeping them apart is what this file is for. They were briefly the same thing:
 * the endpoint once sent an address only on the licensed subset, so presence and
 * obligation coincided. They no longer do, and the collapse is easy to
 * reintroduce because the two controls look related and sit a few lines apart.
 *
 * Both are drawn by Alpine from JSON, so no rendering assertion can reach them —
 * the markup is the only artefact a test can hold. It is held here for the same
 * reason DisabledStateTest holds its bindings: the failure is silent, and the fix
 * for a silent failure is to write it down somewhere that fails.
 *
 * What this cannot catch is a *new* surface that displays a marked candidate
 * without its credit. There are two surfaces today.
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

    /**
     * One link's opening tag, by class. Fails rather than returning nothing when
     * the element is missing, so a test that has lost its subject says so instead
     * of passing against an empty string.
     */
    private function openingTag(string $class): string
    {
        $found = preg_match('/<a class="' . preg_quote($class, '/') . '".*?>/s', $this->markup(), $m);

        self::assertSame(1, $found, 'Expected one <a class="' . $class . '"> in the gallery markup.');

        return $m[0];
    }

    /**
     * The Alpine condition that decides whether that link is shown.
     */
    private function condition(string $class): string
    {
        $found = preg_match('/x-show="([^"]+)"/', $this->openingTag($class), $m);

        self::assertSame(1, $found, 'The ' . $class . ' link must be shown conditionally.');

        return $m[1];
    }

    public function testTheGridBadgeCreditsAMarkedCandidate(): void
    {
        $markup = $this->markup();

        self::assertStringContainsString('class="find-item__credit"', $markup);
        self::assertStringContainsString('x-show="poster.attributionRequired"', $markup);
        // The marking says a credit is owed; the address says where it points.
        self::assertStringContainsString(':href="poster.page"', $markup);
    }

    /**
     * **The guard this file exists for.**
     *
     * Nearly every candidate now carries a source address, so a badge bound to
     * one would appear on all ~189 results of a show search — asserting a licence
     * condition over TMDB, TheTVDB and fanart.tv artwork that carries none, and
     * leaving the real obligation indistinguishable from decoration.
     *
     * That is not hypothetical: it is what this code did before the contract
     * separated the two fields, and it stayed compliant only by over-attributing.
     * The negative assertion is what makes reintroducing it fail rather than pass
     * quietly.
     */
    public function testTheGridBadgeIsNotBoundToTheSourceAddress(): void
    {
        self::assertStringNotContainsString(
            'x-show="poster.page"',
            $this->openingTag('find-item__credit'),
            'The required credit must be bound to the attribution marking, never to the presence of '
            . 'a source address — nearly every candidate has one of those.',
        );
    }

    public function testTheFullScreenPreviewOffersProvenanceForAnyCandidateWithAnAddress(): void
    {
        $markup = $this->markup();

        self::assertStringContainsString('class="viewer__credit"', $markup);
        self::assertStringContainsString('x-show="preview.page"', $markup);
        self::assertStringContainsString(':href="preview.page"', $markup);
    }

    /**
     * The two controls read different conditions, and that difference is the
     * design rather than an accident of how they were written.
     *
     * Collapsing them either way is a defect: one condition on both means the
     * badge returns to every poster, or the preview link vanishes from the three
     * quarters of candidates that are unmarked.
     */
    public function testTheTwoLinksAreDrivenByDifferentConditions(): void
    {
        self::assertNotSame(
            $this->condition('find-item__credit'),
            $this->condition('viewer__credit'),
            'The required credit and the provenance link must not share a condition.',
        );
    }

    /**
     * The provenance link names the service and what it opens, and says nothing
     * about licensing.
     *
     * It is shown on candidates from every source, and most of those are under no
     * attribution condition at all — so wording like "Attribution required" or
     * "CC BY-SA" here would be a licence claim Marquee has no basis to make. The
     * words are asserted, not just their absence, because the risk is someone
     * later making this copy "more informative".
     */
    public function testTheProvenanceWordingMakesNoLicenceClaim(): void
    {
        $credit = $this->openingTag('viewer__credit');

        self::assertStringContainsString("'View on '", $credit);

        foreach (['attribution', 'licen', 'CC BY', 'required', 'must'] as $claim) {
            self::assertStringNotContainsStringIgnoringCase(
                $claim,
                $credit,
                'The provenance link is shown on candidates under no attribution condition, so its '
                . 'wording must not state or imply that one exists.',
            );
        }
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
     * Only Find Posters passes a page and a service name. The other three tabs
     * preview an address the user supplied or artwork Plex holds, neither of
     * which has a supplying service to name, and the defaults keep them from
     * inheriting one.
     */
    public function testOnlyTheFoundCandidatePassesAPageIntoThePreview(): void
    {
        $markup = $this->markup();

        self::assertMatchesRegularExpression(
            '/openPreview\(poster\.url, \x27find\x27,[^)]*poster\.page, section\.label\)/',
            $markup,
            'The Find Posters grid must hand the candidate page and its section label to the preview.',
        );
    }

    /**
     * The service name reaches the preview by being passed in, never by being
     * resolved in the browser.
     *
     * The candidate's provider slug is deliberately withheld from the payload, so
     * a lookup here would be the first place the page learned a provider — and
     * would hand whoever writes it the map that makes keying the credit on a
     * service name a one-line change. What is passed is the section's label,
     * which the server already resolved.
     */
    public function testThePreviewIsToldTheServiceNameRatherThanResolvingIt(): void
    {
        $script = $this->script();

        self::assertMatchesRegularExpression(
            '/openPreview: function \([^)]*service\)/',
            $script,
            'openPreview() must accept the service name as an argument.',
        );

        self::assertMatchesRegularExpression(
            '/closePreview: function \(\) \{.*?service: \x27\x27.*?\}/s',
            $script,
            'closePreview() must clear the service name with the rest of the preview state.',
        );
    }
}
