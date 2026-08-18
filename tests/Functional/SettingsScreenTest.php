<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function App\buildContainer;

use App\Config\AuthConfig;
use App\Config\AutoImportConfig;
use App\Config\LibraryExclusions;
use App\Config\PlexConfig;
use App\Config\PosterConfig;

use function App\createApp;

use App\Plex\Import\AutoImportInterval;
use App\Plex\PlexClient;
use App\Plex\PlexLibrary;
use App\Plex\PlexMediaType;
use App\Poster\SortOrder;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;
use App\Support\Session\ArraySession;
use App\Support\Session\SessionInterface;
use App\Tests\AppTestCase;
use App\Tests\Support\FakePlexClient;
use Slim\App;

/**
 * The settings screen: what it offers, what it refuses, and what the next
 * request sees.
 *
 * "The next request" is taken literally throughout — a save is asserted by
 * resolving the configuration objects from the store on disk, which is exactly
 * what bootstrap does on the following request. Asserting through the container
 * that handled the save would prove only that the form remembered its own input.
 */
final class SettingsScreenTest extends AppTestCase
{
    private string $dataDir = '';

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/marquee-settings-screen-' . bin2hex(random_bytes(6));
        mkdir($this->dataDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dataDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dataDir);
    }

    /**
     * @param array<string, string> $env
     * @param list<PlexLibrary>     $libraries
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function screen(array $env = [], array $libraries = [], bool $plexFails = false): App
    {
        $fake = new FakePlexClient($libraries, failLibraries: $plexFails);

        return $this->makeSignedInApp(
            array_merge(['DATA_DIR' => $this->dataDir], $env),
            [PlexClient::class => static fn (): PlexClient => $fake],
        );
    }

    /**
     * The store as the next request would read it.
     */
    private function stored(): SettingsStore
    {
        return new SettingsStore($this->dataDir);
    }

    /**
     * A second app over the state the first one left behind.
     *
     * Deliberately not makeApp(): that deletes the settings file and re-seeds it
     * from the environment, which is right for isolating tests and wrong here —
     * it would erase the very save being asserted. This builds a container the
     * way a subsequent request does, over the store already on disk.
     *
     * The fake client is handed the exclusions the store now holds, because it
     * is constructed outside the container and so cannot be injected with the
     * `LibraryExclusions` the real client resolves. That the real client applies
     * them is {@see \App\Tests\Unit\Plex\HttpPlexClientTest}'s job; what this
     * asserts is that a save reaches the screens.
     *
     * @param list<PlexLibrary> $libraries
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function nextRequest(array $libraries = []): App
    {
        $fake = new FakePlexClient(
            $libraries,
            excluded: $this->stored()->list(SettingKey::ExcludedLibraries),
        );
        $app = createApp(buildContainer([
            PlexClient::class => static fn (): PlexClient => $fake,
            SessionInterface::class => static fn (): SessionInterface => new ArraySession(),
        ]));
        $this->signIn($app);

        return $app;
    }

    /**
     * @param array<string, string|list<string>> $overrides
     *
     * @return array<string, string|list<string>>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'site_title' => 'Marquee',
            'images_per_page' => '24',
            'default_sort' => 'alphabetical',
            'max_file_size' => '5',
            'plex_server_url' => 'http://plex:32400',
            'plex_connect_timeout' => '10',
            'plex_request_timeout' => '60',
            'session_duration' => '30',
            'auto_import_interval' => '24h',
        ], $overrides);
    }

    public function testScreenNeedsASession(): void
    {
        $app = $this->makeConnectedApp(['DATA_DIR' => $this->dataDir]);

        $response = $this->get($app, '/settings');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testScreenIsBehindTheConnectionGate(): void
    {
        $app = $this->makeApp(['DATA_DIR' => $this->dataDir]);
        $this->signIn($app);

        $response = $this->get($app, '/settings');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/connect', $response->getHeaderLine('Location'));
    }

    public function testScreenIsOfferedInTheNavigation(): void
    {
        // Another page, not the settings screen itself: the header suppresses the
        // link to the page being viewed, so asserting there would prove nothing.
        $body = (string) $this->get($this->screen(), '/plex')->getBody();

        self::assertStringContainsString('href="/settings"', $body);
        self::assertStringContainsString('Settings', $body);
    }

    public function testPresentationSettingsTakeEffectOnTheNextRequest(): void
    {
        $app = $this->screen();

        $response = $this->postForm($app, '/settings', $this->form([
            'site_title' => 'Home Cinema',
            'images_per_page' => '48',
            'default_sort' => 'date_added',
            'max_file_size' => '12',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/settings', $response->getHeaderLine('Location'));

        $store = $this->stored();
        $poster = PosterConfig::resolve($store);

        self::assertSame('Home Cinema', $store->string(SettingKey::SiteTitle));
        self::assertSame(48, $poster->perPage);
        self::assertSame(SortOrder::DateAdded, $poster->defaultSort);
        self::assertSame(12 * 1048576, $poster->maxFileSize);
        // Absent from the submission, therefore off.
        self::assertFalse($poster->ignoreArticlesInSort);
    }

    public function testPlexAndSessionSettingsTakeEffectOnTheNextRequest(): void
    {
        $app = $this->screen();

        $this->postForm($app, '/settings', $this->form([
            'plex_connect_timeout' => '25',
            'plex_request_timeout' => '120',
            'remove_overlay_label' => 'on',
            'session_duration' => '7',
            'update_check' => 'on',
        ]));

        $store = $this->stored();

        self::assertSame(25, $store->int(SettingKey::PlexConnectTimeout));
        self::assertSame(120, $store->int(SettingKey::PlexRequestTimeout));
        self::assertTrue($store->bool(SettingKey::PlexRemoveOverlayLabel));
        self::assertSame(7 * 86400, AuthConfig::resolve($store)->sessionDuration);
        self::assertTrue($store->bool(SettingKey::UpdateCheckEnabled));
    }

    /**
     * The invariant the floors-as-constants arrangement exists for: what the
     * screen accepts, bootstrap leaves alone.
     */
    public function testNothingTheScreenAcceptsIsCorrectedAtBootstrap(): void
    {
        $app = $this->screen();

        $this->postForm($app, '/settings', $this->form([
            'plex_connect_timeout' => (string) PlexConfig::MINIMUM_TIMEOUT,
            'session_duration' => '1',
            'images_per_page' => (string) PosterConfig::MINIMUM_PER_PAGE,
        ]));

        $store = $this->stored();

        self::assertSame(PlexConfig::MINIMUM_TIMEOUT, $store->int(SettingKey::PlexConnectTimeout));
        self::assertSame(86400, AuthConfig::resolve($store)->sessionDuration);
        self::assertSame(PosterConfig::MINIMUM_PER_PAGE, PosterConfig::resolve($store)->perPage);
    }

    public function testARefusedFieldStoresNothingAndKeepsWhatWasTyped(): void
    {
        $app = $this->screen();

        $response = $this->postForm($app, '/settings', $this->form([
            'site_title' => 'Home Cinema',
            'plex_connect_timeout' => '0',
        ]));
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        // The whole submission is refused, including the field that was fine.
        self::assertSame('Marquee', $this->stored()->string(SettingKey::SiteTitle));
        // …and re-rendered carrying it, so the work is not lost.
        self::assertStringContainsString('value="Home Cinema"', $body);
        self::assertStringContainsString('Enter a whole number between 1 and 300.', $body);
    }

    public function testAnUnrecognisedSortIsRefused(): void
    {
        $app = $this->screen();

        $response = $this->postForm($app, '/settings', $this->form(['default_sort' => 'by_vibes']));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Choose one of the sort orders offered.', (string) $response->getBody());
        self::assertSame('', $this->stored()->string(SettingKey::DefaultSort));
    }

    /**
     * A value seeded before this screen existed renders adjusted into range, and
     * — the point of the rule — does not stop the rest of the form saving.
     */
    public function testASeededValueOutsideTheOfferedRangeDoesNotBlockTheForm(): void
    {
        $app = $this->screen(['IMAGES_PER_PAGE' => '5000']);

        $body = (string) $this->get($app, '/settings')->getBody();
        self::assertStringContainsString('value="200"', $body);

        $this->postForm($app, '/settings', $this->form([
            'images_per_page' => '200',
            'site_title' => 'Home Cinema',
        ]));

        self::assertSame('Home Cinema', $this->stored()->string(SettingKey::SiteTitle));
    }

    public function testSessionDurationRoundTripsThroughDays(): void
    {
        // Thirty days in seconds, as an install seeded before this screen
        // existed holds it.
        $app = $this->screen(['SESSION_DURATION' => '2592000']);

        self::assertStringContainsString('value="30"', (string) $this->get($app, '/settings')->getBody());

        $this->postForm($app, '/settings', $this->form());

        self::assertSame(2592000, $this->stored()->int(SettingKey::SessionDuration));
    }

    public function testLibrariesAreListedIncludingExcludedOnes(): void
    {
        $app = $this->screen(
            ['EXCLUDED_LIBRARIES' => 'Kids'],
            [new PlexLibrary('1', 'Movies', 'movie'), new PlexLibrary('2', 'Kids', 'movie')],
        );

        $body = (string) $this->get($app, '/settings')->getBody();

        // Excluded or not, both are on offer — the screen that undoes an
        // exclusion is the one place that must see past it.
        self::assertStringContainsString('value="Movies"', $body);
        self::assertStringContainsString('value="Kids"', $body);
        self::assertMatchesRegularExpression('/value="Kids"[^>]*checked/', $body);
    }

    public function testExcludingALibraryHidesItFromTheImportScreen(): void
    {
        $app = $this->screen([], [new PlexLibrary('1', 'Movies', 'movie'), new PlexLibrary('2', 'Kids', 'movie')]);

        self::assertStringContainsString('Kids', (string) $this->get($app, '/plex')->getBody());

        $this->postForm($app, '/settings', $this->form(['excluded' => ['Kids']]));

        // A second request, because exclusions resolve once at bootstrap.
        $next = $this->nextRequest([new PlexLibrary('1', 'Movies', 'movie'), new PlexLibrary('2', 'Kids', 'movie')]);
        $body = (string) $this->get($next, '/plex')->getBody();

        self::assertStringContainsString('Movies', $body);
        self::assertStringNotContainsString('Kids', $body);
        self::assertSame(['Kids'], LibraryExclusions::resolve($this->stored())->names);
    }

    public function testUnticikingALibraryUnexcludesIt(): void
    {
        $app = $this->screen(
            ['EXCLUDED_LIBRARIES' => 'Kids'],
            [new PlexLibrary('1', 'Movies', 'movie'), new PlexLibrary('2', 'Kids', 'movie')],
        );

        $this->postForm($app, '/settings', $this->form());

        self::assertSame([], LibraryExclusions::resolve($this->stored())->names);
    }

    /**
     * The failure the merge exists to prevent: a library the server no longer
     * reports must not un-hide itself because someone changed the site title.
     */
    public function testAnExclusionForAnUnreportedLibrarySurvivesASave(): void
    {
        $app = $this->screen(
            ['EXCLUDED_LIBRARIES' => 'Gone'],
            [new PlexLibrary('1', 'Movies', 'movie')],
        );

        $body = (string) $this->get($app, '/settings')->getBody();
        self::assertStringContainsString('Gone', $body);

        $this->postForm($app, '/settings', $this->form(['site_title' => 'Home Cinema']));

        self::assertSame(['Gone'], LibraryExclusions::resolve($this->stored())->names);
        self::assertSame('Home Cinema', $this->stored()->string(SettingKey::SiteTitle));
    }

    public function testAStaleExclusionCanBeClearedDeliberately(): void
    {
        $app = $this->screen(
            ['EXCLUDED_LIBRARIES' => 'Gone'],
            [new PlexLibrary('1', 'Movies', 'movie')],
        );

        $this->postForm($app, '/settings', $this->form(['clear_unreported' => 'on']));

        self::assertSame([], LibraryExclusions::resolve($this->stored())->names);
    }

    public function testScreenRendersWhenPlexCannotBeReached(): void
    {
        $app = $this->screen(['EXCLUDED_LIBRARIES' => 'Kids'], [], plexFails: true);

        $response = $this->get($app, '/settings');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        // The failure is explained where the libraries would have been, and the
        // exclusions in force are still named.
        self::assertStringContainsString('Kids', $body);
        self::assertStringContainsString('Plex', $body);
        // Every other section is still there to save.
        self::assertStringContainsString('name="site_title"', $body);
    }

    public function testSavingWithPlexUnreachableLeavesExclusionsAlone(): void
    {
        $app = $this->screen(['EXCLUDED_LIBRARIES' => 'Kids'], [], plexFails: true);

        $this->postForm($app, '/settings', $this->form(['site_title' => 'Home Cinema']));

        self::assertSame('Home Cinema', $this->stored()->string(SettingKey::SiteTitle));
        self::assertSame(['Kids'], LibraryExclusions::resolve($this->stored())->names);
    }

    public function testASubmissionWithoutATokenIsRefused(): void
    {
        $app = $this->screen();

        $response = $this->postFormWithoutToken($app, '/settings', $this->form(['site_title' => 'Home Cinema']));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Marquee', $this->stored()->string(SettingKey::SiteTitle));
    }

    /**
     * Relocated variables are named here, where they are now managed. Retired
     * ones are not: what replaced a password is a different sentence, and it
     * belongs on the screen that replaced it.
     */
    public function testTheScreenNamesSettingsStillSetInTheComposeFile(): void
    {
        $app = $this->screen();
        $this->supersede(['SITE_TITLE' => 'Home Cinema', 'AUTH_PASSWORD' => 'hunter2']);

        $body = (string) $this->get($app, '/settings')->getBody();

        self::assertStringContainsString('SITE_TITLE', $body);
        self::assertStringContainsString('still in your compose file', $body);
        self::assertStringNotContainsString('AUTH_PASSWORD', $body);
        self::assertStringNotContainsString('hunter2', $body);
    }

    public function testTheScreenIsQuietWhenTheComposeFileIsClean(): void
    {
        $body = (string) $this->get($this->screen(), '/settings')->getBody();

        self::assertStringNotContainsString('still in your compose file', $body);
    }

    /**
     * The Plex server address is deliberately absent. It is the assertion that
     * only someone with host access can make, and moving it into the browser
     * without replacing that property would let the first stranger to reach an
     * unconfigured install claim it.
     */
    /**
     * Withheld until phase 4, because it was the assertion only someone with
     * host access could make. The claim code carries that now, so the address is
     * an ordinary setting.
     */
    public function testTheScreenOffersTheServerAddress(): void
    {
        $body = (string) $this->get($this->screen(), '/settings')->getBody();

        self::assertStringContainsString('name="plex_server_url"', $body);
    }

    public function testTheServerAddressCanBeChanged(): void
    {
        $app = $this->screen();

        $this->postForm($app, '/settings', $this->form(['plex_server_url' => 'http://10.0.0.5:32400']));

        self::assertSame('http://10.0.0.5:32400', $this->stored()->string(SettingKey::PlexServerUrl));
    }

    public function testAnUnusableServerAddressIsRefused(): void
    {
        $app = $this->screen();

        $response = $this->postForm($app, '/settings', $this->form(['plex_server_url' => 'http://plex:324000']));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('not a usable address', (string) $response->getBody());
    }

    /**
     * Auto-import was withheld while its schedule was fixed into the container
     * at boot, because the control would not have worked. It works now.
     */
    public function testTheScreenOffersAutoImport(): void
    {
        $body = (string) $this->get($this->screen(), '/settings')->getBody();

        self::assertStringContainsString('name="auto_import"', $body);
        self::assertStringContainsString('name="auto_import_interval"', $body);
        self::assertStringContainsString('name="auto_import_movies"', $body);
        // Says when it applies, which is not "your next page load".
        self::assertStringContainsString('next scheduled run', $body);
    }

    public function testAutoImportSettingsAreStored(): void
    {
        $app = $this->screen();

        $this->postForm($app, '/settings', $this->form([
            'auto_import' => 'on',
            'auto_import_interval' => '6h',
            'auto_import_movies' => 'on',
            'auto_import_shows' => 'on',
        ]));

        $config = AutoImportConfig::resolve($this->stored());

        self::assertTrue($config->enabled);
        self::assertSame(AutoImportInterval::EverySixHours, $config->interval);
        self::assertSame(
            [PlexMediaType::Movie, PlexMediaType::Show],
            $config->mediaTypes(),
        );
    }

    public function testAnUnrecognisedIntervalIsRefused(): void
    {
        $app = $this->screen();

        $response = $this->postForm($app, '/settings', $this->form(['auto_import_interval' => 'fortnightly']));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Choose one of the schedules offered.', (string) $response->getBody());
        self::assertFalse(AutoImportConfig::resolve($this->stored())->enabled);
    }
}
