## Why

Marquee's settings moved into the app, but the README still opens with a compose
file that sets seventeen of them. A new user copies that block, edits values in
it, and is then told by the Settings screen that most of what they just wrote is
superseded and should be deleted. The first thing the project asks people to do
contradicts the second thing it tells them.

The install instructions should show the install Marquee actually wants: a
compose file with the container settings and the Plex address, and everything
else on a screen.

## What Changes

- **Trim the Quick start compose example** to what an install genuinely needs
  for good: the image, port, volume, `PUID` / `PGID` / `TZ`, and
  `PLEX_SERVER_URL`. Every commented-out setting that is now a field on the
  Settings screen comes out.
- **Rewrite the Configuration section** around the Settings screen rather than
  around a variable table. What stays in the README is the short list of
  variables that are never settings: `PUID`, `PGID`, `TZ`, `PLEX_SERVER_URL`,
  `SESSION_DIR`, and the `/config` path overrides.
- **Move the seed-once variable table to a new `docs/configuration.md`**, framed
  as what it is — an optional way to pre-configure a brand-new install from
  compose, not the way to configure Marquee. Seeding still works; it stops being
  the headline. The README links to it.
- **Correct the stale instructions elsewhere in the README** that still route
  the reader through a compose edit and a restart for something the Settings
  screen now owns — notably the excluded-libraries FAQ answer and the update
  check.
- **Audit the whole README against the code** and fix what has drifted: the
  environment-exemption list, the `SESSION_DIR` and `MAX_FILE_SIZE` facts, the
  Settings group table, and the "takes effect when" wording, which the spec
  states more precisely than the README does.

No behavior changes. No variable is retired, and no install has to do anything.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `settings`: adds a requirement that the documented install path is the
  app-configured one — the compose example carries only variables the store
  never owns, and seeding is documented as an optional path for a fresh install
  rather than the primary one. This is what stops the README drifting back to a
  variable-per-setting compose block the next time a setting is added.
- `application-shell`: the documented-configuration-surface requirement gains
  two clauses. A variable that becomes a setting leaves the README table, so the
  guard test's positive control must be a variable that cannot become one —
  `SITE_TITLE` was the control and this change relocates it. And the
  `DATA_DIR` / `POSTERS_DIR` exclusion is stated to cover every user-facing page,
  not the README alone, so a second page cannot overturn it while the test that
  guards the README still passes.

## Impact

- `README.md` — Quick start compose example, the Configuration section and its
  table, the Settings subsection, the excluded-libraries FAQ answer, the
  Updating section.
- `docs/configuration.md` — new; receives the seed-once variable table and the
  explanation of how seeding and superseded reporting behave.
- `docs/development-workflow.md` — contains its own compose snippet with
  `PUID` / `PGID`; check it for the same drift.
- `tests/Unit/Config/ConfigurationSurfaceTest.php` — the guard test asserting
  which variables the README documents. Its positive control is `SITE_TITLE`,
  which this change relocates, so the control moves to `PLEX_SERVER_URL`.
- No source, template, or Docker changes. `composer test`, `stan`, and `cs` all
  run before the commit.
