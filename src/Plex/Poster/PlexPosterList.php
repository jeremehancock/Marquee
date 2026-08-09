<?php

declare(strict_types=1);

namespace App\Plex\Poster;

use SimpleXMLElement;

/**
 * The posters a Plex server holds for one item, in the two groups the change
 * dialog shows them in.
 *
 * Parsing lives here rather than on the client so the one rule that decides
 * what this feature is — a poster on the server, not a remote offering — sits
 * next to the type it produces.
 */
final class PlexPosterList
{
    /**
     * @param list<PlexPosterCandidate> $candidates
     */
    private function __construct(public readonly array $candidates)
    {
    }

    /**
     * Read Plex's `/library/metadata/{id}/posters` answer.
     *
     * Plex mixes two unlike things in it: images stored on the server, given a
     * server-relative path, and remote provider artwork it is merely offering,
     * given an absolute URL to another host. Only the first kind is kept.
     *
     * The test is that the path is rooted at `/`, which is the same property
     * the image proxy enforces on a signed token. Sharing one rule means a
     * candidate that survives this filter is by construction one the proxy can
     * serve, and the two cannot drift apart. Excluding the rest also keeps this
     * tab from restating Find Posters, which already aggregates and *ranks* the
     * same providers.
     */
    public static function fromXml(SimpleXMLElement $xml): self
    {
        $candidates = [];

        foreach ($xml->Photo as $photo) {
            $path = (string) $photo['key'];
            if (!str_starts_with($path, '/')) {
                continue;
            }

            $thumb = (string) $photo['thumb'];
            $posterKey = (string) $photo['ratingKey'];

            $candidates[] = new PlexPosterCandidate(
                path: $path,
                // A server-held poster normally reports the same path twice.
                // Falling back keeps a candidate usable if Plex omits `thumb`
                // rather than dropping it from a list it belongs in.
                thumbPath: str_starts_with($thumb, '/') ? $thumb : $path,
                posterKey: $posterKey,
                origin: str_starts_with($posterKey, 'upload://')
                    ? PlexPosterOrigin::Uploaded
                    : PlexPosterOrigin::Server,
                // Exactly one entry carries "1"; an item whose poster was never
                // explicitly chosen simply has none, which reads as no marker.
                selected: (string) $photo['selected'] === '1',
            );
        }

        return new self($candidates);
    }

    public function isEmpty(): bool
    {
        return $this->candidates === [];
    }

    /**
     * The candidate at a given full-resolution path, or null when the item no
     * longer has one there.
     *
     * Applying re-reads the list and looks the chosen poster up again rather
     * than trusting what the dialog was rendered with. The dialog can sit open
     * for a long time, and a poster removed from Plex meanwhile must fail
     * plainly instead of being selected by a key that no longer resolves.
     */
    public function withPath(string $path): ?PlexPosterCandidate
    {
        foreach ($this->candidates as $candidate) {
            if ($candidate->path === $path) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The user's own history: every poster ever uploaded to the item.
     *
     * @return list<PlexPosterCandidate>
     */
    public function uploaded(): array
    {
        return $this->ofOrigin(PlexPosterOrigin::Uploaded);
    }

    /**
     * Everything else the server holds for the item.
     *
     * @return list<PlexPosterCandidate>
     */
    public function server(): array
    {
        return $this->ofOrigin(PlexPosterOrigin::Server);
    }

    /**
     * @return list<PlexPosterCandidate>
     */
    private function ofOrigin(PlexPosterOrigin $origin): array
    {
        return array_values(array_filter(
            $this->candidates,
            static fn (PlexPosterCandidate $c): bool => $c->origin === $origin,
        ));
    }
}
