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
     * server-relative path, and remote provider artwork it is only offering,
     * given an absolute URL to another host. Both are kept, classified by which
     * they are, because that decides everything downstream — how the image is
     * shown, and how applying it reaches Plex.
     *
     * A server-relative path is exactly what the image proxy will accept, so a
     * candidate classified as held is by construction one the proxy can serve;
     * the classification and the proxy's guard are the same rule and cannot
     * drift apart.
     *
     * An offered candidate must be an ordinary web URL, since it goes into the
     * page as an image address and is applied as one. Anything else Plex might
     * report is dropped rather than trusted.
     */
    public static function fromXml(SimpleXMLElement $xml): self
    {
        $candidates = [];

        foreach ($xml->Photo as $photo) {
            $path = (string) $photo['key'];
            $held = str_starts_with($path, '/');

            if (!$held && !self::isWebUrl($path)) {
                continue;
            }

            $thumb = (string) $photo['thumb'];
            $posterKey = (string) $photo['ratingKey'];

            $candidates[] = new PlexPosterCandidate(
                path: $path,
                // A held poster normally reports the same path twice, and an
                // offered one usually reports a smaller preview. Either way a
                // thumb that is not usable falls back to the full image rather
                // than dropping a candidate from a list it belongs in.
                thumbPath: self::usableThumb($thumb, $held) ? $thumb : $path,
                posterKey: $posterKey,
                origin: match (true) {
                    !$held => PlexPosterOrigin::Offered,
                    str_starts_with($posterKey, 'upload://') => PlexPosterOrigin::Uploaded,
                    default => PlexPosterOrigin::Server,
                },
                // Exactly one entry carries "1"; an item whose poster was never
                // explicitly chosen simply has none, which reads as no marker.
                selected: (string) $photo['selected'] === '1',
            );
        }

        return new self($candidates);
    }

    /**
     * A held poster's thumb must stay on the server, because it is served
     * through the proxy. An offered one's must be a web URL, because it is put
     * straight into the page.
     */
    private static function usableThumb(string $thumb, bool $held): bool
    {
        return $held ? str_starts_with($thumb, '/') : self::isWebUrl($thumb);
    }

    private static function isWebUrl(string $value): bool
    {
        return str_starts_with($value, 'https://') || str_starts_with($value, 'http://');
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
            if ($candidate->origin->isHeldOnServer() && $candidate->path === $path) {
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
     * Artwork Plex knows about but has not downloaded.
     *
     * @return list<PlexPosterCandidate>
     */
    public function offered(): array
    {
        return $this->ofOrigin(PlexPosterOrigin::Offered);
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
