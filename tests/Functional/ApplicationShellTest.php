<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AppTestCase;

final class ApplicationShellTest extends AppTestCase
{
    /**
     * The opening tags of the two footers the shared layout renders: the page
     * footer, and the menu tray's, which replaces it on a phone. Both carry the
     * same chrome, so both are asserted against.
     *
     * @var list<string>
     */
    private const FOOTERS = ['<footer class="footer">', '<div class="menu__footer"'];

    /** @var list<string> */
    private const PROVIDERS = [
        'https://www.themoviedb.org/',
        'https://www.thetvdb.com/',
        'https://fanart.tv/',
    ];


    /**
     * The header's secondary navigation, isolated from the tray, which renders
     * the same macro on every page and would otherwise satisfy any assertion made
     * against the whole body.
     */
    private function header(string $body): string
    {
        $matched = preg_match('#<div class="topnav__desktop">.*?\n\s*</div>#s', $body, $m);
        self::assertSame(1, $matched, 'The topbar must render the desktop navigation.');

        return $m[0];
    }

    /**
     * These links used to live in the gallery's own toolbar, which left /plex and
     * /orphans with no navigation at all — reaching one from the other meant a
     * detour through the gallery. Rendering them from the shared layout is what
     * fixes that, so the reach is asserted on a page that is not the gallery.
     */
    public function testSecondaryActionsRenderInTheHeaderOnEveryPageWithNavigation(): void
    {
        // Authenticated rather than bypassed, so Log out is part of the group.
        $app = $this->makeConnectedApp(['AUTH_BYPASS' => 'false']);
        $this->postForm($app, '/login', ['username' => 'admin', 'password' => 'secret']);

        foreach (['/library/movies', '/plex', '/orphans'] as $path) {
            $header = $this->header((string) $this->get($app, $path)->getBody());

            foreach (['Poster Wall', 'Import from Plex', 'Orphans', 'Support Development'] as $label) {
                self::assertStringContainsString(
                    'aria-label="' . $label . '"',
                    $header,
                    sprintf('%s must be reachable from the header on %s.', $label, $path),
                );
            }

            // Log out is presented like the rest rather than as a plain link, so
            // the group reads as one set of actions.
            self::assertStringContainsString('href="/logout"', $header);
            self::assertStringContainsString('nav-item', $header);
            self::assertStringNotContainsString('<nav><a href="/logout">', $header);
        }
    }

    /**
     * Every item renders both labels and lets CSS choose, so the visible text can
     * shorten — or vanish entirely in the narrow band. That makes the visible text
     * unusable as the accessible name, which is why each item carries an explicit
     * one holding the full name.
     */
    public function testHeaderActionsCarryFullNamesRegardlessOfTheVisibleLabel(): void
    {
        $header = $this->header((string) $this->get(
            $this->makeConnectedApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody());

        // Both forms are present in the markup...
        self::assertStringContainsString('>Import from Plex</span>', $header);
        self::assertStringContainsString('nav-label--short" aria-hidden="true">Import</span>', $header);
        // ...but only the full one names the control.
        self::assertStringContainsString('aria-label="Import from Plex"', $header);
        self::assertStringNotContainsString('aria-label="Import"', $header);
    }

    /**
     * A link pointing at the page you are already on is the thing being avoided,
     * so the href goes rather than merely being marked — the item renders as a
     * span, which is what actually stops it behaving as a link.
     */
    public function testTheCurrentDestinationIsMarkedAndNotLinked(): void
    {
        $app = $this->makeConnectedApp(['AUTH_BYPASS' => 'true']);

        $plex = $this->header((string) $this->get($app, '/plex')->getBody());
        self::assertMatchesRegularExpression(
            '#<span class="btn nav-item nav-item--current" aria-current="page" aria-label="Import from Plex"#',
            $plex,
            'Import must not link to the page it is on.',
        );
        self::assertStringNotContainsString('href="/plex"', $plex);
        // The others on that page are untouched.
        self::assertStringContainsString('href="/orphans"', $plex);

        $orphans = $this->header((string) $this->get($app, '/orphans')->getBody());
        self::assertStringContainsString('aria-current="page" aria-label="Orphans"', $orphans);
        self::assertStringNotContainsString('href="/orphans"', $orphans);
        self::assertStringContainsString('href="/plex"', $orphans);
    }

    /**
     * The tray shares the macro with the header but has a full-width row per item,
     * so it keeps the full name where the header shortens it.
     */
    public function testTrayKeepsTheFullNamesTheHeaderShortens(): void
    {
        $body = (string) $this->get(
            $this->makeConnectedApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody();

        $matched = preg_match('#<div class="sheet__body menu__body".*?\n\s*</div>#s', $body, $m);
        self::assertSame(1, $matched, 'The menu tray must render its link list.');

        self::assertStringContainsString('>Import from Plex</span>', $m[0]);
        self::assertStringContainsString('>Support Development</span>', $m[0]);
    }

    public function testLoginPageRendersNoSecondaryNavigation(): void
    {
        $body = (string) $this->get($this->makeApp(), '/login')->getBody();

        self::assertStringNotContainsString('topnav__desktop', $body);
        self::assertStringNotContainsString('aria-label="Import from Plex"', $body);
        self::assertStringNotContainsString('menu-btn', $body);
    }

    public function testHeaderOmitsLogOutWhenAuthenticationIsBypassed(): void
    {
        $header = $this->header((string) $this->get(
            $this->makeConnectedApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody());

        self::assertStringNotContainsString('href="/logout"', $header);
        // The rest of the group is unaffected.
        self::assertStringContainsString('aria-label="Support Development"', $header);
    }

    public function testHealthReturnsOkWithoutAuthentication(): void
    {
        $response = $this->get($this->makeApp(), '/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('"status":"ok"', (string) $response->getBody());
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $response = $this->get($this->makeConnectedApp(['AUTH_BYPASS' => 'true']), '/does-not-exist');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testProtectedRouteRedirectsToLoginWhenUnauthenticated(): void
    {
        $response = $this->get($this->makeApp(), '/');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testGalleryRendersSiteTitleAsTheBrand(): void
    {
        $response = $this->get($this->makeConnectedApp(['AUTH_BYPASS' => 'true', 'SITE_TITLE' => 'My Wall']), '/library/movies');

        self::assertSame(200, $response->getStatusCode());
        // Assert the brand link specifically: a bare substring check would also
        // pass on the tab <title>, and so could not tell the two apart. The
        // brand carries the logo mark plus the title in a <span>.
        self::assertStringContainsString(
            '<span>My Wall</span>',
            (string) $response->getBody(),
        );
    }

    /**
     * The glyph is the menu's whole promise to the user, and it is the one part
     * of this control a stylesheet cannot get wrong loudly — a hamburger would
     * still open the tray, just after telling the user to expect a navigation
     * drawer that slides in from an edge. Nothing behind the button navigates in
     * place on a phone, so the overflow form is the honest one.
     */
    public function testMenuTriggerPresentsAnOverflowGlyphRatherThanAHamburger(): void
    {
        $body = (string) $this->get(
            $this->makeConnectedApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody();

        $matched = preg_match('#<button[^>]*class="menu-btn".*?</button>#s', $body, $m);
        self::assertSame(1, $matched, 'The topbar must render the menu trigger.');
        $button = $m[0];

        // Three dots on one line. Asserting the count matters: a single circle
        // would satisfy a bare substring check and read as something else.
        self::assertSame(
            3,
            preg_match_all('/<circle\b/', $button),
            'The overflow glyph is three dots.',
        );
        self::assertStringNotContainsString(
            '<path',
            $button,
            'The hamburger rules must be gone, not merely joined by the dots.',
        );
        // The glyph is decorative; the button's name is what a screen reader
        // announces, and it must survive the swap.
        self::assertStringContainsString('aria-label="Actions"', $button);
        self::assertStringContainsString('aria-haspopup="true"', $button);
    }

    /**
     * The keyword reads like boilerplate and would be an easy thing to drop while
     * tidying the meta tag, so it is pinned here.
     *
     * Under the default `resizes-visual`, an on-screen keyboard shrinks only the
     * visual viewport while `position: sticky` keeps resolving against the layout
     * viewport. The pinned toolbar then stays anchored above the visible area, and
     * scrolling with the keyboard up pushes it out of view entirely — which is the
     * bug this keyword fixes, on the one browser family that honours it.
     */
    public function testViewportLetsTheKeyboardResizeTheLayoutViewport(): void
    {
        $body = (string) $this->get(
            $this->makeConnectedApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody();

        $matched = preg_match('/<meta name="viewport" content="([^"]*)"/', $body, $m);
        self::assertSame(1, $matched, 'The shared layout must declare a viewport.');

        self::assertStringContainsString('width=device-width', $m[1]);
        self::assertStringContainsString(
            'interactive-widget=resizes-content',
            $m[1],
            'The pinned toolbar needs the layout viewport to be the visible region when the keyboard is up.',
        );
    }

    public function testBothFootersLinkTheProductNameToTheProjectSite(): void
    {
        $body = (string) $this->get(
            $this->makeConnectedApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody();

        // The page footer and the mobile tray's footer show the same credit
        // line, so both link the product name — and both open a new tab, which
        // requires rel="noopener".
        foreach (self::FOOTERS as $open) {
            self::assertMatchesRegularExpression(
                '#' . preg_quote($open, '#') . '.*?<a ([^>]*)>Marquee</a>#s',
                $body,
                $open . ' must wrap the product name in a link.',
            );

            // Attributes may wrap across lines, but no single attribute does,
            // so the raw capture can be searched as-is.
            preg_match('#' . preg_quote($open, '#') . '.*?<a ([^>]*)>Marquee</a>#s', $body, $m);
            $attributes = $m[1] ?? '';

            self::assertStringContainsString('href="https://getmarquee.now"', $attributes);
            self::assertStringContainsString('target="_blank"', $attributes);
            self::assertStringContainsString('rel="noopener"', $attributes);
        }
    }

    public function testBothFootersCreditThePosterProviders(): void
    {
        $body = (string) $this->get(
            $this->makeConnectedApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody();

        foreach (self::FOOTERS as $open) {
            // Capture what each footer renders *before* its product-name link:
            // the credit belongs above that line, so matching this way asserts
            // the order as a side effect of finding the block at all.
            preg_match(
                '#' . preg_quote($open, '#') . '(.*?)<a [^>]*>Marquee</a>#s',
                $body,
                $m,
            );
            $credit = $m[1] ?? '';

            self::assertStringContainsString(
                'Posters provided by:',
                $credit,
                $open . ' must credit the poster providers above the version line.',
            );

            foreach (self::PROVIDERS as $provider) {
                self::assertStringContainsString('href="' . $provider . '"', $credit);
            }

            // The poster source returns no Mediux artwork, so crediting it would
            // name a provider Marquee does not actually use.
            self::assertStringNotContainsString('mediux', $credit);

            // Every logo is served by Marquee itself, so rendering a footer never
            // reaches a third-party host. asset() appends a ?v= cache-buster,
            // hence the prefix check rather than an equality one.
            preg_match_all('#<img[^>]*\bsrc="([^"]*)"#s', $credit, $logos);

            self::assertCount(
                \count(self::PROVIDERS),
                $logos[1],
                $open . ' must show one logo per credited provider.',
            );

            foreach ($logos[1] as $src) {
                self::assertStringStartsWith('/assets/providers/', $src);
            }
        }
    }

    public function testProviderLogoAssetsExist(): void
    {
        // The templates reference these by path, so a missing file is a broken
        // image in production but nothing a rendering assertion would catch.
        foreach (['tmdb.svg', 'tvdb.png', 'fanart.png'] as $logo) {
            self::assertFileExists(\dirname(__DIR__, 2) . '/public/assets/providers/' . $logo);
        }
    }

    public function testHeaderBrandMarkDrawsTheSameShapesAsTheLogoAsset(): void
    {
        // The header repeats logo.svg inline so it can be styled and animated,
        // which means the two can silently drift: edit the asset, forget the
        // template, and the tab icon stops matching the header. Compare the
        // shape geometry of both so that drift fails here instead of shipping.
        $body = (string) $this->get(
            $this->makeConnectedApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody();

        preg_match('#<svg class="brand__logo".*?</svg>#s', $body, $m);
        $inline = $m[0] ?? '';
        self::assertNotSame('', $inline, 'The header must carry an inline brand mark.');

        $asset = (string) file_get_contents(\dirname(__DIR__, 2) . '/public/assets/logo.svg');

        self::assertSame(
            self::shapeGeometry($asset),
            self::shapeGeometry($inline),
            'The header brand mark and public/assets/logo.svg must draw the same '
            . 'shapes. Update both, or the header and the favicon will disagree.',
        );
    }

    /**
     * Every shape-defining attribute of an SVG's <rect>/<path> elements, in
     * document order. Ignores the wrapper element, so the asset's <svg> root and
     * the template's class-carrying copy compare equal.
     *
     * @return list<string>
     */
    private static function shapeGeometry(string $svg): array
    {
        preg_match_all('#<(rect|path)\b([^>]*)>#s', $svg, $elements, PREG_SET_ORDER);

        $shapes = [];

        foreach ($elements as $element) {
            preg_match_all(
                '#\b(d|x|y|width|height|rx|ry|fill|transform)="([^"]*)"#s',
                $element[2],
                $attributes,
                PREG_SET_ORDER,
            );

            $shape = [];

            foreach ($attributes as $attribute) {
                // Collapse whitespace so indentation or a wrapped attribute in
                // the template is not mistaken for a geometry change.
                $shape[$attribute[1]] = (string) preg_replace('/\s+/', ' ', trim($attribute[2]));
            }

            ksort($shape);

            $parts = [];

            foreach ($shape as $name => $value) {
                $parts[] = $name . '=' . $value;
            }

            $shapes[] = $element[1] . '[' . implode(',', $parts) . ']';
        }

        return $shapes;
    }
}
