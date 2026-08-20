<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * The two links a Find Posters candidate can carry, and the fact that they are
 * not the same link.
 *
 * - The **required credit** must appear wherever a marked poster is shown. Some
 *   of the artwork Find Posters returns is licensed on terms discharged only by
 *   linking back from where the image is shown, and marked artwork must not be
 *   displayed without it. Not our decision to make.
 * - The **provenance link** is offered for any candidate the source gave an
 *   address for, which is nearly all of them. It is a product decision, and could
 *   be moved or dropped tomorrow.
 *
 * They are drawn the same and in the same places — a badge on the poster in the
 * grid, a link under the full-screen preview — because the licence asks that the
 * link be shown, not that it be shown differently. So the distinction survives in
 * the *conditions* and in a `data-` attribute rather than in the pixels, and that
 * is what this file holds.
 *
 * The reason to hold it: presence and obligation were briefly the same fact, when
 * the endpoint sent an address only on the licensed subset. They no longer are,
 * but a condition covering both still renders identically to one covering only
 * the address — so the collapse costs nothing visible and loses the obligation
 * entirely.
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
        $badge = $this->openingTag('find-item__credit');

        self::assertStringContainsString('poster.attributionRequired', $this->condition('find-item__credit'));
        // The marking says a credit is owed; the address says where it points.
        self::assertStringContainsString(':href="poster.page"', $badge);
    }

    /**
     * The badge is also offered as provenance, on any candidate with an address.
     *
     * That is a product decision — it puts a link on nearly every result — and it
     * is deliberately the *second* clause of the condition. See the test below for
     * why the order is load-bearing.
     */
    public function testTheGridBadgeIsAlsoOfferedForProvenance(): void
    {
        self::assertStringContainsString('poster.page', $this->condition('find-item__credit'));
    }

    /**
     * **The guard this file exists for.**
     *
     * The badge is shown on nearly every candidate, so the two reasons it can
     * appear are indistinguishable on screen — which is fine, since the licence
     * asks that the link be shown, not that it be shown differently. What is not
     * fine is the obligation vanishing from the code, because then the next
     * change to how optional links are presented takes the required one with it
     * and nothing says so.
     *
     * `poster.page` alone would render identically today. This asserts the
     * condition still names the marking, so removing the provenance clause leaves
     * the credit behind instead of deleting it.
     *
     * Not hypothetical: keying solely on the address is what this code did before
     * the source separated the two fields, and it stayed compliant only by
     * over-attributing — which is luck, not design.
     */
    public function testTheGridBadgeStillNamesTheObligation(): void
    {
        $condition = $this->condition('find-item__credit');

        self::assertStringContainsString(
            'attributionRequired',
            $condition,
            'The badge condition must name the attribution marking even while it also shows for '
            . 'provenance. Reducing it to the source address renders the same today and silently '
            . 'discards the one credit that may never be removed.',
        );

        self::assertStringStartsWith(
            'poster.attributionRequired',
            $condition,
            'The obligation is the first clause: "always when required, also when we have a page". '
            . 'Reversed, it reads as an afterthought and invites being tidied away.',
        );
    }

    /**
     * Both badges look alike, so the distinction lives in the DOM instead — the
     * one place a reader, a test, or a future stylesheet can still find it.
     */
    public function testTheMarkedBadgeIsIdentifiableInTheDom(): void
    {
        self::assertStringContainsString(
            ':data-attribution-required="poster.attributionRequired',
            $this->openingTag('find-item__credit'),
            'A badge shown for a licence reason must be distinguishable from one shown for a '
            . 'product reason, even when the two are drawn identically.',
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
     * The grid badge and the preview link read their own conditions.
     *
     * They cover nearly the same candidates now, which is exactly when someone
     * decides one flag would do for both. It would not: the badge has to keep
     * naming the obligation, and the preview link is pure provenance with nothing
     * required of it. Sharing a condition would tie a licence credit to a
     * decision about how previews look.
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
