<?php

declare(strict_types=1);

namespace App\Controller;

use App\Version\VersionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reports the current version and whether an update is available.
 */
final class VersionController
{
    public function __construct(private readonly VersionService $version)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [
            'version' => $this->version->current(),
            'updateAvailable' => $this->version->updateAvailable(),
            'latest' => $this->version->latest(),
        ];

        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
