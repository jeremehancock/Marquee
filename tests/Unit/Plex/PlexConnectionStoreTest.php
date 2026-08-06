<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Plex\Connection\PlexConnectionStore;
use PHPUnit\Framework\TestCase;

final class PlexConnectionStoreTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/marquee-connection-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testNothingIsStoredInitially(): void
    {
        self::assertNull($this->store()->token());
    }

    public function testTokenRoundTripsThroughTheFile(): void
    {
        $this->store()->storeToken('plex-token');

        // A second instance proves the value came off disk, not out of memory.
        self::assertSame('plex-token', $this->store()->token());
    }

    public function testGeneratedValuesAreStableAcrossInstances(): void
    {
        $first = $this->store();
        $clientId = $first->clientIdentifier();
        $secret = $first->signingSecret();

        self::assertNotSame('', $clientId);
        self::assertNotSame('', $secret);

        $second = $this->store();
        self::assertSame($clientId, $second->clientIdentifier());
        self::assertSame($secret, $second->signingSecret());
    }

    public function testClientIdentifierAndSigningSecretDiffer(): void
    {
        $store = $this->store();

        self::assertNotSame($store->clientIdentifier(), $store->signingSecret());
    }

    public function testSigningSecretExistsWithoutAToken(): void
    {
        // The wall signs tokens whether or not Plex is connected, so the secret
        // must never depend on a token being present.
        self::assertNotSame('', $this->store()->signingSecret());
        self::assertNull($this->store()->token());
    }

    public function testClearingTheTokenKeepsTheOtherSecrets(): void
    {
        $store = $this->store();
        $store->storeToken('plex-token');
        $clientId = $store->clientIdentifier();
        $secret = $store->signingSecret();

        $store->clearToken();

        self::assertNull($store->token());
        self::assertSame($clientId, $store->clientIdentifier());
        self::assertSame($secret, $store->signingSecret());

        $reloaded = $this->store();
        self::assertNull($reloaded->token());
        self::assertSame($clientId, $reloaded->clientIdentifier());
        self::assertSame($secret, $reloaded->signingSecret());
    }

    public function testFileIsOwnerOnly(): void
    {
        $this->store()->storeToken('plex-token');

        $mode = fileperms($this->dir . '/plex-connection.json');
        self::assertNotFalse($mode);
        self::assertSame(0o600, $mode & 0o777);
    }

    public function testMalformedFileReadsAsNothingStored(): void
    {
        @mkdir($this->dir, 0o775, true);
        file_put_contents($this->dir . '/plex-connection.json', 'not json at all');

        self::assertNull($this->store()->token());
    }

    public function testUnusableEntriesAreDroppedIndividually(): void
    {
        @mkdir($this->dir, 0o775, true);
        file_put_contents(
            $this->dir . '/plex-connection.json',
            json_encode(['token' => null, 'client_identifier' => 'kept'], JSON_THROW_ON_ERROR),
        );

        $store = $this->store();
        self::assertNull($store->token());
        self::assertSame('kept', $store->clientIdentifier());
    }

    public function testStoringAnEmptyTokenIsIgnored(): void
    {
        $store = $this->store();
        $store->storeToken('real-token');
        $store->storeToken('');

        self::assertSame('real-token', $store->token());
    }

    private function store(): PlexConnectionStore
    {
        return new PlexConnectionStore($this->dir);
    }
}
