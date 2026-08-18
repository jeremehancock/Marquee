## Context

`ChangePosterService::fetchUrl()` validates a user-supplied URL with
`FILTER_VALIDATE_URL` and a `^https?://` check, then hands it to Guzzle. Nothing
constrains where it points, so the request goes wherever the address says —
including addresses only reachable from inside the network Marquee runs on.

This was found and deferred repeatedly during the settings-in-app migration,
where it was recorded as out of scope. It is genuinely unrelated to that work,
which is why it kept being pushed; retiring the plan file is what finally made
it homeless.

**Threat model, stated plainly so the design is proportionate.** Marquee is
single-user and owner-only. The person who can reach this form is the person who
already owns the network. So this is not protecting the owner from themselves —
it is defense in depth for after a compromise: a stolen session cookie, a
cross-site request that gets through, or any future bug that lets an attacker
drive an authenticated action. In that world the poster fetch is the most useful
thing on the box, because it turns a foothold into an internal network scanner
that reports back through ordinary-looking error messages.

Judged as "what does an attacker gain", the win is real and the cost is small.
Judged as "does the owner notice", nothing changes: a poster URL is an internet
address, and it stays one.

**Find Posters uses this path too.** `gallery.js` sends a found candidate and a
pasted address to the same `change/url` endpoint — "both a URL for the server to
fetch". So this policy governs applying a search result, not only manual URL
entry, and a false positive breaks a headline feature. Candidate URLs point at
the TMDB, fanart.tv, and TheTVDB CDNs: public hosts over https on 443, which pass
cleanly. They are deliberately *not* exempted — they arrive from an external
service, and "it came from our own API" is not a trust boundary worth crossing
when the check costs them nothing.

**The constraint that shapes everything.** `src/bootstrap.php:120` registers one
shared `ClientInterface`, used by `HttpPlexClient`, `PosteriaApiPosterSource`,
`PlexPinClient`, `PlexServerOwner`, and the update check. **`PLEX_SERVER_URL` is
normally a private address** — `http://192.168.1.10:32400` is the documented
example. A blanket private-network block on that client would break the product
for essentially every install on the first request.

So the rule cannot be global. It applies to one code path: the URL a user typed
into the change-poster form.

## Goals / Non-Goals

**Goals:**

- A poster fetch reaches public internet addresses only, verified before the
  connection is made.
- Every redirect hop is verified, not just the address submitted.
- No configuration. No setting, no environment variable, no way to switch it off
  — an install that can turn the guard off is an install where the guard is not a
  guarantee.
- The owner who pastes a LAN address gets told why, not a generic failure.

**Non-Goals:**

- **DNS rebinding is not solved.** See the decision below; it is accepted and
  documented rather than half-mitigated.
- Not touching `PlexClient`, `PosterSource`, the Plex sign-in clients, or the
  update check. Their destinations are configured by the operator, not typed
  into a form by whoever holds a session.
- Not adding an allowlist of image hosts. The feature's value is that any poster
  URL works.
- Not fixing SSRF-shaped issues that do not exist here — there is no other place
  a user-supplied URL is fetched.

## Decisions

### The policy attaches to a fetcher, not to an HTTP client

**Revised while implementing.** The original decision was a dedicated,
policy-configured `ClientInterface` for `ChangePosterService`, with the shared
client left alone. Building it showed the dedicated client earns nothing.

The guarantee is a property of `PosterUrlFetcher`: it checks the address before
every request it issues, and passes `allow_redirects => false` so the client is
never asked to follow anything unchecked. A second client would have been a
second thing to keep configured — timeouts, redirect policy, future options —
without adding a guard, and a misconfiguration of it would be silent.

What actually keeps the two apart is stronger than a second binding:
**`ChangePosterService` is given a `PosterUrlFetcher` and no HTTP client at
all.** There is no unguarded client in the class to reach for by mistake, which
was the risk the dedicated client was meant to address. The shared
`ClientInterface` registration is untouched, as it must be — `PlexClient` and
the rest still need to reach private addresses.

The alternative considered and rejected remains rejected: one guarded client with
an opt-out for Plex inverts the safety default, since every future caller would
inherit the restriction and the exemption would be the dangerous thing to
forget.

### Redirects are followed explicitly, not by Guzzle

Set `allow_redirects => false` and follow redirects in a bounded loop (cap 5),
validating each `Location` before requesting it.

Guzzle's `on_redirect` callback and a stack middleware both work in principle,
but both depend on middleware ordering and on `on_redirect` not being invoked
for the initial request — subtleties that are easy to get wrong and hard to see
in review. An explicit loop makes "every hop is checked" a property you can read
off the code, and makes it directly testable with `MockHandler` by queueing a
302 to a blocked address.

Trade-off accepted: hand-rolled redirect following is a classic source of bugs
(relative `Location` headers, protocol downgrade, redirect loops). The loop must
resolve relative locations against the current URI, re-run the full policy on the
result, and stop at the cap.

### Blocked ranges are denied by default, not by blocklist

The check is: resolve the host, and require every resulting address to be a
global unicast address. Everything else is refused — loopback, private, link-local
(which is what covers `169.254.169.254`), unique-local, carrier-grade NAT,
multicast, reserved, unspecified, and IPv4-mapped or IPv4-compatible IPv6 forms
of any of them.

Stated as "must be public" rather than "must not be one of these", so a range
nobody thought of fails closed. A literal IP in the URL goes through the same
check as a resolved name, because `gethostbyname`-style resolution of a literal
returns the literal.

**Every** resolved address must pass, not the first. A name resolving to one
public and one private address is exactly the shape of an attack, and picking the
first is a coin flip.

### Ports are restricted to 80 and 443

Confirmed with the maintainer rather than assumed, because it is the only
decision here a user could notice.

**The port rule is what makes accepting DNS rebinding tolerable.** The address
rule is the primary control, but it is the one with a known gap. If rebinding
ever defeats it, the port rule means the attacker reaches port 80 or 443 on an
internal host — not Redis on 6379, an admin panel on 8080, or Elasticsearch on
9200, which is where the interesting internal targets actually live. The two
decisions are load-bearing together; dropping the port rule silently widens the
rebinding gap from "a web page on an internal host" to "any internal service".

The cost is smaller than it first appears. An image server on a non-standard port
is almost always self-hosted, which puts it on the LAN, which the address rule
already refuses — so the port rule's *marginal* cost is public hosts on
non-standard ports, which poster URLs essentially never are. CDNs use 443.

A URL scheme's default port counts as specified for this purpose. If this ever
bites a real user, this is the paragraph to revisit.

### A refused address gets its own message

`UploadException::blockedAddress()`, distinct from `fetchFailed()`.

This is deliberately an oracle: it tells the caller "that address is internal"
rather than "that did not work". In a multi-tenant application that would be a
finding in its own right. Here the only user is the owner, who already knows what
is on their LAN, and the cost of hiding it is an owner who pastes their NAS
address and gets an unexplained failure with nothing to act on.

The distinction is only in the message shown to a signed-in user. Nothing is
logged that would not already be logged.

### DNS rebinding is accepted, not mitigated

The policy resolves the host, validates the addresses, then Guzzle resolves again
when connecting. A name that returns a public address to the first lookup and a
private one to the second defeats the check.

Closing this means pinning the validated address into the connection
(`CURLOPT_RESOLVE` via Guzzle's `curl` options), which couples the fetch to the
curl handler, has to be recomputed for every redirect hop, and breaks TLS
verification unless the original host header is preserved carefully.

That is disproportionate here. Rebinding requires the attacker to control DNS for
a name they get the owner's compromised session to fetch — by which point they
have easier options — and the common cases this change exists to stop
(`127.0.0.1`, `192.168.x.x`, `169.254.169.254`) involve no DNS at all. Recorded
in the spec as a known limitation so that a later reader does not mistake the
guard for something stronger than it is.

## Risks / Trade-offs

- **A legitimate poster URL on a non-standard port is refused.** → Accepted and
  documented in the decision above. The refusal message names the reason, so it
  is diagnosable rather than mysterious.
- **A future caller fetches a user-supplied URL through the unguarded shared
  client.** → The likeliest way this regresses. Mitigated by the docblock, the
  spec requirement, and a test asserting `ChangePosterService` is constructed
  with the guarded client rather than the shared one.
- **Someone "simplifies" this by applying the policy to the shared client.** →
  Breaks every install whose Plex server is on a LAN, which is nearly all of
  them. The reason is recorded in the spec requirement itself, not only here,
  because spec text is what survives into `openspec/specs/`.
- **Hand-rolled redirect following introduces a bug.** → Bounded loop, tested
  against relative locations, a redirect to a blocked host, and a redirect loop.
- **Resolution adds latency to a fetch.** → One extra lookup against a 10-second
  connect timeout. Not material.
