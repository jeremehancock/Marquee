<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\AppConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Serves the web app manifest, named after SITE_TITLE.
 */
final class ManifestController
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $manifest = [
            'name' => $this->config->siteTitle,
            'short_name' => $this->config->siteTitle,
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#14161c',
            'theme_color' => '#14161c',
            'icons' => [
                [
                    'src' => '/assets/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => '/assets/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        $response->getBody()->write(json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/manifest+json');
    }
}
