## Why

Every decision a user makes about their install — the site title, how many
posters a page shows, which libraries to hide, whether auto-import runs — lives
in `docker-compose.yml`. Changing any of them means editing YAML and recreating
the container, and getting one wrong is silent: a misspelled library name in
`EXCLUDED_LIBRARIES` excludes nothing and says nothing.

Marquee already proved the alternative works. The Plex token moved out of the
environment into a runtime store in 2.1.0, and signing in became something the
user does in the browser rather than something they paste into a file. This
change extends that store to the rest of the configuration, so a later phase can
put a settings screen over it.

This phase is deliberately invisible. It moves where configuration comes from
and changes nothing about what any setting does.

## What Changes

- A new settings store persists configuration to `/config/data/settings.json`,
  separate from the credentials in `plex-connection.json` and from the SQLite
  database, which is specified as a deletable cache.

- Typed configuration objects resolve their movable values from the store
  instead of reading the environment directly. Resolution still happens exactly
  once at bootstrap, and every existing default, floor, and fallback is
  preserved unchanged.

- Four settings stay in the environment because they cannot come from anywhere
  else: `DATA_DIR`, `POSTERS_DIR`, and `SESSION_DIR` name where the store itself
  lives, and `DISPLAY_ERRORS` has to work when the application is too broken to
  read its own settings. `PUID`, `PGID`, and `TZ` were never Marquee's to read.

- Environment variables seed the store rather than being obeyed by it. The first
  bootstrap that finds no stored settings populates the store from the
  environment, so an upgrading install keeps every value its compose file set.
  Seeding happens once; from then on the store is the only source, and the
  environment is read only to report that it no longer has any effect.

- Obsolete-environment reporting is generalized. `PlexConfig` and `AuthConfig`
  each carry their own flags today; one service now reports which superseded
  variables are still set, so a single notice can list them. The distinction
  between *relocated* (now managed by the app) and *obsolete outright*
  (`PLEX_TOKEN`, `AUTH_USERNAME`, `AUTH_PASSWORD`, `AUTH_BYPASS` — which never
  come back) is preserved, because the remedy differs.

- No user interface. No setting changes behaviour. The settings screen arrives
  in the next phase, auto-import scheduling in the one after, and the Plex
  server address in the last. All four phases accumulate on `dev` and reach
  production as a single release, so no install ever runs a version where the
  store is authoritative and no settings screen exists.

- Fixes a latent defect in `openspec/config.yaml`: its per-artifact rules are
  keyed `spec`, but the schema's artifact id is `specs`, so the rule requiring
  four-hash scenario headings has never been delivered to the artifact that
  needs it.

## Capabilities

### New Capabilities

- `settings`: the runtime settings store — how configuration is persisted,
  resolved into typed objects at bootstrap, seeded once from the environment,
  and how superseded environment variables are reported. Later phases add the
  settings screen, app-owned scheduling, and first-run setup to this capability.

### Modified Capabilities

- `application-shell`: the "Typed configuration from environment" requirement no
  longer describes where configuration comes from. It keeps ownership of the
  bootstrap contract — read once, immutable, typed, every default asserted by a
  test — and delegates the source to `settings`.

Specs that name an individual variable — `auto-import` for
`EXCLUDED_LIBRARIES`, `plex-export` for `PLEX_REMOVE_OVERLAY_LABEL`,
`poster-library` for `DEFAULT_SORT`, `authentication` for `SESSION_DURATION` —
are deliberately left alone. Their behaviour is unchanged in this phase, and
rewording them is the business of the phase that gives each setting a screen.
Splitting spec churn along the same line as user-visible change keeps each
phase's delta reviewable.

## Impact

**New code**
- `App\Settings\SettingsStore` — persistence, mirroring `PlexConnectionStore`'s
  guarantees (atomic rename, tight permissions, re-read before write, missing or
  malformed file means "nothing stored").
- `App\Settings\SettingsSeeder` — the one-time environment import and its
  `seeded_at` marker.
- `App\Settings\SupersededEnvironment` — reports which superseded variables are
  still set, replacing the per-config obsolete flags.

**Modified code**
- `App\Config\AppConfig`, `AuthConfig`, `AutoImportConfig`, `LibraryExclusions`,
  `PlexConfig`, `PosterConfig` — resolve movable fields from the store.
- `src/bootstrap.php` — wire the store ahead of the config objects; the
  `UPDATE_CHECK_ENABLED` read moves out of the container definition.
- `App\Controller\PlexConnectionController` and `connect.html.twig` — consume the
  generalized report instead of two booleans.

**Unchanged deliberately**
- `bin/auto-import.php` needs no new mechanism; it already reads a store from
  disk to obtain the Plex token, and reads settings the same way.
- Docker, s6, and cron are untouched in this phase.

**Documentation**
- `README.md` gains a note that environment variables seed a new install and
  stop taking effect once the application has written a setting.
- `docs/development-workflow.md` for `DISPLAY_ERRORS` remaining environment-only.

**Risk**
- The store is read on every request. A malformed or unreadable file must degrade
  to documented defaults rather than failing the request, exactly as
  `PlexConnectionStore` does — otherwise one bad write bricks the install.
