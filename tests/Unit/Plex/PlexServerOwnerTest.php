<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Config\PlexConfig;
use App\Plex\Connection\PlexOwnerLookup;
use App\Plex\Connection\PlexServerOwner;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * The classification this class exists for: separating "the server said this
 * account has no access" from "there was no server to ask".
 *
 * Both refuse the sign-in. They differ only in what the user is told to go and
 * fix, which is the entire reason the distinction is drawn.
 */
final class PlexServerOwnerTest extends TestCase
{
    public function testAServerThatNamesItsOwnerIsNamed(): void
    {
        $lookup = $this->lookup(new Response(200, [], '<MediaContainer myPlexUsername="owner@example.com"/>'));

        self::assertFalse($lookup->isUnreachable());
        self::assertSame('owner@example.com', $lookup->owner());
    }

    public function testNothingAnsweringIsUnreachable(): void
    {
        $lookup = $this->lookup(new ConnectException('refused', new Request('GET', 'http://plex:32400/')));

        self::assertTrue($lookup->isUnreachable());
        self::assertNull($lookup->owner());
    }

    /**
     * The address points at something, but not at a Plex server that will talk.
     * What the user has to check is the address either way.
     */
    public function testAnErrorStatusThatIsNotAnAuthorisationRefusalIsUnreachable(): void
    {
        self::assertTrue($this->lookup(new Response(404, [], 'nope'))->isUnreachable());
        self::assertTrue($this->lookup(new Response(500, [], 'boom'))->isUnreachable());
        self::assertTrue($this->lookup(new Response(502, [], ''))->isUnreachable());
    }

    /**
     * A server that answers and declines the token has made a statement about
     * the account, not about the network.
     */
    public function testAnAuthorisationRefusalIsAnOwnershipAnswer(): void
    {
        foreach ([401, 403] as $status) {
            $lookup = $this->lookup(new Response($status, [], ''));

            self::assertFalse($lookup->isUnreachable(), "status {$status}");
            self::assertNull($lookup->owner(), "status {$status}");
        }
    }

    /**
     * A Plex server that answers without naming an owner has said nothing to
     * compare against. It still refuses — but as an ownership answer, because
     * the server was reached and did reply.
     */
    public function testAPlexServerThatNamesNoOwnerIsAnonymous(): void
    {
        $lookup = $this->lookup(new Response(200, [], '<MediaContainer friendlyName="Anansi"/>'));

        self::assertFalse($lookup->isUnreachable());
        self::assertNull($lookup->owner());
    }

    public function testAnEmptyOwnerAttributeIsAnonymous(): void
    {
        $lookup = $this->lookup(new Response(200, [], '<MediaContainer myPlexUsername="   "/>'));

        self::assertFalse($lookup->isUnreachable());
        self::assertNull($lookup->owner());
    }

    /**
     * PLEX_SERVER_URL pointing at some other web application. That is an
     * address problem, and refusing the user's Plex account for it would be the
     * bug this class was changed to remove.
     */
    public function testAResponseThatIsNotPlexIsUnreachable(): void
    {
        self::assertTrue($this->lookup(new Response(200, [], '<html><body>Hi</body></html>'))->isUnreachable());
        self::assertTrue($this->lookup(new Response(200, [], 'not markup at all'))->isUnreachable());
        self::assertTrue($this->lookup(new Response(200, [], ''))->isUnreachable());
    }

    public function testNoConfiguredAddressIsUnreachable(): void
    {
        $owner = new PlexServerOwner(
            new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
            new PlexConfig('', '', 10, 60),
        );

        self::assertTrue($owner->forToken('a-token')->isUnreachable());
    }

    public function testNoTokenIsUnreachable(): void
    {
        $owner = new PlexServerOwner(
            new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
            new PlexConfig('http://plex:32400', '', 10, 60),
        );

        self::assertTrue($owner->forToken('')->isUnreachable());
    }

    public function testTheTokenIsSentToTheServer(): void
    {
        /** @var list<RequestInterface> $sent */
        $sent = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '<MediaContainer myPlexUsername="owner@example.com"/>'),
        ]));
        $stack->push(function (callable $handler) use (&$sent): callable {
            return function (RequestInterface $request, array $options) use ($handler, &$sent) {
                $sent[] = $request;

                return $handler($request, $options);
            };
        });

        $owner = new PlexServerOwner(
            new Client(['handler' => $stack]),
            new PlexConfig('http://plex:32400', '', 10, 60),
        );
        $owner->forToken('a-token');

        self::assertCount(1, $sent);
        self::assertSame('a-token', $sent[0]->getHeaderLine('X-Plex-Token'));
    }

    private function lookup(Response|ConnectException $response): PlexOwnerLookup
    {
        $owner = new PlexServerOwner(
            new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]),
            new PlexConfig('http://plex:32400', '', 10, 60),
        );

        return $owner->forToken('a-token');
    }
}
