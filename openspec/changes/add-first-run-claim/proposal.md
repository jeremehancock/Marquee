## Why

`PLEX_SERVER_URL` is the last thing in the compose file, and moving it is not
like moving the others. It is not merely a setting: it is the assertion that only
someone with host access can make, and it is what stops the first stranger who
reaches an unconfigured install from becoming its owner. Ownership is verified
against *that* server — so an address chosen in the browser verifies nothing,
because the attacker chose the server.

Probing the address to confirm it looks like Plex does not recover this. Marquee
has outbound internet by design and nothing restricts the address to private
ranges, so a stub returning a plausible `<MediaContainer>` satisfies any probe.
That check is worth having as usability and must be specified as usability.

So this phase does not move a variable. It **replaces the property that variable
was providing**, then moves it.

## What Changes

- **A claim code**, generated on first boot, written to
  `/config/data/claim-code.txt` at `0600` and echoed to `marquee.log`. Reading
  either requires host access — the property `PLEX_SERVER_URL` was standing in
  for, in a form a settings screen cannot reach.
- **`/connect` grows into the first-run wizard** rather than a new screen being
  built. It is already the first-run destination and already renders one template
  in two states. Step 1 takes the claim code and the server URL; step 2 is the
  existing Plex sign-in, unchanged; step 3 is the settings from phases 2 and 3.
  Only step 1 is reachable without a session.
- **The claim marker survives `clearToken()`.** That method deliberately forgets
  the owner so ownership is re-proven on the next sign-in. If it also cleared the
  claim, disconnecting would reopen a public install to the first stranger and
  the whole control would be worthless.
- **Guessing is made irrelevant rather than merely slow.** The plan's floor is 20
  bits with per-IP throttling; this uses a code far above that floor, so the
  throttle stops being what stands between an attacker and the install. Both are
  implemented — see design.
- **`PLEX_SERVER_URL` moves into the store** and is entered in step 1. It becomes
  the last relocated variable.
- **The compose file drops to `PUID`, `PGID`, `TZ`, the port, the volume, and the
  restart policy** — the goal the whole four-phase plan was for.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `authentication`: signing in is unchanged, but it now happens inside a wizard
  and only after an install is claimed. Adds what claiming is, that the claim
  gates the first sign-in, and that it survives disconnecting.
- `settings`: the requirement withholding the Plex server address is overturned —
  the property it protected now has its own mechanism. The address becomes a
  stored setting entered in the wizard.
- `application-shell`: the connection gate and the sign-in rate limit both change
  shape. An unclaimed install sends every visitor to step 1, and the claim
  endpoint needs the throttle the sign-in route already has.

## Impact

- **Security-relevant.** The claim code is the only thing standing between a
  publicly reachable unconfigured install and a stranger owning it. Reviewed as
  such, not as a UI change.
- **New**: a claim service and its store entry, the code generator, the wizard's
  step 1 controller and template, a step-1 nginx rate-limit location.
- **Modified**: `PlexConnectionStore` (the claim marker), `PlexConnectionMiddleware`
  (unclaimed installs), `PlexConfig` (the address comes from the store),
  `SettingsForm` (the address becomes editable after claiming), `connect.html.twig`,
  the nginx site config.
- **Docs**: the README's compose example loses everything but the container
  variables; how to find the claim code; how to reset a claimed install, with the
  warning that a publicly reachable install must come off the network first —
  `/config/posters` survives independently of claim state, and the Poster Wall is
  exempt from both gates by design, so the next claimant would see the library.
- **Release**: this is the phase that makes `main` worth touching. After it,
  `VERSION` bumps once and phases 1–4 ship as a single release.
