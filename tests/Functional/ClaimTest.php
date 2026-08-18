<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Auth\Claim\ClaimAttempts;
use App\Auth\Claim\ClaimCode;
use App\Auth\Claim\ClaimService;
use App\Plex\Connection\PlexConnectionStore;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;
use App\Tests\AppTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use Slim\App;

/**
 * The claim: what stops the first stranger to reach an unconfigured install from
 * becoming its owner.
 *
 * This is the security-relevant surface of the whole four-phase migration. Until
 * this existed, the property was supplied by `PLEX_SERVER_URL` coming from the
 * environment — an assertion only someone with host access could make. Ownership
 * is verified against the configured server, so once the address is typed into a
 * browser it proves nothing on its own.
 */
final class ClaimTest extends AppTestCase
{
    private string $dataDir = '';

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/marquee-claim-' . bin2hex(random_bytes(6));
        mkdir($this->dataDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dataDir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->dataDir);
    }

    /**
     * A Plex server that answers `/identity` the way a real one does, so the
     * probe in front of the claim is satisfied.
     *
     * @return array<string, mixed>
     */
    private function reachableServer(): array
    {
        $identity = '<MediaContainer size="0" machineIdentifier="abc123" version="1.41.0.1234"/>';

        return [
            ClientInterface::class => static fn (): ClientInterface => new Client([
                'handler' => HandlerStack::create(new MockHandler(array_fill(
                    0,
                    20,
                    new Response(200, ['Content-Type' => 'application/xml'], $identity),
                ))),
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function unclaimed(array $overrides = []): App
    {
        return $this->makeUnclaimedApp(['DATA_DIR' => $this->dataDir], $overrides);
    }

    private function codeOnDisk(): ?string
    {
        $path = $this->dataDir . '/' . ClaimService::FILENAME;

        return is_file($path) ? trim((string) file_get_contents($path)) : null;
    }

    // ---- The code ----

    public function testAFreshInstallGeneratesACodeOwnerReadableOnly(): void
    {
        $this->unclaimed();

        $code = $this->codeOnDisk();
        self::assertNotNull($code);
        self::assertNotSame('', $code);

        $path = $this->dataDir . '/' . ClaimService::FILENAME;
        self::assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
    }

    public function testTheCodeCarriesRealEntropy(): void
    {
        // Two installs must not produce the same code. A fixed or predictable
        // code would make every one of these tests pass and the control useless.
        $first = ClaimCode::generate();
        $second = ClaimCode::generate();

        self::assertNotSame($first, $second);
        self::assertGreaterThanOrEqual(26, strlen(ClaimCode::normalize($first)));
    }

    public function testACodeIsAcceptedRegardlessOfCaseSpacingOrHyphens(): void
    {
        $code = ClaimCode::generate();

        self::assertTrue(ClaimCode::matches(strtolower($code), $code));
        self::assertTrue(ClaimCode::matches(str_replace('-', ' ', $code), $code));
        self::assertTrue(ClaimCode::matches($code . "\n", $code));
        self::assertFalse(ClaimCode::matches('', $code));
        self::assertFalse(ClaimCode::matches($code, ''));
    }

    // ---- The gate ----

    public function testAnUnclaimedInstallSendsTheGalleryToTheClaimScreen(): void
    {
        $response = $this->get($this->unclaimed(), '/');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/claim', $response->getHeaderLine('Location'));
    }

    /**
     * The one that matters most. `/login` is exempt from the connection gate,
     * because signing in is how that gate's precondition is met — so a claim
     * check living inside authentication would never see this request, and a
     * stranger could sign in against a server they named themselves.
     */
    public function testAnUnclaimedInstallSendsTheSignInScreenToTheClaimScreen(): void
    {
        $response = $this->get($this->unclaimed(), '/login');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/claim', $response->getHeaderLine('Location'));
    }

    public function testAnUnclaimedInstallRefusesToStartASignIn(): void
    {
        $app = $this->unclaimed();

        $response = $this->postFormWithoutToken($app, '/plex/connection/sign-in', []);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/claim', $response->getHeaderLine('Location'));
    }

    public function testHealthAndTheWallSurviveAnUnclaimedInstall(): void
    {
        $app = $this->unclaimed();

        // A container reported unhealthy before anyone has claimed it would
        // restart-loop in an orchestrator.
        self::assertSame(200, $this->get($app, '/health')->getStatusCode());
        // The wall is specified to run unattended. It has no posters to show on
        // an unclaimed install, because posters arrive only through an import.
        self::assertSame(200, $this->get($app, '/wall')->getStatusCode());
    }

    public function testTheClaimScreenItselfIsReachable(): void
    {
        $response = $this->get($this->unclaimed(), '/claim');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Claim code', (string) $response->getBody());
    }

    public function testNoResponseDisclosesTheCode(): void
    {
        $app = $this->unclaimed();
        $code = $this->codeOnDisk();
        self::assertNotNull($code);

        $screen = (string) $this->get($app, '/claim')->getBody();
        self::assertStringNotContainsString($code, $screen);

        $refused = (string) $this->postForm($app, '/claim', [
            'code' => 'WRONG-CODE-HERE',
            'server_url' => 'http://plex:32400',
        ])->getBody();
        self::assertStringNotContainsString($code, $refused);
    }

    // ---- Claiming ----

    public function testTheRightCodeClaimsTheInstall(): void
    {
        $app = $this->unclaimed($this->reachableServer());
        $code = $this->codeOnDisk();
        self::assertNotNull($code);

        $response = $this->postForm($app, '/claim', [
            'code' => $code,
            'server_url' => 'http://plex:32400',
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/connect', $response->getHeaderLine('Location'));
        self::assertTrue((new PlexConnectionStore($this->dataDir))->isClaimed());
        // The address the claim named is the one every later request uses.
        self::assertSame(
            'http://plex:32400',
            (new SettingsStore($this->dataDir))->string(SettingKey::PlexServerUrl),
        );
    }

    public function testClaimingDeletesTheCodeAndTheGateDoesNotReopen(): void
    {
        $app = $this->unclaimed($this->reachableServer());
        $code = $this->codeOnDisk();
        self::assertNotNull($code);

        $this->postForm($app, '/claim', ['code' => $code, 'server_url' => 'http://plex:32400']);

        self::assertNull($this->codeOnDisk());
        // The same code again claims nothing, because there is nothing left to
        // claim and no code to match against.
        self::assertSame(302, $this->get($app, '/claim')->getStatusCode());
    }

    public function testAWrongCodeClaimsNothing(): void
    {
        $app = $this->unclaimed($this->reachableServer());

        $response = $this->postForm($app, '/claim', [
            'code' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZ',
            'server_url' => 'http://plex:32400',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('not correct', (string) $response->getBody());
        self::assertFalse((new PlexConnectionStore($this->dataDir))->isClaimed());
        self::assertNotNull($this->codeOnDisk());
    }

    /**
     * The address is probed before the code is checked, so a correct code is
     * never spent on an address that was going to fail — it is single-use, and
     * burning it on a typo means fetching the file again.
     */
    public function testAnUnreachableAddressIsRefusedWithoutSpendingTheCode(): void
    {
        // The transport failure a wrong host or port actually produces.
        $app = $this->unclaimed([
            ClientInterface::class => static fn (): ClientInterface => new Client([
                'handler' => HandlerStack::create(new MockHandler(array_fill(
                    0,
                    5,
                    new ConnectException('Connection refused', new Request('GET', '/identity')),
                ))),
            ]),
        ]);
        $code = $this->codeOnDisk();
        self::assertNotNull($code);

        $response = $this->postForm($app, '/claim', [
            'code' => $code,
            'server_url' => 'http://nothing-here:32400',
        ]);

        self::assertStringContainsString('No Plex server answered', (string) $response->getBody());
        self::assertFalse((new PlexConnectionStore($this->dataDir))->isClaimed());
        self::assertSame($code, $this->codeOnDisk());
    }

    public function testRepeatedWrongCodesTriggerTheCoolingOff(): void
    {
        $app = $this->unclaimed($this->reachableServer());
        $code = $this->codeOnDisk();
        self::assertNotNull($code);

        for ($i = 0; $i < ClaimAttempts::LIMIT; ++$i) {
            $this->postForm($app, '/claim', [
                'code' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZ',
                'server_url' => 'http://plex:32400',
            ]);
        }

        // Even the correct code is refused while cooling off.
        $response = $this->postForm($app, '/claim', ['code' => $code, 'server_url' => 'http://plex:32400']);

        self::assertStringContainsString('Too many incorrect codes', (string) $response->getBody());
        self::assertFalse((new PlexConnectionStore($this->dataDir))->isClaimed());
    }

    public function testTheCoolingOffLifts(): void
    {
        $attempts = new ClaimAttempts($this->dataDir);
        $now = 1_700_000_000;

        for ($i = 0; $i < ClaimAttempts::LIMIT; ++$i) {
            $attempts->recordFailure($now);
        }
        self::assertTrue($attempts->isCoolingOff($now));

        self::assertFalse($attempts->isCoolingOff($now + ClaimAttempts::COOLING_OFF_SECONDS));
    }

    // ---- Surviving what must not clear it ----

    /**
     * The trap this whole phase turns on. `clearToken()` deliberately forgets
     * the owner so ownership is re-proven on the next sign-in; if it also forgot
     * the claim, disconnecting a publicly reachable install would reopen it to
     * the first stranger who loaded it.
     */
    public function testDisconnectingLeavesTheInstallClaimed(): void
    {
        $store = new PlexConnectionStore($this->dataDir);
        $store->markClaimed();
        $store->storeToken('a-token');
        $store->storeOwner('someone');

        $store->clearToken();

        self::assertNull($store->token());
        self::assertNull($store->owner());
        self::assertTrue($store->isClaimed(), 'clearToken() must never un-claim an install');
    }

    public function testTheClaimIsNotStoredWhereASettingsSaveOrAResetCouldClearIt(): void
    {
        $store = new PlexConnectionStore($this->dataDir);
        $store->markClaimed();

        // Not in the settings store, which the settings screen writes.
        $settings = new SettingsStore($this->dataDir);
        $settings->set(SettingKey::SiteTitle, 'Anything');
        self::assertTrue($store->isClaimed());

        // Not in the database, which is specified as a deletable cache.
        @unlink($this->dataDir . '/marquee.sqlite');
        self::assertTrue((new PlexConnectionStore($this->dataDir))->isClaimed());
    }

    public function testTheClaimIsNotRestamped(): void
    {
        $store = new PlexConnectionStore($this->dataDir);
        $store->markClaimed();
        $first = $store->claimedAt();

        $store->markClaimed();

        // When it happened is the only evidence available if an install turns
        // out to have been claimed by someone unexpected.
        self::assertSame($first, $store->claimedAt());
    }

    // ---- Upgrading ----

    /**
     * An existing install must never be shown a wizard demanding a code it has
     * never seen. A stored token means somebody already proved ownership.
     */
    public function testAnInstallWithAStoredTokenIsClaimedWithoutACode(): void
    {
        (new PlexConnectionStore($this->dataDir))->storeToken('an-existing-token');

        $this->claimService()->ensureCode();

        self::assertTrue((new PlexConnectionStore($this->dataDir))->isClaimed());
        self::assertNull($this->codeOnDisk());
    }

    /**
     * So does a compose file carrying a server address: choosing it required
     * host access, which is the same assertion the claim code makes.
     */
    public function testAnInstallSeededWithAServerAddressIsClaimedWithoutACode(): void
    {
        $settings = new SettingsStore($this->dataDir);
        $settings->set(SettingKey::PlexServerUrl, 'http://plex:32400');

        $this->claimService()->ensureCode();

        self::assertTrue((new PlexConnectionStore($this->dataDir))->isClaimed());
        self::assertNull($this->codeOnDisk());
    }

    /**
     * Built by hand rather than through makeApp(), which deletes the connection
     * store to isolate tests — and the upgrade path is entirely about what that
     * file already contains.
     */
    private function claimService(): ClaimService
    {
        return new ClaimService(
            new PlexConnectionStore($this->dataDir),
            new SettingsStore($this->dataDir),
            new ClaimAttempts($this->dataDir),
            new NullLogger(),
            $this->dataDir,
        );
    }
}
