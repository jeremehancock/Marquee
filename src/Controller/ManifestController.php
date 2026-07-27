<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\AppConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Serves the web app manifest, named after the product rather than SITE_TITLE:
 * the install name is written to the device's home screen once and is not
 * re-read later, so it must not follow a per-install setting.
 */
final class ManifestController
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $manifest = [
            'name' => AppConfig::APP_NAME,
            'short_name' => AppConfig::APP_NAME,
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#1c1e24',
            'theme_color' => '#1c1e24',
            'icons' => [
                [
                    'src' => '/assets/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/assets/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/assets/icons/icon-192-maskable.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/assets/icons/icon-512-maskable.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        $response->getBody()->write(json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/manifest+json');
    }
}
