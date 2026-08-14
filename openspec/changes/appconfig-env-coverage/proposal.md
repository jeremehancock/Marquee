## Why

Five environment variables decide where Marquee keeps a user's posters, database,
logs, and sessions — and four of the five have no test pinning their default.
A default nobody asserts is a default a refactor can move silently, which is
exactly what happened to `session.save_path`: sessions landed in the container's
`/tmp` and every image update signed the user out. The fix shipped in v2.5.0 with
a test guarding `SESSION_DIR` only, because assumed parity with `DATA_DIR` and
`POSTERS_DIR` turned out not to exist. This closes that gap for every variable
`AppConfig` reads, and settles once — in writing — which of them users are meant
to see.

## What Changes

- `tests/Unit/Config/AppConfigTest.php` asserts, for **every** variable
  `AppConfig::fromEnv()` reads, the value applied when the variable is absent:
  `SITE_TITLE`, `DATA_DIR`, `POSTERS_DIR`, `SESSION_DIR`, `DISPLAY_ERRORS`.
- The same test asserts that a trailing `/` is trimmed from each of the three
  directory settings, not just `SESSION_DIR`.
- `DISPLAY_ERRORS` is documented in `docs/development-workflow.md` as a
  developer-only setting, next to the local toolchain it belongs to. It stays out
  of the user-facing README.
- `DATA_DIR` and `POSTERS_DIR` stay **undocumented** — a decision, not an
  omission. The `/config` volume layout is a fixed, simple contract in the
  README; publishing the subpaths as knobs invites installs that split the volume
  and then ask why a backup of `/config` didn't restore their library.
- A new `tests/Unit/Config/ConfigurationSurfaceTest.php` asserts that split, so
  the documentation decision is guarded the same way the defaults are. The same
  argument applies to both: a decision nobody asserts is a decision a later edit
  reverses silently. Reading repo files as content is an established pattern here
  — `tests/Unit/Asset/` does it to CSS, JS, and Twig.
- No default changes, no variable is renamed, and `AppConfig`'s behaviour is
  untouched. Tests and docs only.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the *Typed configuration from environment* requirement
  gains the two properties the code already relies on but the spec never states —
  that every setting's default is asserted by a test, and that each directory
  setting defaults onto the `/config` volume with any trailing separator trimmed.
  A new requirement records that the documented configuration surface is chosen
  by audience: variables an install is expected to set belong in the README,
  developer-only ones in `docs/development-workflow.md`, and the `/config` layout
  is presented as fixed. That split is itself asserted by a test, so the
  requirement is enforced rather than merely stated.

## Impact

- `tests/Unit/Config/AppConfigTest.php` — extended; the class is no longer about
  sessions alone, so its purpose docblock is rewritten.
- `tests/Unit/Config/ConfigurationSurfaceTest.php` — new; reads `README.md` and
  `docs/development-workflow.md` and asserts which variables each one names.
- `docs/development-workflow.md` — one addition covering `DISPLAY_ERRORS`.
- `README.md` — unchanged, deliberately. The decision to leave `DATA_DIR` and
  `POSTERS_DIR` out is recorded in the spec so the next reader finds an answer
  rather than a gap.
- `src/Config/AppConfig.php` — unchanged. No production code is touched.
- No migration, no user-visible behaviour change, no new dependency.
