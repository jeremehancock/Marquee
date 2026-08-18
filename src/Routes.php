<?php

declare(strict_types=1);

namespace App;

use App\Controller\AuthController;
use App\Controller\ChangePosterController;
use App\Controller\GalleryController;
use App\Controller\HealthController;
use App\Controller\ManifestController;
use App\Controller\OrphanController;
use App\Controller\PlexConnectionController;
use App\Controller\PlexImportController;
use App\Controller\PlexPosterImageController;
use App\Controller\PosterController;
use App\Controller\PosterImageController;
use App\Controller\PosterWallController;
use App\Controller\SettingsController;
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

    $app->get('/logout', [AuthController::class, 'logout']);

    // Signing in and the Plex connection are one screen rendered two ways, but
    // each state gets the URL that names it: nobody managing a connection should
    // be sitting on a page called /login. Each redirects to the other when the
    // visitor is in the wrong state, so neither can be reached misnamed.
    $app->get('/login', [PlexConnectionController::class, 'login']);

    $app->get('/', [GalleryController::class, 'home']);
    $app->get('/library/{category}', [GalleryController::class, 'show']);

    $app->get('/posters/{category}/{filename}', PosterImageController::class);

    // A Plex-held poster candidate, proxied so no Plex URL (and so no Plex
    // token) is ever put in a page. Behind the login like every other route
    // that is not explicitly public: the signed token bounds *which* paths this
    // will fetch, not *who* may ask for them.
    $app->get('/plex-poster-image/{token}', PlexPosterImageController::class);

    $app->post('/library/{category}/change/upload', [ChangePosterController::class, 'upload']);
    $app->post('/library/{category}/change/url', [ChangePosterController::class, 'url']);
    $app->post('/library/{category}/send-to-plex', [ChangePosterController::class, 'sendToPlex']);
    $app->post('/library/{category}/fetch-from-plex', [ChangePosterController::class, 'fetchFromPlex']);
    $app->get('/library/{category}/find-posters', [ChangePosterController::class, 'findPosters']);
    $app->get('/library/{category}/plex-posters', [ChangePosterController::class, 'plexPosters']);
    $app->post('/library/{category}/change/plex-poster', [ChangePosterController::class, 'usePlexPoster']);
    $app->post('/library/{category}/delete', [PosterController::class, 'delete']);

    $app->get('/connect', [PlexConnectionController::class, 'show']);

    $app->get('/plex', [PlexImportController::class, 'show']);
    $app->post('/plex/import', [PlexImportController::class, 'run']);

    // Signing in to Plex, which is how Marquee is entered. The first two are
    // reachable without a session because they are how one is obtained;
    // disconnecting is not, because it destroys the connection.
    $app->post('/plex/connection/sign-in', [PlexConnectionController::class, 'start']);
    $app->get('/plex/connection/status', [PlexConnectionController::class, 'poll']);
    $app->post('/plex/connection/sign-out', [PlexConnectionController::class, 'signOut']);

    // Behind both gates like every other screen: settings are the install's,
    // not the visitor's.
    $app->get('/settings', [SettingsController::class, 'show']);
    $app->post('/settings', [SettingsController::class, 'save']);

    $app->get('/orphans', [OrphanController::class, 'show']);
    $app->get('/orphans/list', [OrphanController::class, 'results']);
    $app->post('/orphans/delete', [OrphanController::class, 'delete']);
    $app->post('/orphans/delete-all', [OrphanController::class, 'deleteAll']);

    $app->get('/wall', [PosterWallController::class, 'show']);
    $app->get('/wall/posters', [PosterWallController::class, 'posters']);
    $app->get('/wall/streams', [PosterWallController::class, 'streams']);
    // The wall's own posters, reachable without a session because the wall is.
    // Restricted to the categories the wall draws; /posters stays behind the
    // login for everything else.
    $app->get('/wall/poster/{category}/{filename}', [PosterImageController::class, 'wall']);
    $app->get('/wall/stream-poster/{id}', [PosterWallController::class, 'streamPoster']);
}
