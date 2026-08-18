## 1. The address policy

- [x] 1.1 Add `src/Poster/Edit/PublicAddressPolicy.php` — decides whether a URL
      may be fetched. Pure and injectable: it takes a resolver callable so tests
      never touch real DNS.
- [x] 1.2 Implement the URL-shape rules: `http`/`https` only, host present, port
      80 or 443 (a scheme's default port counts as specified), no userinfo
      component.
- [x] 1.3 Implement the address rule as "must be global unicast", not as a
      blocklist. `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE
      | FILTER_FLAG_NO_RES_RANGE)` is the base. **Measured on this PHP 8.4, it
      already rejects** `127.0.0.1`, `10.0.0.5`, `192.168.1.1`, `172.16.0.1`,
      `169.254.169.254`, `0.0.0.0`, `::1`, `fc00::1`, `fe80::1`, `::`, and
      `::ffff:127.0.0.1`. **It lets exactly two through:** `100.64.0.0/10`
      (carrier-grade NAT) and `224.0.0.0/4` (multicast). Add an explicit check
      for those two and nothing else — and pin the whole table in a test, so a
      PHP upgrade that changes the filter's behavior fails loudly rather than
      silently widening what is reachable.
- [x] 1.4 Resolve the host to **every** address (A and AAAA) and require all of
      them to pass. A literal IP resolves to itself and takes the same path.
- [x] 1.5 Fail closed: a host that cannot be resolved is refused, not fetched.

## 2. The guarded fetch

- [x] 2.1 Add `UploadException::blockedAddress()` with a message naming the
      reason — the address is not a public one — distinct from `fetchFailed()`.
- [x] 2.2 Add a fetcher that takes the policy and a Guzzle client, sets
      `allow_redirects => false`, and follows redirects in a bounded loop of at
      most 5, running the full policy on every hop before requesting it.
- [x] 2.3 Resolve a relative `Location` against the URI of the hop that returned
      it, then apply the policy to the resolved absolute URL.
- [x] 2.4 Keep the existing size and emptiness checks, and keep the existing
      timeouts (20s request, 10s connect).
- [x] 2.5 Rewrite `ChangePosterService::fetchUrl()` to delegate to the fetcher.
      No other method changes; `changeFromPlexPath` and `fetchFromPlex` talk to
      Plex and are deliberately unaffected.

## 3. Wiring

- [x] 3.1 Register the guarded client in `src/bootstrap.php` as its own binding.
      **Do not modify the shared `ClientInterface` registration** — `PlexClient`,
      `PosterSource`, `PlexPinClient`, `PlexServerOwner`, and the update check all
      depend on it reaching private addresses. **Simplified while implementing:**
      no second client is registered. Because `PosterUrlFetcher` checks the
      address before every request it issues and follows redirects itself, the
      guarantee is a property of the fetcher, not of the client — a dedicated
      client would have been a second thing to keep configured without adding a
      guard. `PosterUrlFetcher` is registered instead, over the shared client.
      The shared registration is untouched, as required.
- [x] 3.2 Inject it into `ChangePosterService` in place of the shared client.
- [x] 3.3 Record in `ChangePosterService`'s docblock that its client is the
      restricted one and why, so the next person to add a constructor argument
      does not reach for the shared binding.

## 4. Tests

- [x] 4.1 `PublicAddressPolicy` unit tests: for each of loopback, private v4,
      link-local (`169.254.169.254` by name), CGNAT, unique-local, IPv6 loopback,
      IPv4-mapped IPv6 loopback, multicast, and unspecified — refused. A public
      v4 and a public v6 — allowed.
- [x] 4.2 A host resolving to two addresses, one public and one private, is
      refused.
- [x] 4.3 Port tests: 80 allowed, 443 allowed, 8443 refused, and the scheme
      defaults allowed when no port is given.
- [x] 4.4 A host that fails to resolve is refused.
- [x] 4.5 Fetcher tests with `MockHandler`: a 302 to a private address is not
      followed and raises `blockedAddress`; a 302 to a public address is
      followed; a relative `Location` is resolved and checked; a redirect loop
      terminates at the cap.
- [x] 4.6 `ChangePosterServiceTest` — the existing URL tests still pass with the
      policy in place (they use `https://example.com/...`, so the resolver stub
      must return a public address for them).
- [x] 4.9 **Find Posters shares this path.** `gallery.js` posts a found candidate
      and a pasted address to the same `change/url` endpoint, so the policy
      governs applying a search result too. Cover a typical candidate URL — a
      public CDN host over https on 443 — and assert it is fetched normally. A
      false positive here breaks Find Posters, not just manual URL entry.
- [x] 4.7 A test asserting `ChangePosterService` receives the guarded client and
      not the shared one, so the wiring cannot silently regress. Realised as
      `PosterFetchWiringTest`: structurally, that the constructor takes a
      `PosterUrlFetcher` and **no** `ClientInterface` at all; and behaviourally,
      that the container's own fetcher refuses `127.0.0.1`, `192.168.1.1`, and
      the metadata address.
- [x] 4.8 A test asserting the Plex client is *not* restricted — a private
      `PLEX_SERVER_URL` still works end to end. Covered in `PosterFetchWiringTest`
      plus the existing suite, which runs against `http://plex:32400` throughout;
      982 tests pass unchanged, which is the real assertion that Plex was not
      caught by this.
- [x] 4.10 **Mutation-checked the guards.** Forcing `permits()` to return `true`
      turns 38 of the 60 new assertions red; restoring it returns all 60 green. A
      guard test that cannot fail is worth nothing, so this was verified rather
      than assumed.

## 5. Docs

- [x] 5.1 README "Security considerations" — one bullet stating that a poster URL
      is fetched only from public internet addresses, checked on every redirect,
      and that this does not affect reaching your own Plex server. This is a
      user-visible guarantee. Also names Find Posters, since it travels the same
      path and a reader would not otherwise know the guarantee covers it.
- [x] 5.2 Check whether the change-poster UI copy anywhere promises that any URL
      works, and reconcile if so. It does not: the field is labelled "Image URL"
      with a `https://…` placeholder and claims nothing about which addresses are
      allowed. No change made.
- [x] 5.3 State explicitly if nothing else in `README.md` / `docs/` is affected,
      rather than inventing edits. Nothing else is. `docs/configuration.md` adds
      no setting because this change deliberately adds none;
      `docs/development-workflow.md` and `docs/docker.md` describe the toolchain
      and the image, neither of which moved. The Features list already says
      "paste an image URL", which stays true.

## 6. Verify

- [x] 6.1 `composer test`, `composer stan`, `composer cs`.
- [x] 6.2 `openspec validate block-private-network-poster-fetch --strict`.
- [ ] 6.3 Manually confirm the refusal path on a running instance: paste
      `http://127.0.0.1/` and a LAN address into the From URL field, and confirm
      each is refused with the address message and the poster is untouched.
- [ ] 6.4 Confirm a real public poster URL still applies end to end, including
      the push to Plex.
