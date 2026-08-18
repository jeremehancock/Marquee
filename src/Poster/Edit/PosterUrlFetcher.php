<?php

declare(strict_types=1);

namespace App\Poster\Edit;

use App\Poster\Upload\UploadException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Throwable;

/**
 * Downloads a poster from an address a user supplied, refusing anything that is
 * not on the public internet.
 *
 * Redirects are followed here rather than by the HTTP client, which is the whole
 * reason this class exists. Checking only the submitted address is no check at
 * all: a public host may answer `Location: http://127.0.0.1/`, and the client
 * would follow it without the policy ever seeing it. Guzzle's `on_redirect`
 * hook and a stack middleware can both be made to work, but both depend on
 * middleware ordering and on knowing that `on_redirect` does not fire for the
 * first request — subtleties that are easy to get wrong and hard to see in
 * review. An explicit loop makes "every hop is checked" something you can read
 * off the code.
 *
 * The client handed in MUST be the restricted one. See
 * {@see PublicAddressPolicy} for why this must never be applied to the client
 * the Plex code uses.
 */
final class PosterUrlFetcher
{
    /**
     * Redirects followed before giving up.
     *
     * Matches Guzzle's own default, so replacing its redirect handling with
     * this loop does not change how far a legitimate fetch will chase a
     * `Location`. It also terminates a redirect loop.
     */
    private const MAX_REDIRECTS = 5;

    private const CONNECT_TIMEOUT = 10;
    private const REQUEST_TIMEOUT = 20;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly PublicAddressPolicy $policy,
        private readonly int $maxBytes,
    ) {
    }

    /**
     * @throws UploadException when the address is refused, or the fetch fails
     */
    public function fetch(string $url): string
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('#^https?://#i', $url) !== 1) {
            throw UploadException::invalidUrl();
        }

        $bytes = $this->follow($url);

        if ($bytes === '') {
            throw UploadException::fetchFailed();
        }
        if (strlen($bytes) > $this->maxBytes) {
            throw UploadException::tooLarge($this->maxBytes);
        }

        return $bytes;
    }

    /**
     * Request the URL, following redirects, checking the policy at every hop.
     *
     * The check happens before each request rather than after each response, so
     * a refused address is never connected to — the point is not to learn that
     * the address was internal, it is not to touch it.
     */
    private function follow(string $url): string
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (!$this->policy->permits($current)) {
                throw UploadException::blockedAddress();
            }

            try {
                $response = $this->http->request('GET', $current, [
                    'timeout' => self::REQUEST_TIMEOUT,
                    'connect_timeout' => self::CONNECT_TIMEOUT,
                    'http_errors' => true,
                    // Followed by this loop instead, so the policy sees every
                    // hop. Without this the client would follow silently.
                    'allow_redirects' => false,
                ]);
            } catch (Throwable) {
                throw UploadException::fetchFailed();
            }

            $status = $response->getStatusCode();
            if ($status < 300 || $status > 399) {
                return (string) $response->getBody();
            }

            $location = $response->getHeaderLine('Location');
            if ($location === '') {
                throw UploadException::fetchFailed();
            }

            // Resolved against the hop that issued it, so a relative Location
            // becomes the absolute URL the policy needs to judge. Without this
            // a relative redirect would be judged as a bare path, refused for
            // having no host, and reported as blocked rather than followed.
            $current = (string) UriResolver::resolve(new Uri($current), new Uri($location));
        }

        // Ran out of hops: either a redirect chain longer than any real image
        // host uses, or a loop.
        throw UploadException::fetchFailed();
    }
}
