<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AppTestCase;

final class ApplicationShellTest extends AppTestCase
{
    public function testHealthReturnsOkWithoutAuthentication(): void
    {
        $response = $this->get($this->makeApp(), '/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('"status":"ok"', (string) $response->getBody());
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $response = $this->get($this->makeApp(['AUTH_BYPASS' => 'true']), '/does-not-exist');

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
        $response = $this->get($this->makeApp(['AUTH_BYPASS' => 'true', 'SITE_TITLE' => 'My Wall']), '/library/movies');

        self::assertSame(200, $response->getStatusCode());
        // Assert the brand link specifically: a bare substring check would also
        // pass on the tab <title>, and so could not tell the two apart. The
        // brand carries the logo mark plus the title in a <span>.
        self::assertStringContainsString(
            '<span>My Wall</span>',
            (string) $response->getBody(),
        );
    }

    public function testBothFootersLinkTheProductNameToTheProjectSite(): void
    {
        $body = (string) $this->get(
            $this->makeApp(['AUTH_BYPASS' => 'true']),
            '/library/movies',
        )->getBody();

        // The page footer and the mobile tray's footer show the same credit
        // line, so both link the product name — and both open a new tab, which
        // requires rel="noopener".
        foreach (['<footer class="footer">', '<div class="menu__footer">'] as $open) {
            self::assertMatchesRegularExpression(
                '#' . preg_quote($open, '#') . '<a ([^>]*)>Marquee</a>#s',
                $body,
                $open . ' must wrap the product name in a link.',
            );

            // Attributes may wrap across lines, but no single attribute does,
            // so the raw capture can be searched as-is.
            preg_match('#' . preg_quote($open, '#') . '<a ([^>]*)>#s', $body, $m);
            $attributes = $m[1] ?? '';

            self::assertStringContainsString('href="https://getmarquee.now"', $attributes);
            self::assertStringContainsString('target="_blank"', $attributes);
            self::assertStringContainsString('rel="noopener"', $attributes);
        }
    }

    public function testHeaderBrandMarkDrawsTheSameShapesAsTheLogoAsset(): void
    {
        // The header repeats logo.svg inline so it can be styled and animated,
        // which means the two can silently drift: edit the asset, forget the
        // template, and the tab icon stops matching the header. Compare the
        // shape geometry of both so that drift fails here instead of shipping.
        $body = (string) $this->get(
            $this->makeApp(['AUTH_BYPASS' => 'true']),
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
