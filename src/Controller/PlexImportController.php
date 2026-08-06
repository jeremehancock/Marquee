<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\LibraryExclusions;
use App\Plex\Connection\PlexConnectionStatus;
use App\Plex\Import\ImportService;
use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Plex\PlexFailureMessage;
use App\Plex\PlexMediaType;
use App\Support\Flash;
use App\Support\LastCategory;
use App\Support\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * The Plex page: choose libraries and media types, and run an import.
 */
final class PlexImportController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly PlexClient $plex,
        private readonly ImportService $import,
        private readonly Flash $flash,
        private readonly SessionInterface $session,
        private readonly LibraryExclusions $exclusions,
        private readonly PlexConnectionStatus $connection,
        private readonly PlexFailureMessage $plexMessage,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $configured = $this->plex->isConfigured();
        $libraries = [];
        $error = null;

        if ($configured) {
            try {
                $libraries = $this->plex->libraries();
            } catch (PlexException $e) {
                $error = $this->plexMessage->for($e);
            }
        }

        return $this->twig->render($response, 'plex.html.twig', [
            'configured' => $configured,
            // This is the page the connection panel lives on, so it is the one
            // place that asks Plex its name rather than using the cached one.
            'connection' => $this->connection->refresh(),
            'libraries' => $libraries,
            // The client has already dropped excluded libraries, so an empty
            // list here can mean either "the server has none" or "they are all
            // excluded"; the template needs this to word the difference.
            'has_exclusions' => $this->exclusions->hasAny(),
            'error' => $error,
            'mediaTypes' => [
                ['value' => 'movie', 'label' => 'Movies'],
                ['value' => 'show', 'label' => 'TV Shows'],
                ['value' => 'season', 'label' => 'TV Seasons'],
                ['value' => 'collection', 'label' => 'Collections'],
            ],
            'flash' => $this->flash->pull(),
            'back_url' => LastCategory::backUrl($this->session),
        ]);
    }

    public function run(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $sections = $this->stringList($body['sections'] ?? null);
        $types = [];
        foreach ($this->stringList($body['types'] ?? null) as $value) {
            $type = PlexMediaType::fromString($value);
            if ($type !== null) {
                $types[] = $type;
            }
        }

        if ($sections === [] || $types === []) {
            $this->flash->add('error', 'Select at least one library and one media type.');

            return $this->backToPlex($response);
        }

        $force = isset($body['force']);

        try {
            $result = $this->import->import($sections, $types, $force);
            $succeeded = $result->imported() > 0 || $result->skipped() > 0;
            $this->flash->add($succeeded ? 'success' : 'error', $result->summary());
        } catch (PlexException $e) {
            $this->flash->add('error', $this->plexMessage->for($e));
        }

        return $this->backToPlex($response);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function backToPlex(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader('Location', '/plex')->withStatus(302);
    }
}
