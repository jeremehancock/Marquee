<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AppTestCase;
use App\Tests\Support\MakesImages;
use Slim\App;

final class PosterWallTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
        $this->writePng($this->postersDir . '/movies/Solaris.png');
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
        return $this->makeApp(['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir]);
    }

    public function testWallPageRenders(): void
    {
        $response = $this->get($this->app(), '/wall');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('wall__layer', (string) $response->getBody());
    }

    public function testPostersEndpointReturnsJson(): void
    {
        $response = $this->get($this->app(), '/wall/posters');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('/posters/movies/Solaris.png', (string) $response->getBody());
    }

    public function testWallRequiresAuthentication(): void
    {
        $response = $this->get($this->makeApp(['POSTERS_DIR' => $this->postersDir]), '/wall');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }
}
