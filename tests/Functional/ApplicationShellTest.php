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
        // pass on the tab title, and so could not tell the two apart.
        self::assertStringContainsString(
            '<a class="brand" href="/">My Wall</a>',
            (string) $response->getBody(),
        );
    }
}
