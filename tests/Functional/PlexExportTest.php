<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\Database;
use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Plex\PlexPosterWriter;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexPosterWriter;
use App\Tests\Support\MakesImages;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PlexExportTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        mkdir($this->postersDir . '/movies');
        $this->dataDir = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
        $this->removeDir($this->dataDir);
    }

    private function link(string $filename, string $ratingKey = '10'): void
    {
        $this->writePng($this->postersDir . '/movies/' . $filename);
        $repo = new PlexItemRepository(new Database($this->dataDir . '/marquee.sqlite'));
        $repo->upsert(new PlexItemRecord($ratingKey, 'movie', 'movies', 'Movies', 'Solaris', $filename, time(), '5'));
    }

    public function testSendToPlexUploadsAndLocks(): void
    {
        $this->link('Solaris.jpg');
        $writer = new FakePlexPosterWriter();

        $app = $this->makeApp(
            ['AUTH_BYPASS' => 'true', 'POSTERS_DIR' => $this->postersDir, 'DATA_DIR' => $this->dataDir],
            [PlexPosterWriter::class => static fn (): PlexPosterWriter => $writer],
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/library/movies/send-to-plex')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query(['filename' => 'Solaris.jpg']));
        $request->getBody()->rewind();

        $response = $app->handle($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/library/movies', $response->getHeaderLine('Location'));
        self::assertSame(['10'], $writer->uploaded);
        self::assertSame(['10'], $writer->locked);
    }

    public function testGalleryShowsSendButtonOnlyForLinkedPosters(): void
    {
        $this->link('Solaris.jpg');
        $this->writePng($this->postersDir . '/movies/Manual.jpg');

        $app = $this->makeApp([
            'AUTH_BYPASS' => 'true',
            'POSTERS_DIR' => $this->postersDir,
            'DATA_DIR' => $this->dataDir,
            'PLEX_SERVER_URL' => 'http://plex:32400',
            'PLEX_TOKEN' => 'token',
        ]);

        $body = (string) $this->get($app, '/library/movies')->getBody();

        self::assertStringContainsString('Send to Plex', $body);
        self::assertSame(1, substr_count($body, '/library/movies/send-to-plex'));
    }
}
