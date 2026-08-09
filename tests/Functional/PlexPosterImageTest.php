<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Plex\PlexClient;
use App\Plex\SignedImagePath;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use Slim\App;

/**
 * The proxy that lets the change dialog show Plex-held poster candidates
 * without a Plex URL — and so without the Plex token — ever reaching the page.
 */
final class PlexPosterImageTest extends AppTestCase
{
    /**
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    private function signer(App $app): SignedImagePath
    {
        $container = $app->getContainer();
        self::assertNotNull($container);
        /** @var SignedImagePath $signer */
        $signer = $container->get(SignedImagePath::class);

        return $signer;
    }

    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function app(): App
    {
        return $this->makeSignedInApp([], [
            PlexClient::class => static fn (): PlexClient => new FakePlexClient(),
        ]);
    }

    public function testServesASignedCandidateImage(): void
    {
        $app = $this->app();
        $token = $this->signer($app)->sign('/library/metadata/10/thumb/171');

        $response = $this->get($app, '/plex-poster-image/' . $token);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/jpeg', $response->getHeaderLine('Content-Type'));
        self::assertNotSame('', (string) $response->getBody());
    }

    public function testRefusesATokenItDidNotSign(): void
    {
        $forged = (new SignedImagePath('not-the-real-secret'))->sign('/library/metadata/10/thumb/171');

        self::assertSame(404, $this->get($this->app(), '/plex-poster-image/' . $forged)->getStatusCode());
    }

    public function testRefusesARawPathInPlaceOfAToken(): void
    {
        self::assertSame(404, $this->get($this->app(), '/plex-poster-image/not-a-token')->getStatusCode());
    }

    /**
     * The wall's equivalent is public because an unattended display has no
     * session. This one is not: it serves a dialog behind the login.
     */
    public function testRequiresASession(): void
    {
        $app = $this->makeConnectedApp([], [
            PlexClient::class => static fn (): PlexClient => new FakePlexClient(),
        ]);
        $token = $this->signer($app)->sign('/library/metadata/10/thumb/171');

        $response = $this->get($app, '/plex-poster-image/' . $token);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringStartsWith('/login', $response->getHeaderLine('Location'));
    }

    /**
     * A candidate token is opaque, so a page carrying one discloses neither the
     * Plex path it stands for nor the server it would be fetched from.
     */
    public function testTokenCarriesNoPlexPath(): void
    {
        $token = $this->signer($this->app())->sign('/library/metadata/10/thumb/171');

        self::assertStringNotContainsString('/library/', $token);
        self::assertStringNotContainsString('thumb', $token);
    }
}
