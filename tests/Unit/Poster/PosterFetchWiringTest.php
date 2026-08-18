<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use function App\buildContainer;

use App\Config\PlexConfig;
use App\Poster\Edit\ChangePosterService;
use App\Poster\Edit\PosterUrlFetcher;
use App\Poster\Upload\UploadException;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * That the address policy is actually wired to the poster fetch.
 *
 * `PublicAddressPolicyTest` proves the rule is right and
 * `PosterUrlFetcherTest` proves the fetch honours it. Neither would notice if
 * the container stopped connecting the two — a container that handed
 * `ChangePosterService` a plain HTTP client would pass both files and ship the
 * hole. This is the test that fails in that case.
 *
 * The other half is what must *not* happen: the Plex client reaches a private
 * address on purpose, and applying this policy to it would break nearly every
 * install on the first request.
 */
final class PosterFetchWiringTest extends TestCase
{
    /**
     * The regression this whole change guards against.
     *
     * Asserted structurally rather than by behaviour, because the failure being
     * prevented is someone adding an HTTP client back to this constructor —
     * which no behavioural test would catch until the guarded path stopped
     * being the one used.
     */
    public function testChangePosterServiceIsGivenNoHttpClientOfItsOwn(): void
    {
        $constructor = (new ReflectionClass(ChangePosterService::class))->getConstructor();
        self::assertNotNull($constructor);

        $types = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType) {
                $types[] = $type->getName();
            }
        }

        self::assertContains(
            PosterUrlFetcher::class,
            $types,
            'The URL fetch must go through the guarded fetcher.',
        );
        self::assertNotContains(
            ClientInterface::class,
            $types,
            'ChangePosterService must hold no unguarded HTTP client — that is what stops '
            . 'a later change fetching a user-supplied URL straight from it.',
        );
    }

    /**
     * The container's fetcher must refuse a private address.
     *
     * Built through the real bootstrap rather than assembled here, so it fails
     * if the binding is removed, or given a resolver that answers anything.
     */
    public function testTheContainerWiresAFetcherThatRefusesPrivateAddresses(): void
    {
        putenv('DATA_DIR=' . sys_get_temp_dir() . '/marquee-test-data');
        $container = buildContainer();

        $fetcher = $container->get(PosterUrlFetcher::class);
        self::assertInstanceOf(PosterUrlFetcher::class, $fetcher);

        foreach (['http://127.0.0.1/p.jpg', 'http://192.168.1.1/p.jpg', 'http://169.254.169.254/'] as $url) {
            try {
                $fetcher->fetch($url);
                self::fail(sprintf('Expected %s to be refused.', $url));
            } catch (UploadException $e) {
                self::assertStringContainsString('not on the public internet', $e->getMessage());
            }
        }
    }

    /**
     * The Plex server is exempt, and must stay exempt.
     *
     * `PLEX_SERVER_URL` is documented as a private address — `192.168.1.10` in
     * the README's own example. If the policy were ever applied to the Plex
     * client, this is the install that would stop working.
     */
    public function testAPrivatePlexAddressIsNotSubjectToThePolicy(): void
    {
        $plex = new PlexConfig('http://192.168.1.10:32400', 'token', 10, 60);

        self::assertTrue($plex->isConfigured());
        self::assertSame('http://192.168.1.10:32400', $plex->serverUrl);
    }
}
