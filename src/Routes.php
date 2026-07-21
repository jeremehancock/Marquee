<?php

declare(strict_types=1);

namespace App;

use App\Controller\AuthController;
use App\Controller\HealthController;
use App\Controller\HomeController;
use Slim\App;

/**
 * Register every HTTP route. Kept in one place so the route map is easy to read.
 *
 * @param App<\Psr\Container\ContainerInterface|null> $app
 */
function registerRoutes(App $app): void
{
    $app->get('/health', HealthController::class);

    $app->get('/login', [AuthController::class, 'showLogin']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->get('/logout', [AuthController::class, 'logout']);

    $app->get('/', HomeController::class);
}
