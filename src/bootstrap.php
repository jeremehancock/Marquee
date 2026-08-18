<?php

declare(strict_types=1);

namespace App;

use App\Auth\AuthMiddleware;
use App\Auth\CsrfGuard;
use App\Auth\CsrfMiddleware;
use App\Auth\PlexConnectionMiddleware;
use App\Auth\SessionAuthenticator;
use App\Config\AppConfig;
use App\Config\AuthConfig;
use App\Config\AutoImportConfig;
use App\Config\LibraryExclusions;
use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Controller\PosterWallController;
use App\Database\Database;
use App\Plex\Connection\PlexConnectionState;
use App\Plex\Connection\PlexConnectionStatus;
use App\Plex\Connection\PlexConnectionStore;
use App\Plex\Connection\PlexPinClient;
use App\Plex\HttpPlexClient;
use App\Plex\PlexClient;
use App\Plex\PlexPosterWriter;
use App\Plex\SignedImagePath;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterStorage;
use App\Poster\Source\PosteriaApiPosterSource;
use App\Poster\Source\PosterSource;
use App\Poster\Wall\NowPlayingService;
use App\Poster\Wall\PosterWallService;
use App\Poster\Wall\StreamToken;
use App\Settings\SettingKey;
use App\Settings\SettingsSeeder;
use App\Settings\SettingsStore;
use App\Settings\SupersededEnvironment;
use App\Support\Env;
use App\Support\Session\NativeSession;
use App\Support\Session\SessionInterface;
use App\Version\GitHubLatestReleaseProvider;
use App\Version\LatestReleaseProvider;
use App\Version\VersionService;
use DI\Container;
use DI\ContainerBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Twig\TwigFunction;

/**
 * Read the application version from the VERSION file at the project root.
 */
function readVersion(): string
{
    $contents = @file_get_contents(dirname(__DIR__) . '/VERSION');
    $version = $contents === false ? '' : trim($contents);

    return $version !== '' ? $version : '0.0.0';
}

/**
 * Build the DI container with the application's service definitions.
 *
 * @param array<string, mixed> $overrides definitions that replace the defaults (used by tests)
 */
function buildContainer(array $overrides = []): Container
{
    $builder = new ContainerBuilder();
    $builder->addDefinitions([
        // The settings store comes first: every configuration object below is
        // resolved from it. Its own location cannot, which is why the data
        // directory is asked for separately rather than through AppConfig —
        // building AppConfig would need the store that needs the directory.
        //
        // Seeding happens here rather than in a bootstrap step of its own so
        // that it cannot be skipped by any entry point. The store refuses a
        // second seed, so constructing it repeatedly is harmless.
        SettingsStore::class => static function (): SettingsStore {
            $store = new SettingsStore(AppConfig::dataDir());
            (new SettingsSeeder($store))->seed();

            return $store;
        },
        SupersededEnvironment::class => static fn (): SupersededEnvironment => new SupersededEnvironment(),
        AppConfig::class => static fn (SettingsStore $settings): AppConfig => AppConfig::resolve($settings),
        AuthConfig::class => static fn (SettingsStore $settings): AuthConfig => AuthConfig::resolve($settings),
        PosterConfig::class => static fn (SettingsStore $settings): PosterConfig => PosterConfig::resolve($settings),
        PlexConnectionStore::class => static fn (AppConfig $app): PlexConnectionStore
            => new PlexConnectionStore($app->dataDir),
        PlexPinClient::class => static fn (ClientInterface $http, PlexConnectionStore $store): PlexPinClient
            => new PlexPinClient($http, $store, readVersion()),
        PlexConfig::class => static fn (PlexConnectionStore $connection, SettingsStore $settings): PlexConfig
            => PlexConfig::resolve($connection, $settings),
        AutoImportConfig::class => static fn (SettingsStore $settings): AutoImportConfig
            => AutoImportConfig::resolve($settings),
        LibraryExclusions::class => static fn (SettingsStore $settings): LibraryExclusions
            => LibraryExclusions::resolve($settings),
        // Both values are passed as plain scalars rather than the config
        // objects: App\Support is generic infrastructure, and having it depend
        // on App\Config would invert the layering for nothing. The configs still
        // perform the single bootstrap read, so one `SESSION_DURATION` governs
        // the cookie, the session store, and the authenticated window alike, and
        // one `SESSION_DIR` decides whether any of it survives an update.
        //
        // The directory comes from AppConfig rather than AuthConfig because it
        // is a directory: DATA_DIR and POSTERS_DIR live there, and AuthConfig is
        // about how long a session lasts, not where it is kept.
        SessionInterface::class => static fn (AuthConfig $auth, AppConfig $app): SessionInterface
            => new NativeSession($auth->sessionDuration, $app->sessionDir),
        ClientInterface::class => static fn (): ClientInterface => new Client(),
        PosterStorage::class => static fn (AppConfig $app, PosterConfig $poster): PosterStorage
            => new FilesystemPosterStorage($app->postersDir, $poster->allowedExtensions),
        PosterSource::class => static fn (ClientInterface $http, LoggerInterface $logger): PosterSource
            => new PosteriaApiPosterSource(
                $http,
                rtrim(Env::str('POSTER_SOURCE_URL', 'https://posteria.app'), '/'),
                readVersion(),
                $logger,
            ),
        Database::class => static fn (AppConfig $app): Database => new Database($app->dataDir . '/marquee.sqlite'),
        HttpPlexClient::class => static fn (
            ClientInterface $http,
            PlexConfig $plex,
            LibraryExclusions $exclusions,
        ): HttpPlexClient => new HttpPlexClient($http, $plex, $exclusions),
        PlexClient::class => \DI\get(HttpPlexClient::class),
        PlexPosterWriter::class => \DI\get(HttpPlexClient::class),
        // The wall's now-playing poster tokens are signed with a secret of the
        // application's own, generated once and kept in the connection store.
        // It deliberately does not reuse the Plex token: that token can now be
        // replaced by signing in again, which would rotate the secret and
        // invalidate every token already rendered onto a running wall, and it
        // is empty until Plex is connected, which would make signatures
        // computable by anyone.
        StreamToken::class => static fn (PlexConnectionStore $store): StreamToken
            => new StreamToken($store->signingSecret()),
        // The change dialog's Plex poster candidates are signed with a key
        // *derived* from that same secret rather than the secret itself, so a
        // token minted for one proxy cannot be replayed against the other.
        // That matters in one direction specifically: the wall's poster proxy
        // is public and its tokens are printed into a page anyone can read, so
        // a shared key would let an unauthenticated caller feed a candidate
        // token to the wall route and pull the image back.
        SignedImagePath::class => static fn (PlexConnectionStore $store): SignedImagePath
            => new SignedImagePath(hash_hmac('sha256', 'plex-poster-candidate', $store->signingSecret())),
        PosterWallController::class => static fn (
            Twig $twig,
            PosterWallService $wall,
            NowPlayingService $nowPlaying,
            PlexClient $plex,
            StreamToken $token,
        ): PosterWallController => new PosterWallController(
            $twig,
            $wall,
            $nowPlaying,
            $plex,
            $token,
            dirname(__DIR__) . '/public/assets/live-tv.svg',
        ),
        // Whether to check is a setting; which repository to check is not. The
        // repository exists so a fork or a local build can point the check
        // somewhere else, and offering it in the interface would let an install
        // aim its update notice at a project that is not this one.
        LatestReleaseProvider::class => static fn (
            ClientInterface $http,
            SettingsStore $settings,
        ): LatestReleaseProvider => new GitHubLatestReleaseProvider(
            $http,
            $settings->bool(SettingKey::UpdateCheckEnabled),
            Env::str('UPDATE_REPO', 'jeremehancock/Marquee'),
        ),
        VersionService::class => static fn (LatestReleaseProvider $latest): VersionService
            => new VersionService(readVersion(), $latest),
        LoggerInterface::class => static function (AppConfig $config): LoggerInterface {
            if (!is_dir($config->dataDir)) {
                @mkdir($config->dataDir, 0o775, true);
            }
            $logger = new Logger('marquee');
            $logger->pushHandler(new StreamHandler($config->dataDir . '/marquee.log', Level::Info));

            return $logger;
        },
        Twig::class => static function (
            AppConfig $config,
            CsrfGuard $csrf,
            SessionAuthenticator $auth,
            PlexConnectionStatus $connection,
        ): Twig {
            $twig = Twig::create(dirname(__DIR__) . '/templates', ['cache' => false]);
            // `site_title` names this install and is user-configurable;
            // `app_name` names the product and is not.
            $twig->getEnvironment()->addGlobal('site_title', $config->siteTitle);
            $twig->getEnvironment()->addGlobal('app_name', AppConfig::APP_NAME);
            $twig->getEnvironment()->addGlobal('app_version', readVersion());

            // Cache-busting asset URLs: append the file's mtime so a changed
            // stylesheet or script is a new URL that defeats every cache layer.
            $publicDir = dirname(__DIR__) . '/public';
            $twig->getEnvironment()->addFunction(new TwigFunction(
                'asset',
                static function (string $path) use ($publicDir): string {
                    $file = $publicDir . $path;
                    $mtime = is_file($file) ? filemtime($file) : false;

                    return $mtime === false ? $path : $path . '?v=' . $mtime;
                }
            ));

            // Functions rather than globals, deliberately. A global is
            // evaluated when this service is constructed; the token needs the
            // session, which AuthMiddleware starts during the request. A
            // function is evaluated at render time, when the session is
            // unambiguously live, so no ordering can break it.
            //
            // Whether the visitor is signed in is read the same way and for the
            // same reason. It decides only whether the Log out control is drawn;
            // it grants nothing, and every route that matters is gated by
            // middleware long before a template runs.
            $twig->getEnvironment()->addFunction(new TwigFunction(
                'signed_in',
                static fn (): bool => $auth->isAuthenticated(),
            ));

            // The header's connection status. Deliberately `current()`, never
            // `refresh()`: this renders on every page, and asking Plex its name
            // each time would put a ten-second connect timeout in front of the
            // whole application whenever the server was down — the same reason
            // the connection gate reads configuration only.
            //
            // Templates call it only inside `signed_in()`, so an anonymous
            // request never reaches it.
            $twig->getEnvironment()->addFunction(new TwigFunction(
                'plex_connection',
                static fn (): PlexConnectionState => $connection->current(),
            ));
            $twig->getEnvironment()->addFunction(new TwigFunction(
                'csrf_field',
                static fn (): string => sprintf(
                    '<input type="hidden" name="%s" value="%s">',
                    CsrfMiddleware::FIELD,
                    htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8'),
                ),
                ['is_safe' => ['html']],
            ));
            $twig->getEnvironment()->addFunction(new TwigFunction(
                'csrf_token',
                static fn (): string => $csrf->token(),
            ));

            return $twig;
        },
    ]);

    if ($overrides !== []) {
        $builder->addDefinitions($overrides);
    }

    return $builder->build();
}

/**
 * Assemble the Slim application: middleware stack, error handling, and routes.
 *
 * @return App<\Psr\Container\ContainerInterface|null>
 */
function createApp(?Container $container = null): App
{
    $container ??= buildContainer();

    AppFactory::setContainer($container);
    $app = AppFactory::create();

    /** @var AppConfig $config */
    $config = $container->get(AppConfig::class);
    /** @var LoggerInterface $logger */
    $logger = $container->get(LoggerInterface::class);
    /** @var AuthMiddleware $authMiddleware */
    $authMiddleware = $container->get(AuthMiddleware::class);
    /** @var PlexConnectionMiddleware $plexMiddleware */
    $plexMiddleware = $container->get(PlexConnectionMiddleware::class);

    /** @var CsrfMiddleware $csrfMiddleware */
    $csrfMiddleware = $container->get(CsrfMiddleware::class);

    // Middleware executes outermost-first (last added runs first): errors wrap
    // routing, which wraps auth, which wraps the Plex gate, which wraps body
    // parsing, which wraps the CSRF check and the handler.
    //
    // Auth outside the gate is deliberate: an anonymous visitor is sent to log
    // in before being asked to connect Plex. The other order would expose the
    // connection screen, and its sign-in action, to anyone who can reach the
    // host.
    //
    // The CSRF check is innermost, which is what puts it *inside* body parsing:
    // it reads the token from the parsed body, so it cannot run before the body
    // has been parsed. Being inside auth means the session has been started for
    // every route that has one — which is no longer every route: AuthMiddleware
    // now skips session startup for the public routes that read no session
    // state. That is safe here rather than merely tolerable, because those
    // routes are all reads: routing runs outside auth, so a state-changing
    // method against one is refused as an unrouted method before this check is
    // reached. If a session-less route ever gained a write, this check would
    // still fail closed — a session that was never started yields no token, and
    // CsrfGuard::matches() refuses every candidate rather than minting one.
    $app->add($csrfMiddleware);
    $app->addBodyParsingMiddleware();
    $app->add($plexMiddleware);
    $app->add($authMiddleware);
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware($config->displayErrors, true, true, $logger);

    registerRoutes($app);

    return $app;
}
