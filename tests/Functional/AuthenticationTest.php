<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function App\buildContainer;

use App\Config\PlexConfig;
use App\Plex\Connection\PlexConnectionStore;
use App\Plex\Import\AutoImportService;
use App\Plex\PlexClient;
use App\Plex\PlexItem;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;

final class AuthenticationTest extends AppTestCase
{
    public function testAnEstablishedSessionReachesTheApplication(): void
    {
        $response = $this->get($this->makeSignedInApp(), '/library/movies');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAnUnauthenticatedVisitorIsSentToSignIn(): void
    {
        $response = $this->get($this->makeConnectedApp(), '/library/movies');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    /**
     * There is no credential to submit. Nothing that looks like one may
     * authenticate, by any route.
     */
    public function testNoCredentialAuthenticates(): void
    {
        $app = $this->makeConnectedApp();

        $response = $this->postForm($app, '/login', ['username' => 'admin', 'password' => 'changeme']);

        // No such route at all — the screen offers one action, and it is not this.
        self::assertSame(405, $response->getStatusCode());
        self::assertSame(302, $this->get($app, '/')->getStatusCode());
    }

    public function testLogoutEndsTheSession(): void
    {
        $app = $this->makeSignedInApp();
        self::assertSame(200, $this->get($app, '/library/movies')->getStatusCode());

        $logout = $this->get($app, '/logout');

        self::assertSame(302, $logout->getStatusCode());
        self::assertSame('/login', $logout->getHeaderLine('Location'));
        self::assertSame(302, $this->get($app, '/')->getStatusCode());
    }

    /**
     * The trap this change had to avoid. The scheduled auto-import runs as a
     * separate process with no session and authenticates with the stored token,
     * so collapsing logout into disconnecting would stop scheduled imports at
     * the next run — silently, with nothing in the interface to report it.
     */
    public function testLoggingOutLeavesPlexConnected(): void
    {
        $dataDir = $this->makeTempDir();
        $app = $this->makeSignedInApp(['DATA_DIR' => $dataDir]);

        $this->get($app, '/logout');

        self::assertSame('test-plex-token', (new PlexConnectionStore($dataDir))->token());
        $this->removeDir($dataDir);
    }

    /**
     * The guarantee the previous test protects, proved the way the failure would
     * actually happen: the scheduled import is a separate process that builds
     * its own container and reads the token off disk, with no session anywhere.
     *
     * A logout that cleared the token would not fail here at logout time. It
     * would fail at the next cron tick, silently, which is why this asserts the
     * import itself rather than only the stored value.
     */
    public function testAScheduledImportStillRunsAfterLogout(): void
    {
        $dataDir = $this->makeTempDir();
        $postersDir = $this->makeTempDir();

        $app = $this->makeSignedInApp(['DATA_DIR' => $dataDir, 'POSTERS_DIR' => $postersDir]);
        $this->get($app, '/logout');

        // Auto-import is configured through the settings store, not the
        // environment: the store was seeded when the app above was built, and
        // seeding happens once, so a variable set now would never be read.
        // Writing it here is what the settings screen will do.
        $settings = new SettingsStore($dataDir);
        $settings->set(SettingKey::AutoImportEnabled, true);
        $settings->set(SettingKey::AutoImportMovies, true);

        // A fresh container over the same data directory, as bin/auto-import.php
        // builds. Nothing carries over from the web request but the disk.
        $container = buildContainer([
            PlexClient::class => static fn (): PlexClient => new FakePlexClient(
                [new PlexLibrary('1', 'Movies', 'movie')],
                ['1' => [new PlexItem('10', PlexMediaType::Movie, 'Solaris', 1972, '/t/10', 'Movies')]],
            ),
        ]);

        // The credential that process would authenticate with, resolved the way
        // it resolves it — from the store, at bootstrap. Asserted separately
        // because the fake client below answers regardless of configuration, so
        // the import alone would not notice a token that had gone.
        /** @var PlexConfig $plex */
        $plex = $container->get(PlexConfig::class);
        self::assertTrue($plex->isConfigured());
        self::assertSame('test-plex-token', $plex->token);

        /** @var AutoImportService $autoImport */
        $autoImport = $container->get(AutoImportService::class);
        $result = $autoImport->run();

        // Not null: null is what "Plex is not configured" returns, which is
        // exactly the failure a cleared token would produce.
        self::assertNotNull($result);
        self::assertSame(1, $result->imported());

        $this->removeDir($dataDir);
        $this->removeDir($postersDir);
    }

    /**
     * plex.tv is consulted at sign-in and never again: the server address is
     * configured, and every operation afterwards goes to that server directly.
     */
    public function testAnEstablishedSessionDoesNotDependOnPlexTv(): void
    {
        // Nothing here can reach plex.tv — there is no outbound call left to make.
        $response = $this->get($this->makeSignedInApp(), '/library/movies');

        self::assertSame(200, $response->getStatusCode());
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/marquee-auth-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o775, true);

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach ((array) scandir($dir) as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
