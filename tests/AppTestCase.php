<?php

declare(strict_types=1);

namespace App\Tests;

use function App\buildContainer;
use function App\createApp;

use App\Support\Session\ArraySession;
use App\Support\Session\SessionInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class AppTestCase extends TestCase
{
    /**
     * @param array<string, string> $env
     * @param array<string, mixed>  $overrides
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    protected function makeApp(array $env = [], array $overrides = []): App
    {
        $defaults = [
            'SITE_TITLE' => 'Marquee',
            'AUTH_USERNAME' => 'admin',
            'AUTH_PASSWORD' => 'secret',
            'AUTH_BYPASS' => 'false',
            'SESSION_DURATION' => '3600',
            'DATA_DIR' => sys_get_temp_dir() . '/marquee-test-data',
            'DISPLAY_ERRORS' => 'false',
            // Reset Plex vars each time so one test's config cannot leak into another.
            'PLEX_SERVER_URL' => '',
            'PLEX_TOKEN' => '',
            'PLEX_REMOVE_OVERLAY_LABEL' => 'false',
            'UPDATE_CHECK_ENABLED' => 'false',
        ];
        foreach (array_merge($defaults, $env) as $key => $value) {
            putenv($key . '=' . $value);
        }

        // Use an in-memory session so the auth flow never touches PHP globals.
        $overrides = array_merge(
            [SessionInterface::class => static fn (): SessionInterface => new ArraySession()],
            $overrides,
        );

        return createApp(buildContainer($overrides));
    }

    /**
     * @param App<\Psr\Container\ContainerInterface|null> $app
     */
    protected function get(App $app, string $path): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path);

        return $app->handle($request);
    }

    /**
     * @param App<\Psr\Container\ContainerInterface|null> $app
     * @param array<string, string>                       $data
     */
    protected function postForm(App $app, string $path, array $data): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query($data));
        $request->getBody()->rewind();

        return $app->handle($request);
    }
}
