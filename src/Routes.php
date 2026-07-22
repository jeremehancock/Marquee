<?php

declare(strict_types=1);

namespace App;

use App\Controller\AuthController;
use App\Controller\ChangePosterController;
use App\Controller\GalleryController;
use App\Controller\HealthController;
use App\Controller\ManifestController;
use App\Controller\OrphanController;
use App\Controller\PlexImportController;
use App\Controller\PosterController;
use App\Controller\PosterImageController;
use App\Controller\PosterWallController;
use App\Controller\VersionController;
use Slim\App;

/**
 * Register every HTTP route. Kept in one place so the route map is easy to read.
 *
 * @param App<\Psr\Container\ContainerInterface|null> $app
 */
function registerRoutes(App $app): void
{
    $app->get('/health', HealthController::class);
    $app->get('/manifest.webmanifest', ManifestController::class);
    $app->get('/version', VersionController::class);

    $app->get('/login', [AuthController::class, 'showLogin']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->get('/logout', [AuthController::class, 'logout']);

    $app->get('/', [GalleryController::class, 'home']);
    $app->get('/library/{category}', [GalleryController::class, 'show']);

    $app->get('/posters/{category}/{filename}', PosterImageController::class);

    $app->post('/library/{category}/change/upload', [ChangePosterController::class, 'upload']);
    $app->post('/library/{category}/change/url', [ChangePosterController::class, 'url']);
    $app->post('/library/{category}/fetch-from-plex', [ChangePosterController::class, 'fetchFromPlex']);
    $app->get('/library/{category}/find-posters', [ChangePosterController::class, 'findPosters']);
    $app->post('/library/{category}/delete', [PosterController::class, 'delete']);

    $app->get('/plex', [PlexImportController::class, 'show']);
    $app->post('/plex/import', [PlexImportController::class, 'run']);

    $app->get('/orphans', [OrphanController::class, 'show']);
    $app->post('/orphans/delete-all', [OrphanController::class, 'deleteAll']);

    $app->get('/wall', [PosterWallController::class, 'show']);
    $app->get('/wall/posters', [PosterWallController::class, 'posters']);
}
