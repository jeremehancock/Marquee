<?php

declare(strict_types=1);

namespace App;

use App\Auth\AuthMiddleware;
use App\Config\AppConfig;
use App\Config\AuthConfig;
use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Database\Database;
use App\Plex\HttpPlexClient;
use App\Plex\PlexClient;
use App\Poster\FilesystemPosterStorage;
use App\Poster\PosterStorage;
use App\Support\Session\NativeSession;
use App\Support\Session\SessionInterface;
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
        PlexConfig::class => static fn (): PlexConfig => PlexConfig::fromEnv(),
        SessionInterface::class => static fn (): SessionInterface => new NativeSession(),
        ClientInterface::class => static fn (): ClientInterface => new Client(),
        PosterStorage::class => static fn (AppConfig $app, PosterConfig $poster): PosterStorage
            => new FilesystemPosterStorage($app->postersDir, $poster->allowedExtensions),
        Database::class => static fn (AppConfig $app): Database => new Database($app->dataDir . '/marquee.sqlite'),
        PlexClient::class => static fn (ClientInterface $http, PlexConfig $plex): PlexClient
            => new HttpPlexClient($http, $plex),
        LoggerInterface::class => static function (AppConfig $config): LoggerInterface {
            if (!is_dir($config->dataDir)) {
                @mkdir($config->dataDir, 0o775, true);
            }
            $logger = new Logger('marquee');
            $logger->pushHandler(new StreamHandler($config->dataDir . '/marquee.log', Level::Info));

            return $logger;
        },
        Twig::class => static function (AppConfig $config): Twig {
            $twig = Twig::create(dirname(__DIR__) . '/templates', ['cache' => false]);
            $twig->getEnvironment()->addGlobal('site_title', $config->siteTitle);

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

    // Middleware executes outermost-first (last added runs first): errors wrap
    // routing, which wraps auth, which wraps body parsing and the handler.
    $app->addBodyParsingMiddleware();
    $app->add($authMiddleware);
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware($config->displayErrors, true, true, $logger);

    registerRoutes($app);

    return $app;
}
