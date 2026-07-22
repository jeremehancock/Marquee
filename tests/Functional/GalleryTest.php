<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AppTestCase;
use App\Tests\Support\MakesImages;
use Slim\App;

final class GalleryTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
    }

    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function app(): App
    {
        return $this->makeApp(['POSTERS_DIR' => $this->postersDir, 'AUTH_BYPASS' => 'true']);
    }

    private function writePoster(string $filename): void
    {
        $this->writePng($this->postersDir . '/movies/' . $filename);
    }

    public function testHomeRedirectsToMovies(): void
    {
        $response = $this->get($this->app(), '/');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/library/movies', $response->getHeaderLine('Location'));
    }

    public function testGalleryListsPosters(): void
    {
        $this->writePoster('Solaris.png');

        $response = $this->get($this->app(), '/library/movies');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Solaris', $body);
        self::assertStringContainsString('Movies', $body);
    }

    public function testUnknownCategoryReturns404(): void
    {
        self::assertSame(404, $this->get($this->app(), '/library/books')->getStatusCode());
    }

    public function testGalleryRendersOverlayActionsAndCaption(): void
    {
        $this->writePoster('Solaris.png');

        $body = (string) $this->get($this->app(), '/library/movies')->getBody();

        self::assertStringContainsString('id="results"', $body);
        self::assertStringContainsString('card__caption', $body);
        self::assertStringContainsString('data-action="change"', $body);
        self::assertStringContainsString('data-action="view"', $body);
        // The title moved to a caption; the old overlay title class is gone.
        self::assertStringNotContainsString('card__title', $body);
        // Poster Wall opens in a new tab.
        self::assertStringContainsString('target="_blank"', $body);
    }

    public function testRemembersSectionForOrphansBackLink(): void
    {
        // One app instance so the in-memory session persists across requests.
        $app = $this->makeApp(['POSTERS_DIR' => $this->postersDir, 'AUTH_BYPASS' => 'true']);

        $this->get($app, '/library/tv-shows');
        $body = (string) $this->get($app, '/orphans')->getBody();

        self::assertStringContainsString('href="/library/tv-shows"', $body);
    }

    public function testImageIsServed(): void
    {
        $this->writePoster('Solaris.png');

        $response = $this->get($this->app(), '/posters/movies/Solaris.png');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertNotSame('', (string) $response->getBody());
    }

    public function testImageTraversalReturns404(): void
    {
        self::assertSame(404, $this->get($this->app(), '/posters/movies/..')->getStatusCode());
    }

    public function testMissingImageReturns404(): void
    {
        self::assertSame(404, $this->get($this->app(), '/posters/movies/nope.png')->getStatusCode());
    }

    public function testDeleteRemovesPoster(): void
    {
        $this->writePoster('Gone.png');

        $response = $this->postForm($this->app(), '/library/movies/delete', ['filename' => 'Gone.png']);

        self::assertSame(302, $response->getStatusCode());
        self::assertFileDoesNotExist($this->postersDir . '/movies/Gone.png');
    }
}
