## Why

The first-run claim code was built to replace a security property that removing
`PLEX_SERVER_URL` from the compose file would have destroyed. It works, but it
costs the user more than it saves: retrieving the code means `docker logs` or
`cat` on a volume, and the Plex address still has to be typed into the browser
afterwards. The claim adds steps and removes none.

Keeping `PLEX_SERVER_URL` in the compose file keeps the property for free.
Setting an environment variable *is* the host-access assertion the claim code
was standing in for. The problem the claim solved only existed because the
variable was being taken away; if the variable stays, the problem does not.

## What Changes

- **The first-run claim is removed entirely.** No claim code file, no `/claim`
  route or screen, no claim middleware, no rate limiter, no server probe, and no
  `claimed_at` marker in the connection store.
- **The Plex server address returns to the environment.** `PLEX_SERVER_URL`
  stops being a stored setting and becomes environment-only, read at every
  bootstrap alongside `DATA_DIR`, `POSTERS_DIR`, `SESSION_DIR`,
  `DISPLAY_ERRORS`, `UPDATE_REPO`, and `POSTER_SOURCE_URL`. Editing the compose
  file and restarting changes the address again, as it did before this sequence
  began.
- **The address is not offered on the settings screen**, and the reason changes
  from "not yet" to "not ever": it is an assertion that requires host access, so
  it cannot be made from a browser. The existing requirement's promise that it
  would move to the screen "once the property it provides has been replaced" is
  withdrawn.
- **`PLEX_SERVER_URL` stops being reported as superseded.** It is a live
  variable again, so telling the user it is now managed in the application would
  be false.
- No **BREAKING** change reaches anyone: `main` has not moved since before this
  sequence started, so none of it has ever been released.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `settings`: the Plex server address becomes environment-only rather than a
  stored setting — a new requirement pins that it is read from the environment
  on every bootstrap and is neither reported as relocated nor shown on the
  screen. The existing requirement withholding it from the screen is rewritten
  so its reason is permanent rather than provisional.
- `application-shell`: typed configuration resolves the Plex server address from
  the environment instead of from the settings store.

## Impact

**Removed code** — `src/Auth/Claim/` (four classes), `src/Auth/ClaimMiddleware.php`,
`src/Controller/ClaimController.php`, `templates/claim.html.twig`,
`tests/Functional/ClaimTest.php`, and the `location = /claim` block in the nginx
sample.

**Modified code** — `src/Settings/SettingKey.php` (the `PlexServerUrl` case
goes), `src/Config/PlexConfig.php` (resolve the address from `Env`),
`src/Settings/SettingsForm.php` and `templates/settings.html.twig` (the server
address field goes), `src/Auth/AuthMiddleware.php`,
`src/Auth/PlexConnectionMiddleware.php`,
`src/Plex/Connection/PlexConnectionStore.php` (the `claimed_at` marker goes),
`src/Routes.php`, `src/bootstrap.php`, and `tests/AppTestCase.php`.

**Docs** — `README.md` (compose example, Quick start, Connecting to Plex, the
handover FAQ entry), `docs/development-workflow.md`, `docs/testing.md`,
`docs/docker.md` (drop only the claim section; keep the phase 3 s6 block),
`CLAUDE.md` (drop the `claimed_at` bullet, add `PLEX_SERVER_URL` to the
environment exceptions), and `openspec/config.yaml` (same exception list).

**Planning** — `openspec/changes/add-first-run-claim/` is deleted unarchived, so
`openspec/specs/` never receives a claim requirement.
`openspec/settings-in-app-plan.md` records phase 4 as abandoned and why.

**Behaviour for an install with no address configured** — it is inert rather
than claimable. The settings screen sits behind a session, a session needs a
Plex sign-in, and a sign-in needs an address to verify ownership against. That
deadlock is the pre-claim behaviour and it is safe.
