<?php

declare(strict_types=1);

namespace App\Version;

use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Best-effort latest-release lookup from the GitHub releases API. Only runs when
 * enabled; any failure yields null so it never disrupts the page.
 */
final class GitHubLatestReleaseProvider implements LatestReleaseProvider
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly bool $enabled,
        private readonly string $repository,
    ) {
    }

    public function latestVersion(): ?string
    {
        if (!$this->enabled || $this->repository === '') {
            return null;
        }

        try {
            $response = $this->http->request(
                'GET',
                sprintf('https://api.github.com/repos/%s/releases/latest', $this->repository),
                [
                    'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Marquee'],
                    'connect_timeout' => 3,
                    'timeout' => 5,
                    'http_errors' => true,
                ],
            );
            $data = json_decode((string) $response->getBody(), true);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($data) || !isset($data['tag_name']) || !is_string($data['tag_name'])) {
            return null;
        }

        return ltrim($data['tag_name'], 'vV');
    }
}
