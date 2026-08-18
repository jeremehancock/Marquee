<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * Applying a previewed poster runs for seconds — the server fetches the image at
 * full resolution or takes it up from the browser, then pushes it to Plex — so
 * the confirm button must raise a progress overlay and refuse to run twice, from
 * whichever of the three tabs the preview was opened. That is browser behavior,
 * and this repo has no JS test runner; the real verification is by hand against
 * the :dev image.
 *
 * What is worth catching cheaply is the regression that would look fine in
 * review: the guard, the flag, or the `finally` quietly disappearing from
 * applyPreview(), leaving the overlay markup in place and the button bound to a
 * flag nothing sets any more. So these assertions pin the shape of that one
 * function and the markup it drives. They prove nothing about what the user
 * actually sees.
 */
final class PreviewApplyProgressTest extends TestCase
{
    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        return $source;
    }

    private function galleryTemplate(): string
    {
        $path = dirname(__DIR__, 3) . '/templates/gallery.html.twig';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.html.twig must be readable at ' . $path);

        return $source;
    }

    /**
     * The function body, from its own name to the next method after it.
     * Anchoring on both ends keeps the assertions honest if code moves.
     */
    private function applyFunction(): string
    {
        $source = $this->gallerySource();

        $start = strpos($source, 'applyPreview: function (');
        $end = strpos($source, 'copyUrl: function (');
        self::assertIsInt($start, 'applyPreview() must exist.');
        self::assertIsInt($end, 'copyUrl() must follow applyPreview().');
        self::assertGreaterThan($start, $end);

        return substr($source, $start, $end - $start);
    }

    public function testTheInFlightFlagIsSetBeforeTheRequest(): void
    {
        $body = $this->applyFunction();

        $flagged = strpos($body, 'this.preview.applying = true;');
        $fetched = strpos($body, 'fetch(');
        self::assertIsInt($flagged, 'The apply must mark itself in flight.');
        self::assertIsInt($fetched, 'The apply must issue a request.');
        self::assertLessThan(
            $fetched,
            $flagged,
            'The flag must be set before the request, or the first frame shows no progress.'
        );
    }

    public function testASecondActivationIsRefused(): void
    {
        $body = $this->applyFunction();

        // The overlay blocks the second tap only once it has painted; this
        // guard is what makes the refusal independent of paint timing.
        self::assertStringContainsString(
            'if (this.preview.applying) { return; }',
            $body,
            'A change already in flight must not be started again.'
        );
    }

    public function testTheFlagIsClearedOnBothPaths(): void
    {
        $body = $this->applyFunction();

        // In a finally, not in the then — a failure must lift the overlay too,
        // or the preview is stranded behind a spinner that never clears.
        self::assertMatchesRegularExpression(
            '/\.finally\(function \(\) \{ self\.preview\.applying = false; \}\)/',
            $body,
            'The in-flight flag must be cleared in a finally, so failures lift the overlay.'
        );
    }

    public function testAFailedResponseIsNotTreatedAsSuccess(): void
    {
        $body = $this->applyFunction();

        // Without this the error page is parsed as a success page and scraped
        // for its alert, so a change that failed reports as one that worked.
        self::assertStringContainsString(
            'if (!r.ok) { throw new Error(',
            $body,
            'An unsuccessful response must be reported as a failure.'
        );
    }

    public function testEveryPreviewStateLiteralCarriesTheFlag(): void
    {
        $source = $this->gallerySource();

        // The preview object is rebuilt whole in three places (the component's
        // own literal, openPreview and closePreview). One of them missing the
        // flag would silently reset it to undefined mid-flow — which is exactly
        // how the overlay ends up bound to nothing.
        $literals = substr_count($source, 'loaded: false');
        $flags = substr_count($source, 'applying: false');

        self::assertGreaterThan(0, $literals);
        self::assertSame(
            $literals,
            $flags,
            'Every literal that rebuilds preview state must carry the applying flag.'
        );
    }

    /**
     * The apply is one path for four sources; what the source picks is the
     * endpoint and the field, and nothing else. A regression here would post a
     * File to the URL endpoint, or a Plex token to the endpoint that fetches
     * whatever address it is handed — both fail in ways no test would otherwise
     * catch until a user tried it.
     */
    public function testTheSourceSelectsTheEndpointAndTheField(): void
    {
        $body = $this->applyFunction();

        self::assertStringContainsString(
            "var field = upload ? 'poster' : (plex ? 'token' : 'url');",
            $body,
            'A picked file must post as the file field, a Plex candidate as its token, a URL as the url field.'
        );
        self::assertStringContainsString(
            "var endpoint = upload ? 'upload' : (plex ? 'plex-poster' : 'url');",
            $body,
            'Each source must go to the endpoint that knows how to read it.'
        );
        self::assertStringContainsString('body.append(field, payload);', $body);
        self::assertStringContainsString("'/change/' + endpoint", $body);
    }

    /**
     * A Plex candidate is named by a signed token, never by the proxy address
     * its preview is displaying. Posting `src` would send the server a URL to
     * fetch from itself — a loopback that needs the session cookie and breaks
     * wherever the container cannot resolve its own external address.
     */
    public function testAPlexCandidateIsAppliedByItsToken(): void
    {
        self::assertStringContainsString(
            'plex ? this.preview.token : this.preview.src',
            $this->applyFunction(),
            'Applying a Plex candidate must post its signed token, not the proxy URL.'
        );
    }

    /**
     * A blob URL holds its Blob alive until it is revoked. Revoking in the
     * `finally` would blank the image a failed change is still showing, so the
     * two lifecycle points are the only correct places — and the apply must not
     * be one of them.
     */
    public function testTheObjectUrlIsNotRevokedByTheApply(): void
    {
        $source = $this->gallerySource();
        $body = $this->applyFunction();

        self::assertStringNotContainsString(
            'revokeObjectURL',
            $body,
            'Applying must not revoke the preview it may have to keep showing.'
        );
        self::assertSame(
            1,
            preg_match_all('/URL\.revokeObjectURL/', $source),
            'Revoking belongs in one helper, called when the preview closes or is replaced.'
        );
        self::assertMatchesRegularExpression(
            '/openPreview: function \(src, source, file, token\) \{\s*this\._revokePreviewSrc\(\);/',
            $source,
            'Opening a preview must release the object URL it replaces.'
        );
        self::assertMatchesRegularExpression(
            '/closePreview: function \(\) \{\s*this\._revokePreviewSrc\(\);/',
            $source,
            'Closing the preview must release its object URL.'
        );
    }

    public function testTheConfirmButtonIsSwitchedOffWhileTheChangeRuns(): void
    {
        $template = $this->galleryTemplate();

        self::assertStringContainsString(
            'applyPreview()',
            $template,
            'The confirm button must call the apply.'
        );
        // aria-disabled rather than the disabled attribute, which would drop the
        // button out of the tab order at the moment it is focused — the browser
        // then puts focus on the body, and a keyboard user loses their place in
        // the middle of the change they just started. See DisabledStateTest,
        // which pins the guard that has to stand behind every such binding now
        // that the attribute no longer enforces anything.
        self::assertStringContainsString(
            ':aria-disabled="preview.applying ? \'true\' : \'false\'"',
            $template,
            'The confirm button must be switched off while a change is in flight.'
        );
    }

    public function testTheProgressOverlayIsPresentAndBoundToTheFlag(): void
    {
        $template = $this->galleryTemplate();

        self::assertStringContainsString(
            '<div class="overlay" x-show="preview.applying"',
            $template,
            'Applying must raise a progress overlay bound to the in-flight flag.'
        );
        // The same treatment the import and orphan overlays use.
        self::assertMatchesRegularExpression(
            '/x-show="preview\.applying".*?<div class="spinner">/s',
            $template,
            'The progress overlay must show the standard spinner.'
        );
    }

    /**
     * The overlay must not be inside the change-poster modal panel: the
     * full-screen preview covers that panel, so an overlay confined to it would
     * sit underneath the thing the user is looking at.
     */
    public function testTheOverlayLivesWithTheFullScreenPreview(): void
    {
        $template = $this->galleryTemplate();

        $viewer = strpos($template, 'viewer viewer--preview');
        $overlay = strpos($template, 'x-show="preview.applying"');
        $sortTray = strpos($template, 'Sort tray (phone)');

        self::assertIsInt($viewer, 'The preview must exist.');
        self::assertIsInt($overlay, 'The progress overlay must exist.');
        self::assertIsInt($sortTray, 'The sort tray must follow the preview.');
        self::assertGreaterThan($viewer, $overlay, 'The overlay belongs to the preview, not the modal.');
        self::assertLessThan($sortTray, $overlay, 'The overlay must stay inside the preview block.');
    }
}
