## 1. The claim itself

- [x] 1.1 Add a claim code generator: 128 bits from `random_bytes`, rendered as
      Crockford base32 in hyphenated groups, so it can be read off a screen and
      pasted without ambiguity between similar characters
- [x] 1.2 Add `claimed_at` to `PlexConnectionStore`, beside `client_identifier`
      and `signing_secret`, with a reader and a writer
- [x] 1.3 Add a `ClaimService`: is this install claimed, does this code match,
      generate and persist the code on first start, claim the install
- [x] 1.4 Write the code file at `0600` via the store's atomic-rename path, and
      log it once on generation
- [x] 1.5 Claim as one transaction — store the address, write `claimed_at`, then
      delete the code file, in that order, so a half-claimed install cannot exist
      and a lost file cannot lock the install out
- [x] 1.6 Treat an install with a stored token, or with a seeded
      `PLEX_SERVER_URL`, as already claimed on first boot after upgrading — never
      show an existing user a code they have never seen

## 2. Bounding attempts

- [x] 2.1 Add a global attempt counter with a short cooling-off after a threshold
      of failures; global rather than per-IP, because a claim is one global event
      and per-IP is defeated by a proxy or by more than one address
- [x] 2.2 Log every failed attempt, and the cooling-off when it engages
- [x] 2.3 Add the nginx `limit_req` location for the claim route, reusing the
      existing zone pattern
- [x] 2.4 Never disclose the code in any response, including error messages

## 3. Gating an unclaimed install

- [x] 3.1 Send every gated route, and `/login`, to the claim step while unclaimed;
      keep `/health`, the manifest, assets, and the wall reachable
- [x] 3.2 Refuse a sign-in on an unclaimed install regardless of what the
      approving account owns
- [x] 3.3 Assert with a test that `clearToken()` preserves `claimed_at` — it does
      so today by construction, and the cost of a later rewrite is a public
      install silently reopening

## 4. The wizard

- [x] 4.1 Grow `/connect` into the wizard rather than adding a screen: step 1 is
      new, step 2 is the existing sign-in untouched, step 3 is the settings screen
- [x] 4.2 Build step 1: claim code and server URL, with CSRF, reachable without a
      session
- [x] 4.3 Probe the entered address unauthenticated and echo the server's name
      back before committing; word it as the typo-catcher it is, never as
      verification
- [x] 4.4 Send the user to step 2 on a successful claim, and to step 3 once
      connected
- [x] 4.5 Log the owner and server address when the install is first claimed,
      without the code

## 5. The address becomes a setting

- [x] 5.1 Add the Plex server address to `SettingsForm` and the settings screen's
      Plex section, validated the way `PlexConfig` already validates it
- [x] 5.2 Remove the phase-2 test asserting the screen offers no address field and
      replace it with one asserting it does
- [x] 5.3 Confirm `PLEX_SERVER_URL` still seeds a first boot and is still reported
      as relocated afterwards

## 6. Tests

- [x] 6.1 A fresh install generates a code, writes it `0600`, and logs it once
- [x] 6.2 An unclaimed install sends gated routes and `/login` to the claim step;
      health and the wall stay reachable
- [x] 6.3 A sign-in on an unclaimed install creates no session
- [x] 6.4 The correct code claims the install; a wrong one does not and is logged
- [x] 6.5 Claiming deletes the code file, and a second claim is refused
- [x] 6.6 Repeated wrong codes trigger the cooling-off, and it lifts
- [x] 6.7 `clearToken()` preserves `claimed_at`; disconnecting then signing in
      again needs no code
- [x] 6.8 Deleting the database leaves the install claimed
- [x] 6.9 Submitting the settings screen cannot change the claim
- [x] 6.10 An upgrading install with a stored token is claimed without a code
- [x] 6.11 No response discloses the code
- [x] 6.12 The address is stored by the claim and changeable afterwards

## 7. Docker and docs

- [x] 7.1 Update the nginx site config; **build the image locally and smoke-test
      `/health`, plus a real first-run claim from an empty volume**, before pushing
- [x] 7.2 README: the compose example drops to `PUID`, `PGID`, `TZ`, the port, the
      volume, and the restart policy
- [x] 7.3 README: how to find the claim code, in the file and in the log
- [x] 7.4 README: how to reset a claimed install — **including the warning that a
      publicly reachable install must come off the network first**, because
      `/config/posters` survives independently of claim state and the wall is
      exempt from both gates
- [x] 7.5 README: the configuration table now describes first-boot seeding only;
      this is the phase that owns rewriting it
- [x] 7.6 `composer test`, `composer stan`, `composer cs` all pass
- [x] 7.7 Tick phase 4 in `openspec/settings-in-app-plan.md`, and delete the plan
      file — it says to, once phase 4 is archived
