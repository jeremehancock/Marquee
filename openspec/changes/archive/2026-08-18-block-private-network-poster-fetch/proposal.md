## Why

Changing a poster "from URL" makes Marquee fetch whatever address the user
typed. Today it checks only that the address parses and starts with `http`, so
any address is fetched — including `http://192.168.1.1/`,
`http://127.0.0.1:9090/`, and cloud metadata endpoints.

For the owner this is harmless: they can already reach their own network. It
matters after something goes wrong. Marquee sits on a home LAN with a
long-lived session cookie, and a stolen session or a hostile page that gets a
request through turns "fetch this poster" into "probe the network Marquee is on"
— from inside, past the firewall, using a feature that is meant to download an
image from the internet.

The user-visible behavior of the feature does not change. A poster URL is an
internet address; the fix is to make Marquee treat it as one.

## What Changes

- **Fetching a poster URL is restricted to public internet addresses.** Every
  address the host resolves to is checked before a connection is made, and a
  fetch is refused if any of them is loopback, private, link-local (including
  the cloud metadata address), carrier-grade NAT, multicast, or otherwise
  reserved — in IPv4 and IPv6, including IPv4-mapped IPv6 forms.
- **Redirects are checked at every hop**, not only for the address the user
  typed. A permissive check followed by `Location: http://127.0.0.1/` would be
  no check at all.
- **The fetch is restricted to the standard web ports** (80 and 443), which
  removes port-scanning as a capability rather than merely narrowing the hosts
  it can reach.
- **The refusal is its own message**, distinct from "could not be downloaded",
  so an owner who pastes a LAN address is told why it will not work instead of
  being left to guess.
- **Only the poster URL path is restricted.** This is the constraint that shapes
  the change: Marquee's Plex server is normally *at* a private address, and
  reaching it is the whole product. See `design.md`.

Not in scope: DNS rebinding, which is deliberately accepted and documented
rather than half-solved.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-editing`: the requirement covering what makes a replacement image
  acceptable gains the address rule. Where the spec says a URL must be a valid
  `http`/`https` address, it must now also be one that resolves outside the
  private network, at every redirect hop — with the reason recorded, because the
  obvious "simplification" later is to apply the same rule to Marquee's Plex
  client and break every normal install.

## Impact

- `src/Poster/Edit/ChangePosterService.php` — `fetchUrl()` is the only entry
  point affected.
- A new address-policy class plus the HTTP wiring that enforces it on redirects.
  The shared Guzzle client in `src/bootstrap.php` is used by `PlexClient`,
  `PosterSource`, the Plex sign-in clients, and the update check, none of which
  may inherit this restriction — so the poster fetch gets its own configured
  client rather than the policy being applied globally.
- `src/Poster/Upload/UploadException.php` — one new refusal message.
- README "Security considerations" gains a line; this is a user-visible
  guarantee, not an internal detail.
- No settings, no new environment variable, nothing for an install to configure.
