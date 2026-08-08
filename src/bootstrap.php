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
use App\Plex\Connection\PlexConnectionStore;
use App\Plex\Connection\PlexPinClient;
use App\Plex\HttpPlexClient;
use App\Plex\PlexClient;
use App\Plex\PlexPosterWriter;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterStorage;
use App\Poster\Source\PosteriaApiPosterSource;
use App\Poster\Source\PosterSource;
use App\Poster\Wall\NowPlayingService;
use App\Poster\Wall\PosterWallService;
use App\Poster\Wall\StreamToken;
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
        AppConfig::class => static fn (): AppConfig => AppConfig::fromEnv(),
        AuthConfig::class => static fn (): AuthConfig => AuthConfig::fromEnv(),
        PosterConfig::class => static fn (): PosterConfig => PosterConfig::fromEnv(),
        PlexConnectionStore::class => static fn (AppConfig $app): PlexConnectionStore
            => new PlexConnectionStore($app->dataDir),
        PlexPinClient::class => static fn (ClientInterface $http, PlexConnectionStore $store): PlexPinClient
            => new PlexPinClient($http, $store, readVersion()),
        PlexConfig::class => static fn (PlexConnectionStore $store): PlexConfig => PlexConfig::resolve($store),
        AutoImportConfig::class => static fn (): AutoImportConfig => AutoImportConfig::fromEnv(),
        LibraryExclusions::class => static fn (): LibraryExclusions => LibraryExclusions::fromEnv(),
        SessionInterface::class => static fn (): SessionInterface => new NativeSession(),
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
        LatestReleaseProvider::class => static fn (ClientInterface $http): LatestReleaseProvider
            => new GitHubLatestReleaseProvider(
                $http,
                Env::bool('UPDATE_CHECK_ENABLED', false),
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
        Twig::class => static function (AppConfig $config, CsrfGuard $csrf, SessionAuthenticator $auth): Twig {
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
    // has been parsed. Being inside auth also guarantees the session exists,
    // since AuthMiddleware starts it on every request.
    $app->add($csrfMiddleware);
    $app->addBodyParsingMiddleware();
    $app->add($plexMiddleware);
    $app->add($authMiddleware);
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware($config->displayErrors, true, true, $logger);

    registerRoutes($app);

    return $app;
}
