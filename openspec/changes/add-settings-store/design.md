## Context

Marquee reads every setting from the environment through `App\Support\Env`,
once at bootstrap, into six immutable config objects. One value already breaks
that pattern: the Plex token comes from `PlexConnectionStore`, a JSON file on
the persistent volume written when the user signs in. `PlexConfig::resolve()`
reads it at bootstrap so the "resolve once" rule survives.

This change generalizes that store to the rest of the configuration. It is the
first of four phases described in `openspec/settings-in-app-plan.md`; the
settings screen, app-owned scheduling, and first-run setup follow. Nothing here
is user-visible on its own, which is the point — the risky parts of the
migration land on a foundation that has already shipped and been exercised.

Two constraints shape everything below.

**Bootstrap circularity.** The settings file lives inside `DATA_DIR`. `DATA_DIR`,
`POSTERS_DIR`, and `SESSION_DIR` therefore cannot come from it. `DISPLAY_ERRORS`
is excluded for a different reason: it is the switch that makes a broken install
diagnosable, so it must not depend on reading a file that may be what broke.

**Single source at read time.** `PlexConfig`'s docblock records that dual
sources were tried and removed — precedence had to be re-explained everywhere,
produced stored values that were inert, and made error messages branch on which
source was live. That ruling governs here.

## Goals / Non-Goals

**Goals:**

- Persist configuration to `/config/data/settings.json` with the same durability
  guarantees `PlexConnectionStore` already provides.
- Resolve every movable setting from that store, once, at bootstrap.
- Preserve every current default, floor, and fallback exactly.
- Carry an upgrading install's existing compose configuration across unchanged,
  and tell it once that those variables are now managed in the application.
- Report superseded environment variables through one service rather than
  scattered booleans.

**Non-Goals:**

- Any user interface. No route, template, or form is added.
- Changing what any setting does.
- Touching Docker, s6, or cron.
- Moving `DATA_DIR`, `POSTERS_DIR`, `SESSION_DIR`, or `DISPLAY_ERRORS`.
- Moving `UPDATE_REPO` or `POSTER_SOURCE_URL`, which are development overrides
  rather than user settings; exposing them later would invite broken installs.

## Decisions

### A JSON file beside the connection store, not a SQLite table

`settings.json` sits next to `plex-connection.json` in `DATA_DIR`.

The database is specified as a pure cache of Plex data that is safe to delete —
`orphan-detection` and `plex-import` both lean on that. Settings are not a
cache, and putting them in the database would make deleting it destructive,
which is exactly the reasoning `PlexConnectionStore` already records for
credentials.

They are a *separate* file from the connection store rather than more keys in
it, because the two have different sensitivity and different lifecycles. The
connection store holds secrets at `0600` and survives a settings reset; settings
hold no credential and a user may reasonably want to discard them without
signing in to Plex again.

*Alternative considered:* one file for both. Rejected — a settings write would
have to round-trip the token, widening the number of code paths that can corrupt
a credential.

### Seed once, then the store is the only source

On the first boot that finds no settings file, the store is populated from the
environment and the fact is recorded as `seeded_at`. From then on the store is
authoritative: environment variables it owns are read only to report that they
no longer take effect.

```
  no settings file                         seeded
  ┌──────────────────────────┐           ┌──────────────────────────┐
  │ populate store from env  │  once     │ store is authoritative   │
  │ record seeded_at         │  ───────► │ env is READ but never    │
  │                          │           │   obeyed; reported as    │
  │                          │           │   superseded             │
  └──────────────────────────┘           └──────────────────────────┘
```

An upgrading install therefore keeps every value its compose file set, and is
told once — through the superseded-variable notice — that those lines are now
managed in the application and can be deleted. That is the outcome this
migration exists to produce.

All four phases of the plan reach production as a single release, so no install
ever runs a version where the store is authoritative but no settings screen
exists. That is what makes seeding once safe here.

*Alternatives considered:*
- *Re-seed from the environment until the application first writes a setting.*
  Considered while the phases were expected to release independently, where it
  would have prevented settings freezing between phase 1 and phase 2. Rejected
  once the phases became one release: it leaves a compose-managed install never
  told to clean up its file, and then obsoletes every variable at once the first
  time the user saves anything — a cliff, and a second state to reason about,
  bought for a problem that no longer exists.
- *Env always wins when set.* This is the dual-source model already rejected in
  `PlexConfig`, and it would make the settings screen unable to override a value
  the user had forgotten was in their compose file.

### Configs keep resolving once, and keep their own coercion rules

Each config object gains a resolver that takes the store, in the shape
`PlexConfig::resolve()` already uses. `AppConfig` remains environment-backed for
its directories and keeps a `fromEnv()`; the rest resolve from the store.

Flooring and fallbacks stay *in the config objects*, not in the store. The store
returns typed scalars with defaults; the config decides that a session shorter
than sixty seconds is a lockout and that an unrecognized sort slug is A–Z. That
placement matters for the next phase: a value rejected at the settings screen
and a value corrected at bootstrap must not disagree, and keeping the rule in
one place is what guarantees it.

### One superseded-variable report

`PlexConfig::obsoleteEnvToken`, `AuthConfig::obsoleteEnvCredentials`, and
`obsoleteEnvBypass` are replaced by `SupersededEnvironment`, which reports the
variables still set in the environment that no longer take effect, each with the
reason it no longer does.

Two reasons, because two remedies:

| Kind | Variables | What the user is told |
| --- | --- | --- |
| Retired | `PLEX_TOKEN`, `AUTH_USERNAME`, `AUTH_PASSWORD`, `AUTH_BYPASS` | Gone for good. Delete the line. |
| Relocated | everything the store now owns | Managed in the app now. Delete the line. |

The distinction is preserved rather than flattened because a user who reads
"managed in the app" about `AUTH_PASSWORD` would go looking for a password field
that does not exist and never will.

Both kinds are reported whenever the variable is present. Once the store has been
seeded — which happens on the first boot after upgrading — a relocated variable
genuinely has no effect, so there is no state in which reporting it would be
false.

### `AppConfig` stays split

`AppConfig` ends up drawing from both sources: directories and `DISPLAY_ERRORS`
from the environment, `siteTitle` from the store. That is a genuine seam, not an
oversight, and the class documents which half is which and why.

*Alternative considered:* a separate `PathConfig` for the environment-only
values. Rejected as churn — it would rename three widely-injected properties to
express a distinction that one comment carries adequately.

## Risks / Trade-offs

**A malformed `settings.json` could break every request** → The store degrades
to "nothing stored" on a missing, unreadable, or malformed file, exactly as
`PlexConnectionStore` does, and per-key values that are not usable scalars are
dropped individually rather than costing the whole file. The result is
documented defaults, not a failed request. Asserted by a test that feeds it
garbage.

**Two processes write concurrently** → The web process and the cron CLI each
hold their own store. Writes re-read the file first and change only the affected
keys, and land via `tempnam` + `chmod` + `rename`, so a reader sees the old file
or the new one and never a partial write. This is `PlexConnectionStore::put()`'s
approach, adopted wholesale.

**A read on every request costs I/O** → One small JSON file, read once per
request and memoized for that request's lifetime. The same cost the connection
store already carries on every request that touches Plex.

**Settings are unchangeable between this phase and the settings screen** → Real,
and accepted: the store is authoritative from the first boot, and nothing can
write to it until phase 2. This is survivable only because all four phases ship
as one release, so no install ever runs this phase on its own. If that
delivery decision is reversed, this trade-off must be revisited — the mitigation
is re-seeding from the environment until the application first writes a setting.

**A user edits compose after upgrading and is confused** → The superseded-variable
notice names each variable still set and says it no longer takes effect. This is
the same remedy `PLEX_TOKEN` already gets, and the reason that treatment exists.

## Migration Plan

No data migration. A fresh install writes `settings.json` on first boot by
seeding from a mostly-empty environment, producing the documented defaults. An
upgrading install seeds from whatever its compose file sets, so its behaviour is
identical before and after.

This phase does not reach production on its own. All four phases of the plan
accumulate on `dev`, each validated against its own `:dev` image, and reach
`main` in a single release once phase 4 is validated. `VERSION` is therefore
bumped once, before that one promotion — not per phase, which the release
specification permits explicitly: a version bump on `dev` publishes no release.

Rollback is downgrading the image: `settings.json` is ignored by the previous
version, which reads the environment that is still there. Nothing is destroyed
by rolling back, which remains true until phase 2 gives the user a way to store
a value that was never in their compose file.

## Open Questions

None blocking. One deferred: whether `settings.json` should carry a schema
version. Not needed while the store is a flat map of scalars with defaults for
absent keys, but worth revisiting in phase 3, when auto-import's per-type
toggles and interval arrive and a nested shape becomes tempting.
