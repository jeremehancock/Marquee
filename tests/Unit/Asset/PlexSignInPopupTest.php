<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, not a behavior test.
 *
 * Three parts of the Plex sign-in flow are easy to undo without anything at the
 * call site complaining, and each fails in a way that only shows up in a real
 * browser.
 *
 * The window has to be opened synchronously inside the click handler. Moving it
 * into the `.then()` that receives the authorization URL reads better and is
 * exactly what popup blockers stop, because by then the user gesture is over.
 *
 * It has to be opened with size features. Without them `window.open` yields a
 * full tab, which pushes Marquee out of view for what is a single small form.
 *
 * And polling has to stay ordinary short request/response. Long-polling or an
 * event stream would be tidier code and would be buffered or cut by a reverse
 * proxy or CDN in front of a self-hosted install — the deployment this flow was
 * designed around.
 *
 * None of that is verifiable without a browser and this repo has no JS test
 * runner. These assertions pin the arrangement; the flow itself is verified by
 * hand against a real Plex server. See also ImportTrayReuseTest.
 */
final class PlexSignInPopupTest extends TestCase
{
    private string $js = '';

    protected function setUp(): void
    {
        $this->js = (string) file_get_contents(dirname(__DIR__, 3) . '/public/assets/gallery.js');
    }

    public function testTheWindowIsOpenedWithPopupSizing(): void
    {
        self::assertStringContainsString("'popup=yes,width='", $this->js);
        self::assertStringContainsString("'marquee-plex-signin'", $this->js);
    }

    public function testTheWindowIsOpenedInsideTheClickHandlerNotAfterTheRequest(): void
    {
        $signIn = $this->between('signIn: function ()', '_watch: function (deadline)');

        $openAt = strpos($signIn, 'this._open()');
        $fetchAt = strpos($signIn, "fetch('/plex/connection/sign-in'");

        self::assertNotFalse($openAt);
        self::assertNotFalse($fetchAt);
        // Opening must precede the request, not follow its promise.
        self::assertLessThan($fetchAt, $openAt);
    }

    public function testABlockedPopupStillOffersALink(): void
    {
        self::assertStringContainsString('self.fallbackUrl = result.data.authUrl;', $this->js);
    }

    public function testTheWindowIsClosedWhenSignInCompletes(): void
    {
        $watch = $this->between('_watch: function (deadline)', '_closeWindow: function ()');

        self::assertStringContainsString("data.status === 'completed'", $watch);
        self::assertStringContainsString('self._closeWindow();', $watch);
    }

    public function testPollingIsShortRequestResponse(): void
    {
        self::assertStringContainsString('window.setTimeout(', $this->between(
            '_watch: function (deadline)',
            '_closeWindow: function ()',
        ));
        // No held-open connection: a proxy in front of Marquee would buffer or
        // cut one, and the whole flow was chosen to avoid needing anything to
        // route back in.
        self::assertStringNotContainsString('EventSource', $this->js);
    }

    public function testPollingStopsAtADeadline(): void
    {
        $watch = $this->between('_watch: function (deadline)', '_closeWindow: function ()');

        self::assertStringContainsString('Date.now() > deadline', $watch);
        self::assertStringContainsString("data.status === 'expired'", $watch);
    }

    private function between(string $start, string $end): string
    {
        $from = strpos($this->js, $start);
        $to = strpos($this->js, $end);
        self::assertNotFalse($from, 'missing: ' . $start);
        self::assertNotFalse($to, 'missing: ' . $end);

        return substr($this->js, $from, $to - $from);
    }
}
