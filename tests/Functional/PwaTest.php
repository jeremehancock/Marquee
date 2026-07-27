<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AppTestCase;

final class PwaTest extends AppTestCase
{
    public function testManifestIsPublicAndNamedAfterProductNotSiteTitle(): void
    {
        // No AUTH_BYPASS: the manifest must be reachable without a session.
        $response = $this->get($this->makeApp(['SITE_TITLE' => 'My Wall']), '/manifest.webmanifest');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/manifest+json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        // The install name is captured on the home screen once and never
        // re-read, so it must not follow a per-install setting.
        self::assertStringContainsString('"name":"Marquee"', $body);
        self::assertStringContainsString('"short_name":"Marquee"', $body);
        self::assertStringNotContainsString('My Wall', $body);
        self::assertStringContainsString('/assets/icons/icon-512.png', $body);
    }

    public function testManifestDeclaresDistinctAnyAndMaskableIcons(): void
    {
        $response = $this->get($this->makeApp(['AUTH_BYPASS' => 'true']), '/manifest.webmanifest');

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('icons', $data);
        $icons = $data['icons'];
        self::assertIsArray($icons);

        $purposes = array_column($icons, 'purpose');
        self::assertContains('any', $purposes, 'Manifest must declare at least one "any" icon.');
        self::assertContains('maskable', $purposes, 'Manifest must declare at least one "maskable" icon.');

        // No single entry may carry both purposes: a maskable icon is padded
        // into a safe zone and looks shrunken when reused as "any".
        foreach ($icons as $icon) {
            self::assertIsArray($icon);
            self::assertNotSame('any maskable', $icon['purpose'] ?? '');
        }

        // The maskable art is a distinct asset, not the edge-tight "any" tile.
        $sources = array_column($icons, 'src');
        self::assertContains('/assets/icons/icon-512-maskable.png', $sources);
    }

    public function testVersionEndpointReportsCurrentVersion(): void
    {
        $response = $this->get($this->makeApp(['AUTH_BYPASS' => 'true']), '/version');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('version', $data);
        self::assertNotSame('', $data['version']);
        self::assertFalse($data['updateAvailable']);
    }

    public function testFooterShowsVersion(): void
    {
        $body = (string) $this->get($this->makeApp(['AUTH_BYPASS' => 'true']), '/library/movies')->getBody();

        self::assertMatchesRegularExpression('/v\d+\.\d+\.\d+/', $body);
    }

    public function testFooterAndHomeScreenLabelNameTheProductNotTheSiteTitle(): void
    {
        $body = (string) $this->get(
            $this->makeApp(['AUTH_BYPASS' => 'true', 'SITE_TITLE' => 'My Wall']),
            '/library/movies',
        )->getBody();

        self::assertMatchesRegularExpression(
            '#<footer class="footer">Marquee &middot; v\d+\.\d+\.\d+#',
            $body,
        );
        self::assertStringContainsString(
            '<meta name="apple-mobile-web-app-title" content="Marquee">',
            $body,
        );
    }
}
