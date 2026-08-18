<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Edit\PublicAddressPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The address rule for a URL a user typed.
 *
 * The blocked-range table is pinned deliberately rather than trusted to
 * `filter_var`. Most of it is the filter's behaviour, not this class's, and a
 * PHP release that changed it would otherwise widen what Marquee can be made to
 * reach with nothing failing. Two ranges the filter does *not* cover —
 * carrier-grade NAT and multicast — are this class's own work, and are in the
 * same table so the two cannot drift apart.
 */
final class PublicAddressPolicyTest extends TestCase
{
    /**
     * Resolves every host to one fixed address, so a case is about the address
     * rather than about DNS.
     */
    private static function resolvingTo(string ...$addresses): PublicAddressPolicy
    {
        $resolved = array_values($addresses);

        return new PublicAddressPolicy(static fn (string $host): array => $resolved);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedAddresses(): array
    {
        return [
            'IPv4 loopback' => ['127.0.0.1'],
            'IPv4 loopback, whole range' => ['127.255.255.254'],
            'RFC 1918 ten-dot' => ['10.0.0.5'],
            'RFC 1918 one-nine-two' => ['192.168.1.1'],
            'RFC 1918 one-seven-two' => ['172.16.0.1'],
            'link-local' => ['169.254.1.1'],
            'cloud metadata endpoint' => ['169.254.169.254'],
            'carrier-grade NAT' => ['100.64.0.1'],
            'carrier-grade NAT, top of range' => ['100.127.255.255'],
            'multicast' => ['224.0.0.1'],
            'reserved above multicast' => ['240.0.0.1'],
            'broadcast' => ['255.255.255.255'],
            'unspecified' => ['0.0.0.0'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unspecified' => ['::'],
            'IPv6 unique-local' => ['fc00::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv4-mapped IPv6 loopback' => ['::ffff:127.0.0.1'],
            'IPv4-mapped IPv6 private' => ['::ffff:192.168.1.1'],
        ];
    }

    #[DataProvider('blockedAddresses')]
    public function testAnAddressOutsideThePublicInternetIsRefused(string $address): void
    {
        self::assertFalse(self::resolvingTo($address)->permits('https://poster.example/p.jpg'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicAddresses(): array
    {
        return [
            'public IPv4' => ['8.8.8.8'],
            'public IPv4, another' => ['93.184.216.34'],
            'just above carrier-grade NAT' => ['100.128.0.1'],
            'just below carrier-grade NAT' => ['100.63.255.255'],
            'public IPv6' => ['2606:4700::1111'],
        ];
    }

    #[DataProvider('publicAddresses')]
    public function testAPublicAddressIsPermitted(string $address): void
    {
        self::assertTrue(self::resolvingTo($address)->permits('https://poster.example/p.jpg'));
    }

    /**
     * The case a first-address-only check would get wrong half the time.
     */
    public function testAHostResolvingToBothPublicAndPrivateIsRefused(): void
    {
        self::assertFalse(self::resolvingTo('8.8.8.8', '192.168.1.1')->permits('https://poster.example/p.jpg'));
        self::assertFalse(self::resolvingTo('192.168.1.1', '8.8.8.8')->permits('https://poster.example/p.jpg'));
    }

    public function testAHostThatDoesNotResolveIsRefused(): void
    {
        self::assertFalse(self::resolvingTo()->permits('https://nothing.example/p.jpg'));
    }

    public function testAnAddressLiteralIsCheckedRatherThanResolved(): void
    {
        // The real resolver, which returns a literal unchanged. This is the
        // path an attacker takes: no DNS involved at all.
        $policy = new PublicAddressPolicy(PublicAddressPolicy::systemResolver());

        self::assertFalse($policy->permits('http://127.0.0.1/p.jpg'));
        self::assertFalse($policy->permits('http://169.254.169.254/latest/meta-data/'));
        self::assertFalse($policy->permits('http://[::1]/p.jpg'));
        self::assertTrue($policy->permits('http://8.8.8.8/p.jpg'));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function ports(): array
    {
        return [
            'https default' => ['https://poster.example/p.jpg', true],
            'http default' => ['http://poster.example/p.jpg', true],
            'explicit 443' => ['https://poster.example:443/p.jpg', true],
            'explicit 80' => ['http://poster.example:80/p.jpg', true],
            'alternate https' => ['https://poster.example:8443/p.jpg', false],
            'alternate http' => ['http://poster.example:8080/p.jpg', false],
            'redis' => ['http://poster.example:6379/p.jpg', false],
        ];
    }

    #[DataProvider('ports')]
    public function testOnlyTheStandardWebPortsArePermitted(string $url, bool $permitted): void
    {
        self::assertSame($permitted, self::resolvingTo('8.8.8.8')->permits($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedUrls(): array
    {
        return [
            'not a URL' => ['this is not a url'],
            'no scheme' => ['poster.example/p.jpg'],
            'ftp' => ['ftp://poster.example/p.jpg'],
            'file' => ['file:///etc/passwd'],
            'gopher' => ['gopher://poster.example/'],
            'no host' => ['http:///p.jpg'],
            'credentials in authority' => ['http://user:pass@poster.example/p.jpg'],
            'username only' => ['http://user@poster.example/p.jpg'],
        ];
    }

    #[DataProvider('malformedUrls')]
    public function testAUrlThatIsNotAPlainWebAddressIsRefused(string $url): void
    {
        self::assertFalse(self::resolvingTo('8.8.8.8')->permits($url));
    }

    public function testTheSchemeIsMatchedRegardlessOfCase(): void
    {
        self::assertTrue(self::resolvingTo('8.8.8.8')->permits('HTTPS://poster.example/p.jpg'));
    }
}
