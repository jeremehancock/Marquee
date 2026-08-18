## 1. Capability map and tooling

- [x] 1.1 Add `settings` to the capability map in `openspec/config.yaml`, described as the runtime settings store, its resolution into typed config objects, seeding, and superseded-variable reporting
- [x] 1.2 Fix the artifact id in `openspec/config.yaml`: the per-artifact rules key is `spec`, but the schema's id is `specs`, so the four-hash scenario rule has never reached the artifact that needs it. Confirm with `openspec instructions specs --change add-settings-store --json` that the warning is gone

## 2. Settings store

- [x] 2.1 Add `App\Settings\SettingKey` enumerating the stored settings, each with its key name, type, and documented default — one place that answers "what is storable and what does it default to"
- [x] 2.2 Add `App\Settings\SettingsStore` persisting to `<dataDir>/settings.json`: read-through memoization for the request, `tempnam` + `chmod 0600` + `rename` on write, re-read before write so only owned keys change
- [x] 2.3 Make the store fail soft — a missing, unreadable, or unparseable file means "nothing stored"; a single entry of the wrong shape is dropped without costing the file
- [x] 2.4 Add typed accessors (`string`, `int`, `bool`, `list`) that fall back to the `SettingKey` default, so callers never branch on absence
- [x] 2.5 Test: values survive a write/read cycle; a second writer's keys are preserved; absent, malformed, and partially-bad files all resolve to defaults

## 3. Seeding

- [x] 3.1 Add `App\Settings\SettingsSeeder` that writes stored values from the environment for every `SettingKey`, reusing `App\Support\Env` so coercion matches what the environment already does
- [x] 3.2 Record seeding in the store as `seeded_at`, and skip seeding entirely when it is present
- [x] 3.3 Test: an upgrading install's environment values land in the store; a fresh install seeds documented defaults; a second boot does not re-seed and does not overwrite a stored value that differs from the environment

## 4. Configuration objects resolve from the store

- [x] 4.1 `AppConfig`: resolve `siteTitle` from the store; keep `dataDir`, `postersDir`, `sessionDir`, and `displayErrors` on `Env`, and document the seam and why it exists
- [x] 4.2 `AuthConfig`: resolve `sessionDuration` from the store, preserving the 60-second floor and the reason for it
- [x] 4.3 `PlexConfig`: resolve `serverUrl`, `connectTimeout`, `requestTimeout`, and `removeOverlayLabel` from the store, preserving the URL trimming and `Uri` parse check and the 1-second timeout floors. The token still comes from `PlexConnectionStore`
- [x] 4.4 `PosterConfig`: resolve `perPage`, `maxFileSize`, `ignoreArticlesInSort`, and `defaultSort` from the store, preserving the floors and the A–Z fallback for an unrecognized sort slug
- [x] 4.5 `AutoImportConfig`: resolve `enabled` and the four per-type toggles from the store
- [x] 4.6 `LibraryExclusions`: resolve the excluded names from the store, preserving case-insensitive, whitespace-trimmed, name-only matching
- [x] 4.7 Seed `AUTO_IMPORT_SCHEDULE` into the store even though nothing reads it yet — the s6 init script still owns scheduling until phase 3, and having the value already stored keeps that phase to one concern
- [x] 4.8 Move the `UPDATE_CHECK_ENABLED` read out of the container definition in `src/bootstrap.php` and onto the store; leave `UPDATE_REPO` and `POSTER_SOURCE_URL` on `Env` as development overrides
- [x] 4.9 Test: every default asserted in the existing coverage still holds when nothing is stored and nothing is in the environment; every floor and fallback still holds

## 5. Superseded environment reporting

- [x] 5.1 Add `App\Settings\SupersededEnvironment` reporting variables still set that no longer take effect, each classified as retired or relocated
- [x] 5.2 Classify `PLEX_TOKEN`, `AUTH_USERNAME`, `AUTH_PASSWORD`, and `AUTH_BYPASS` as retired, reported whenever present — matching today's behaviour, including `AUTH_BYPASS` being reported on presence rather than truth
- [x] 5.3 Classify every stored setting's variable as relocated, reported whenever present
- [x] 5.4 Remove `obsoleteEnvToken` from `PlexConfig` and `obsoleteEnvCredentials` / `obsoleteEnvBypass` from `AuthConfig`, updating `PlexConnectionStatus` and `PlexConnectionState` accordingly
- [x] 5.5 Update `PlexConnectionController::screen()` and `connect.html.twig` to render the report, keeping the two kinds visually distinct and their remedies different
- [x] 5.6 Test: both kinds report when present and stay distinguishable; an empty report renders no notice

## 6. Bootstrap wiring

- [x] 6.1 Register `SettingsStore` in the container ahead of the config objects, constructed from `AppConfig`'s `dataDir` in the same shape `PlexConnectionStore` already uses
- [x] 6.2 Run seeding once per bootstrap, before any config object resolves
- [x] 6.3 Confirm `bin/auto-import.php` picks the store up through the container with no new mechanism, as it already does for `PlexConnectionStore`
- [x] 6.4 Test: a request resolves configuration once; the CLI entry point resolves the same values as the web process from the same file
- [x] 6.5 `tests/AppTestCase.php` shares one data directory across the suite and already unlinks `plex-connection.json` per test so one test's connection cannot leak into the next. Unlink `settings.json` the same way — without it, the first test to seed would freeze the store and every later test's environment would be ignored

## 7. Documentation

- [x] 7.1 `README.md`: note that environment variables seed an install on first start and stop taking effect thereafter. Keep the configuration table itself intact — phase 4 owns slimming it, and this phase must not describe a compose file that does not exist yet
- [x] 7.2 `docs/development-workflow.md`: record that `DISPLAY_ERRORS`, `UPDATE_REPO`, and `POSTER_SOURCE_URL` stay environment-only, with the reason
- [x] 7.3 Check `CLAUDE.md`'s configuration convention — "all configuration comes from environment variables" is now wrong — and correct it in the same commit
- [x] 7.4 Tick phase 1 in `openspec/settings-in-app-plan.md`

## 8. Gates

- [x] 8.1 `openspec validate add-settings-store --strict`
- [x] 8.2 `composer test`
- [x] 8.3 `composer stan`
- [x] 8.4 `composer cs`
