<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Edit\PosterUrlFetcher;
use App\Poster\Edit\PublicAddressPolicy;
use App\Poster\Upload\UploadException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Following redirects here rather than in the HTTP client is the point of this
 * class, so most of these tests are about redirects.
 *
 * Each asserts on the requests actually issued, not only on the exception. A
 * refused address must never be *connected to* — an implementation that fetched
 * first and complained afterwards would satisfy a test that only checked the
 * error, while leaking exactly what this exists to prevent.
 */
final class PosterUrlFetcherTest extends TestCase
{
    private const PUBLIC_IP = '93.184.216.34';
    private const PRIVATE_IP = '192.168.1.50';

    /**
     * Every request that reached the handler.
     *
     * Recorded by the small middleware in {@see fetcher()} rather than by
     * Guzzle's `Middleware::history`, whose container is an untyped
     * `array|ArrayAccess` taken by reference. Recording the one thing these
     * tests assert on keeps the type exact.
     *
     * @var list<RequestInterface>
     */
    private array $sent = [];

    /**
     * @param list<Response>        $responses
     * @param array<string, string> $addresses host to the address it resolves to
     */
    private function fetcher(array $responses, array $addresses = []): PosterUrlFetcher
    {
        $this->sent = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                $this->sent[] = $request;

                return $handler($request, $options);
            };
        });

        $policy = new PublicAddressPolicy(
            static fn (string $host): array => [$addresses[$host] ?? self::PUBLIC_IP],
        );

        return new PosterUrlFetcher(new Client(['handler' => $stack]), $policy, 5_000_000);
    }

    /**
     * @return list<string>
     */
    private function requestedUrls(): array
    {
        $urls = [];
        foreach ($this->sent as $request) {
            $urls[] = (string) $request->getUri();
        }

        return $urls;
    }

    public function testAPublicUrlIsFetched(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], 'image-bytes')]);

        self::assertSame('image-bytes', $fetcher->fetch('https://poster.example/p.jpg'));
        self::assertSame(['https://poster.example/p.jpg'], $this->requestedUrls());
    }

    public function testAPrivateAddressIsNeverRequested(): void
    {
        $fetcher = $this->fetcher(
            [new Response(200, [], 'should-never-be-served')],
            ['internal.example' => self::PRIVATE_IP],
        );

        $this->expectException(UploadException::class);
        $this->expectExceptionMessageMatches('/not on the public internet/');

        try {
            $fetcher->fetch('https://internal.example/p.jpg');
        } finally {
            self::assertSame([], $this->requestedUrls(), 'No request may be made to a refused address.');
        }
    }

    public function testARedirectToAPrivateAddressIsNotFollowed(): void
    {
        $fetcher = $this->fetcher(
            [
                new Response(302, ['Location' => 'http://internal.example/p.jpg']),
                new Response(200, [], 'should-never-be-served'),
            ],
            ['internal.example' => self::PRIVATE_IP],
        );

        $this->expectException(UploadException::class);
        $this->expectExceptionMessageMatches('/not on the public internet/');

        try {
            $fetcher->fetch('https://poster.example/p.jpg');
        } finally {
            self::assertSame(
                ['https://poster.example/p.jpg'],
                $this->requestedUrls(),
                'The first hop is requested; the redirect target is not.',
            );
        }
    }

    public function testARedirectToAPublicAddressIsFollowed(): void
    {
        $fetcher = $this->fetcher([
            new Response(301, ['Location' => 'https://cdn.example/final.jpg']),
            new Response(200, [], 'image-bytes'),
        ]);

        self::assertSame('image-bytes', $fetcher->fetch('https://poster.example/p.jpg'));
        self::assertSame(
            ['https://poster.example/p.jpg', 'https://cdn.example/final.jpg'],
            $this->requestedUrls(),
        );
    }

    /**
     * A relative Location judged as-is would have no host, be refused for that,
     * and report a legitimate redirect as a blocked address.
     */
    public function testARelativeRedirectIsResolvedAgainstTheHopThatIssuedIt(): void
    {
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => '/images/final.jpg']),
            new Response(200, [], 'image-bytes'),
        ]);

        self::assertSame('image-bytes', $fetcher->fetch('https://poster.example/posters/p.jpg'));
        self::assertSame(
            ['https://poster.example/posters/p.jpg', 'https://poster.example/images/final.jpg'],
            $this->requestedUrls(),
        );
    }

    /**
     * The check is on the resolved address, so a relative redirect cannot be
     * used to reach a host the policy would refuse.
     */
    public function testAProtocolRelativeRedirectToAPrivateHostIsRefused(): void
    {
        $fetcher = $this->fetcher(
            [
                new Response(302, ['Location' => '//internal.example/p.jpg']),
                new Response(200, [], 'should-never-be-served'),
            ],
            ['internal.example' => self::PRIVATE_IP],
        );

        $this->expectException(UploadException::class);

        try {
            $fetcher->fetch('https://poster.example/p.jpg');
        } finally {
            self::assertSame(['https://poster.example/p.jpg'], $this->requestedUrls());
        }
    }

    public function testARedirectLoopTerminates(): void
    {
        $fetcher = $this->fetcher(array_fill(0, 10, new Response(302, ['Location' => 'https://poster.example/p.jpg'])));

        $this->expectException(UploadException::class);

        try {
            $fetcher->fetch('https://poster.example/p.jpg');
        } finally {
            self::assertLessThanOrEqual(6, count($this->requestedUrls()), 'Redirects must be bounded.');
        }
    }

    public function testARedirectToANonStandardPortIsRefused(): void
    {
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => 'https://poster.example:8443/p.jpg']),
            new Response(200, [], 'should-never-be-served'),
        ]);

        $this->expectException(UploadException::class);

        try {
            $fetcher->fetch('https://poster.example/p.jpg');
        } finally {
            self::assertSame(['https://poster.example/p.jpg'], $this->requestedUrls());
        }
    }

    public function testARedirectWithoutALocationFails(): void
    {
        $fetcher = $this->fetcher([new Response(302)]);

        $this->expectException(UploadException::class);
        $fetcher->fetch('https://poster.example/p.jpg');
    }

    public function testAnEmptyBodyIsRejected(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '')]);

        $this->expectException(UploadException::class);
        $fetcher->fetch('https://poster.example/p.jpg');
    }

    public function testAnOversizedBodyIsRejected(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], str_repeat('x', 5_000_001))]);

        $this->expectException(UploadException::class);
        $this->expectExceptionMessageMatches('/too large/');
        $fetcher->fetch('https://poster.example/p.jpg');
    }

    public function testATransportFailureIsReportedAsAFetchFailure(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('down', new Request('GET', 'https://poster.example/p.jpg')),
        ]));
        $policy = new PublicAddressPolicy(static fn (string $host): array => [self::PUBLIC_IP]);
        $fetcher = new PosterUrlFetcher(new Client(['handler' => $stack]), $policy, 5_000_000);

        $this->expectException(UploadException::class);
        $this->expectExceptionMessageMatches('/could not be downloaded/');
        $fetcher->fetch('https://poster.example/p.jpg');
    }

    public function testAMalformedUrlIsRejectedBeforeAnyRequest(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], 'image-bytes')]);

        $this->expectException(UploadException::class);

        try {
            $fetcher->fetch('not a url');
        } finally {
            self::assertSame([], $this->requestedUrls());
        }
    }

    /**
     * Applying a Find Posters candidate goes through this same path — the
     * browser posts a found candidate and a pasted address to one endpoint. A
     * false positive here would break the poster search, not just manual entry.
     */
    public function testATypicalPosterSearchCandidateIsFetched(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], 'image-bytes')]);

        self::assertSame(
            'image-bytes',
            $fetcher->fetch('https://image.tmdb.org/t/p/original/abc123.jpg'),
        );
    }
}
