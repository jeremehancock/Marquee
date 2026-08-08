<?php

declare(strict_types=1);

namespace App\Plex;

use App\Config\LibraryExclusions;
use App\Config\PlexConfig;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Talks to a Plex Media Server over its XML HTTP API.
 */
final class HttpPlexClient implements PlexClient, PlexPosterWriter
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly PlexConfig $config,
        private readonly LibraryExclusions $exclusions,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function libraries(): array
    {
        $xml = $this->get('/library/sections');

        $libraries = [];
        foreach ($xml->Directory as $directory) {
            $type = (string) $directory['type'];
            if ($type !== 'movie' && $type !== 'show') {
                continue;
            }
            $title = (string) $directory['title'];

            // Excluded libraries are dropped here, at the one place libraries
            // enter the application, so nothing downstream — the import screen,
            // an import, a scheduled run, orphan detection — can observe one.
            if ($this->exclusions->isExcluded($title)) {
                continue;
            }

            $libraries[] = new PlexLibrary(
                key: (string) $directory['key'],
                title: $title,
                type: $type,
            );
        }

        return $libraries;
    }

    public function items(PlexLibrary $library): array
    {
        // `includeGuids=1` adds each item's external ids to the listing. Without
        // it they are only available one metadata request per item, which would
        // make an import's cost scale with library size.
        $xml = $this->get(sprintf('/library/sections/%s/all?includeGuids=1', rawurlencode($library->key)));

        $items = [];
        if ($library->isMovie()) {
            foreach ($xml->Video as $video) {
                $items[] = $this->item($video, PlexMediaType::Movie, $library);
            }
        } else {
            foreach ($xml->Directory as $directory) {
                $items[] = $this->item($directory, PlexMediaType::Show, $library);
            }
        }

        return $items;
    }

    public function seasons(PlexItem $show): array
    {
        $xml = $this->get(sprintf('/library/metadata/%s/children', rawurlencode($show->ratingKey)));

        $items = [];
        foreach ($xml->Directory as $directory) {
            if ((string) $directory['type'] !== 'season') {
                continue;
            }
            $items[] = new PlexItem(
                ratingKey: (string) $directory['ratingKey'],
                mediaType: PlexMediaType::Season,
                title: (string) $directory['title'],
                // The show's year, for the same reason as the id below, and not
                // the season's own air year: a season search resolves the show
                // first and the season within it, so the show is the work the
                // year has to identify. Plex reports no year on a season node
                // anyway. Without it a search that falls back to the title
                // cannot separate two shows that share one.
                year: $show->year,
                thumb: $this->attr($directory, 'thumb'),
                libraryTitle: $show->libraryTitle,
                parentTitle: $show->title,
                sectionKey: $show->sectionKey,
                addedAt: $this->intAttr($directory, 'addedAt'),
                seasonNumber: $this->countAttr($directory, 'index'),
                // A season carries its show's id, not one of its own: a season
                // is addressed as a show plus a season number, and the number
                // is already recorded separately above.
                tmdbId: $show->tmdbId,
            );
        }

        return $items;
    }

    public function collections(PlexLibrary $library): array
    {
        $xml = $this->get(sprintf('/library/sections/%s/collections?includeGuids=1', rawurlencode($library->key)));

        $items = [];
        foreach ($xml->Directory as $directory) {
            $items[] = $this->item($directory, PlexMediaType::Collection, $library);
        }

        return $items;
    }

    public function downloadPoster(PlexItem $item): string
    {
        if ($item->thumb === null || $item->thumb === '') {
            throw PlexException::unexpectedResponse();
        }

        try {
            $response = $this->http->request('GET', $this->config->serverUrl . $item->thumb, $this->options());
        } catch (GuzzleException $e) {
            throw $this->classify($e);
        }

        return (string) $response->getBody();
    }

    public function itemPoster(string $ratingKey): string
    {
        $xml = $this->get('/library/metadata/' . rawurlencode($ratingKey));

        $thumb = null;
        foreach ($xml->children() as $child) {
            $candidate = $this->attr($child, 'thumb');
            if ($candidate !== null && $candidate !== '') {
                $thumb = $candidate;
                break;
            }
        }

        if ($thumb === null) {
            throw PlexException::unexpectedResponse();
        }

        try {
            $response = $this->http->request('GET', $this->config->serverUrl . $thumb, $this->options());
        } catch (GuzzleException $e) {
            throw $this->classify($e);
        }

        return (string) $response->getBody();
    }

    public function sessions(): array
    {
        $xml = $this->get('/status/sessions');

        $sessions = [];
        foreach ($xml->children() as $node) {
            $session = $this->session($node);
            if ($session !== null) {
                $sessions[] = $session;
            }
        }

        return $sessions;
    }

    public function sessionPoster(string $thumb): string
    {
        if ($thumb === '') {
            throw PlexException::unexpectedResponse();
        }

        try {
            $response = $this->http->request('GET', $this->config->serverUrl . $thumb, $this->options());
        } catch (GuzzleException $e) {
            throw $this->classify($e);
        }

        return (string) $response->getBody();
    }

    /**
     * The server's friendly name, read from the root endpoint.
     *
     * That response also carries `myPlexUsername`, which is never *displayed*:
     * it is an email address, and this is the screen users paste into support
     * threads. The server's name identifies the connection without disclosing
     * anything personal, and for a poster manager it is the more useful of the
     * two — it says which server is connected, which is what goes wrong when a
     * URL points at the wrong host. The owner is read separately, by
     * {@see \App\Plex\Connection\PlexServerOwner}, which cannot go through this
     * client: it runs before any token is stored.
     *
     * Every failure is absorbed: the name is decoration, and no page should
     * break because a server did not answer.
     */
    public function serverName(): ?string
    {
        return $this->rootAttr('friendlyName');
    }

    /**
     * One attribute of the server's root response, or null when it cannot be
     * read. Every failure is absorbed: callers decide what an unknown means,
     * and for the two callers here it means "no name" and "refuse", neither of
     * which should raise.
     */
    private function rootAttr(string $name): ?string
    {
        if (!$this->config->isConfigured()) {
            return null;
        }

        try {
            $xml = $this->get('/');
        } catch (PlexException) {
            return null;
        }

        $value = $this->attr($xml, $name);

        return $value !== null && $value !== '' ? $value : null;
    }

    /**
     * Map one `/status/sessions` child element to a session, or null when the
     * element carries no usable type. Music and unrecognised types are still
     * returned (typed accordingly) so the caller decides what to drop.
     */
    private function session(SimpleXMLElement $node): ?PlexSession
    {
        $rawType = (string) $node['type'];
        if ($rawType === '') {
            return null;
        }

        $live = ((string) $node['live']) === '1';
        $user = $this->attr($node->User, 'title') ?? '';

        // Live television is recognised by Plex's `live` flag rather than by
        // media type. A DVR tuner reports its programmes as movies or episodes
        // and points their artwork at the tuner rather than at the library, so
        // type alone would send them down the library-art path. Music is exempt
        // so live radio stays music and stays off the wall.
        if ($live && $rawType !== 'track') {
            return new PlexSession(
                type: PlexSessionType::LiveTv,
                title: (string) $node['title'],
                user: $user,
                live: true,
                grandparentTitle: $this->attr($node, 'grandparentTitle'),
            );
        }

        return match ($rawType) {
            'movie' => new PlexSession(
                type: PlexSessionType::Movie,
                title: (string) $node['title'],
                user: $user,
                live: $live,
                thumb: $this->attr($node, 'thumb'),
                year: $this->intAttr($node, 'year'),
            ),
            'episode' => new PlexSession(
                type: PlexSessionType::Episode,
                title: (string) $node['title'],
                user: $user,
                live: $live,
                thumb: $this->attr($node, 'grandparentThumb'),
                grandparentTitle: $this->attr($node, 'grandparentTitle'),
                seasonNumber: $this->intAttr($node, 'parentIndex'),
                episodeNumber: $this->intAttr($node, 'index'),
            ),
            // A clip that is not live is a trailer or extra, which the wall
            // ignores; the live case is handled above.
            'track' => new PlexSession(PlexSessionType::Music, (string) $node['title'], $user, $live),
            default => new PlexSession(PlexSessionType::Other, (string) $node['title'], $user, $live),
        };
    }

    public function uploadPoster(string $ratingKey, string $imageBytes): void
    {
        $this->write(
            'POST',
            sprintf('/library/metadata/%s/posters', rawurlencode($ratingKey)),
            ['body' => $imageBytes],
        );
    }

    public function lockPoster(string $ratingKey): void
    {
        $this->write('PUT', sprintf('/library/metadata/%s?thumb.locked=1', rawurlencode($ratingKey)));
    }

    public function removeOverlayLabel(string $sectionKey, int $plexType, string $ratingKey): void
    {
        $query = http_build_query([
            'type' => $plexType,
            'id' => $ratingKey,
            'label[].tag.tag-' => 'Overlay',
        ]);

        $this->write('PUT', sprintf('/library/sections/%s/all?%s', rawurlencode($sectionKey), $query));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function write(string $method, string $path, array $extra = []): void
    {
        if (!$this->config->isConfigured()) {
            throw PlexException::notConfigured();
        }

        try {
            $this->http->request($method, $this->config->serverUrl . $path, $extra + $this->options());
        } catch (GuzzleException $e) {
            throw $this->classify($e);
        }
    }

    private function item(SimpleXMLElement $element, PlexMediaType $type, PlexLibrary $library): PlexItem
    {
        return new PlexItem(
            ratingKey: (string) $element['ratingKey'],
            mediaType: $type,
            title: (string) $element['title'],
            year: isset($element['year']) ? (int) $element['year'] : null,
            thumb: $this->attr($element, 'thumb'),
            libraryTitle: $library->title,
            sectionKey: $library->key,
            addedAt: $this->intAttr($element, 'addedAt'),
            tmdbId: $this->tmdbId($element),
        );
    }

    /**
     * The work's TMDB id, read from the item's `<Guid>` children.
     *
     * Modern Plex agents put an opaque `plex://movie/<hash>` in the element's
     * own `guid` attribute and expose the external ids as children instead, so
     * only the children are read. Legacy agents, which put the id back in the
     * attribute as `com.plexapp.agents.themoviedb://1726`, are deliberately not
     * parsed — one extraction rule, not two.
     *
     * Null is an ordinary result: collections have no upstream record at all,
     * and neither does media Plex never matched.
     */
    private function tmdbId(SimpleXMLElement $element): ?string
    {
        foreach ($element->children() as $child) {
            if ($child->getName() !== 'Guid') {
                continue;
            }
            $id = $this->attr($child, 'id');
            if ($id !== null && preg_match('#^tmdb://(\d+)#', $id, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function attr(SimpleXMLElement $element, string $name): ?string
    {
        return isset($element[$name]) ? (string) $element[$name] : null;
    }

    /**
     * Read an integer attribute (e.g. Plex's `addedAt` Unix timestamp), or null
     * when it is absent or non-positive.
     */
    private function intAttr(SimpleXMLElement $element, string $name): ?int
    {
        if (!isset($element[$name])) {
            return null;
        }
        $value = (int) $element[$name];

        return $value > 0 ? $value : null;
    }

    /**
     * Read a counting attribute that may legitimately be zero, such as a
     * season's `index` — 0 is the Specials season, not a missing value, so
     * intAttr()'s "non-positive means absent" rule cannot be used here.
     */
    private function countAttr(SimpleXMLElement $element, string $name): ?int
    {
        if (!isset($element[$name])) {
            return null;
        }
        $value = (int) $element[$name];

        return $value >= 0 ? $value : null;
    }

    private function get(string $path): SimpleXMLElement
    {
        if (!$this->config->isConfigured()) {
            throw PlexException::notConfigured();
        }

        try {
            $response = $this->http->request('GET', $this->config->serverUrl . $path, $this->options());
            $body = (string) $response->getBody();
        } catch (GuzzleException $e) {
            throw $this->classify($e);
        } catch (InvalidArgumentException $e) {
            // An unparseable address fails before a request is ever made, so
            // Guzzle raises this rather than a GuzzleException and the catch
            // above never sees it. Configuration already rejects such an
            // address at bootstrap; this is the backstop that stops one
            // escaping as a 500 from the page whose job is to explain that
            // Plex cannot be reached.
            throw PlexException::connectionFailed($e);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if (!$xml instanceof SimpleXMLElement) {
            throw PlexException::unexpectedResponse();
        }

        return $xml;
    }

    /**
     * Maps a failed Plex request to a user-facing exception. A 404 means the
     * item is gone (likely orphaned); a 401 means the token was rejected;
     * anything else — including a transport failure with no response — is a
     * connection problem. No extra request is made: the status is taken from
     * the response the failed request already carried.
     */
    private function classify(GuzzleException $e): PlexException
    {
        $response = $e instanceof RequestException ? $e->getResponse() : null;
        if ($response !== null) {
            $status = $response->getStatusCode();
            if ($status === 404) {
                return PlexException::itemNotFound($e);
            }
            if ($status === 401) {
                return PlexException::authFailed($e);
            }
        }

        return PlexException::connectionFailed($e);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'headers' => [
                'X-Plex-Token' => $this->config->token,
                'Accept' => 'application/xml',
            ],
            'connect_timeout' => $this->config->connectTimeout,
            'timeout' => $this->config->requestTimeout,
            'http_errors' => true,
        ];
    }
}
