<?php

declare(strict_types=1);

namespace App\Poster\Edit;

use Closure;

/**
 * Decides whether a URL a user typed may be fetched.
 *
 * Marquee sits on a home network with a long-lived session, and changing a
 * poster "from URL" makes the server request whatever address was submitted.
 * Unconstrained, that is a way to reach things only reachable from inside:
 * a router's admin page, a database on localhost, a cloud metadata endpoint.
 * The owner could reach those anyway — this matters after a session is stolen
 * or a request is smuggled through, when the poster fetch is the most useful
 * thing on the box because it scans the network from behind the firewall and
 * reports back through ordinary error messages.
 *
 * **This applies only to an address a user supplied.** It must never be applied
 * to the Plex client: `PLEX_SERVER_URL` is normally a private address, and
 * reaching it is the product working. See {@see PosterUrlFetcher} for the one
 * caller, and `ChangePosterService`'s constructor for the wiring that keeps the
 * two clients apart.
 *
 * The rule is "every resolved address must be public", never a list of
 * forbidden ranges, so that a range nobody anticipated fails closed. *Every*
 * address, not the first: a host answering with one public and one private
 * address is the shape of an attack, and picking among them is a coin flip.
 *
 * **Not solved: DNS rebinding.** The host is resolved here and resolved again
 * by the HTTP client when it connects, so a name that answers differently the
 * second time defeats this. Closing it means pinning the validated address into
 * the connection, which couples the fetch to one handler and has to be redone
 * per redirect hop. It is disproportionate: the cases this exists to stop are
 * literal addresses that involve no resolution at all, and the port rule below
 * keeps the residual gap to a web port on the internal host rather than every
 * internal service.
 */
final class PublicAddressPolicy
{
    /**
     * The only ports a poster may be fetched from.
     *
     * Not merely tidiness. This is what keeps the rebinding gap narrow: if a
     * name ever resolves past the address check, what it reaches is port 80 or
     * 443 on an internal host — not Redis on 6379, an admin panel on 8080, or a
     * search cluster on 9200, which is where the interesting targets are. The
     * two rules are load-bearing together; dropping this one silently widens the
     * other's known gap.
     *
     * The cost is a public image host on a non-standard port, which poster URLs
     * essentially never are. A self-hosted one would be on the LAN, which the
     * address rule already refuses.
     */
    private const ALLOWED_PORTS = [80, 443];

    /**
     * Ranges PHP's filter does not reject, measured rather than assumed.
     *
     * `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` already covers
     * loopback, RFC 1918, link-local (including the metadata address), the
     * unspecified address, IPv6 loopback, unique-local, IPv6 link-local, and
     * IPv4-mapped forms of them. These two it lets through.
     *
     * `PublicAddressPolicyTest` pins the whole table, so a PHP upgrade that
     * changes the filter's behaviour fails loudly rather than quietly widening
     * what is reachable.
     */
    private const CARRIER_GRADE_NAT = ['100.64.0.0', '100.127.255.255'];

    /**
     * @param Closure(string): list<string> $resolver host to every address it
     *                                                resolves to, empty when it
     *                                                does not resolve
     */
    public function __construct(private readonly Closure $resolver)
    {
    }

    /**
     * Resolves a host with the system resolver, both address families.
     *
     * `dns_get_record` rather than `gethostbyname`, which returns only the
     * first A record and nothing for AAAA — and "the first address" is exactly
     * what this policy must not trust.
     */
    public static function systemResolver(): Closure
    {
        return static function (string $host): array {
            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                return [$host];
            }

            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if ($records === false) {
                return [];
            }

            $addresses = [];
            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address) && $address !== '') {
                    $addresses[] = $address;
                }
            }

            return $addresses;
        };
    }

    /**
     * Whether this URL may be fetched.
     *
     * Fails closed on everything: a malformed URL, a host that does not
     * resolve, and a host that resolves to nothing are all refused rather than
     * attempted.
     */
    public function permits(string $url): bool
    {
        $parts = parse_url(trim($url));
        if ($parts === false) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        // Credentials in the authority are refused rather than stripped. They
        // have no legitimate place in a poster URL, and they are how a request
        // gets pointed somewhere other than where the visible host suggests.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            return false;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, self::ALLOWED_PORTS, true)) {
            return false;
        }

        // parse_url keeps the brackets on an IPv6 literal; the filter rejects
        // them, so an unwrapped host would be refused as unresolvable rather
        // than checked as the address it is.
        $addresses = ($this->resolver)(trim($host, '[]'));
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (!self::isPublic($address)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether one address is a global unicast address.
     */
    private static function isPublic(string $address): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (filter_var($address, FILTER_VALIDATE_IP, $flags) === false) {
            return false;
        }

        if (self::isInRange($address, self::CARRIER_GRADE_NAT[0], self::CARRIER_GRADE_NAT[1])) {
            return false;
        }

        // 224.0.0.0/4 — multicast, and 240.0.0.0/4 above it, which the filter's
        // reserved-range flag does not treat as reserved.
        return !self::isInRange($address, '224.0.0.0', '255.255.255.255');
    }

    /**
     * Whether an IPv4 address falls between two others, inclusive.
     *
     * Returns false for anything that is not IPv4, so an IPv6 address is left
     * to the filter above rather than compared against v4 bounds.
     */
    private static function isInRange(string $address, string $first, string $last): bool
    {
        $value = ip2long($address);
        $low = ip2long($first);
        $high = ip2long($last);

        if ($value === false || $low === false || $high === false) {
            return false;
        }

        return $value >= $low && $value <= $high;
    }
}
