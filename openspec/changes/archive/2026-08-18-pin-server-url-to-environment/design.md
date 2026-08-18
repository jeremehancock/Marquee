## Context

This change reverses phase 4 of `openspec/settings-in-app-plan.md` and adjusts
what phase 1 did to the Plex server address.

Phase 1 turned `PLEX_SERVER_URL` into a `SettingKey`, seeded once from the
environment. That made the compose value inert after first boot, which was fine
only because phase 4 was going to supply a browser path to the address. Phase 4
did that with a first-run claim code and, in doing so, had to invent a whole
subsystem — a code file at `0600`, a rate limiter, a server probe, a middleware
outside authentication, a `claimed_at` marker that had to survive
`clearToken()` — purely to re-establish a property that one environment variable
had been providing for free.

The claim shipped to `:dev` and was validated on both fresh and upgrade paths.
It is being removed because it costs the user more than it saves, not because it
is broken.

Two facts make this cheap:

- **Nothing has been released.** `main` has not moved since before phase 1, so
  no install anywhere has run any of this. There is no public migration.
- **Phase 4 was never archived.** `openspec/specs/` contains no claim
  requirement, so deleting `openspec/changes/add-first-run-claim/` is the whole
  of the spec cleanup. No `REMOVED` delta is needed for anything the claim added.

The one live install that matters is the maintainer's own `:dev` volume, whose
`settings.json` already contains a seeded `plex_server_url` key.

## Goals / Non-Goals

**Goals:**

- Remove every trace of the first-run claim from code, templates, nginx config,
  tests, and docs.
- Make `PLEX_SERVER_URL` environment-only and read at every bootstrap, so
  editing the compose file and restarting changes the address.
- Leave the security property intact and make its permanence explicit in the
  spec, so a later change does not "finish the migration" by moving the address
  into the store.
- Leave phases 1–3 — the settings store, the settings screen, the app-owned
  auto-import schedule — untouched and shipped.

**Non-Goals:**

- Offering the Plex server address on the settings screen. This was considered
  and rejected: it would reintroduce exactly the problem the claim was built to
  solve.
- Migrating or cleaning the stale `plex_server_url` key out of existing
  `settings.json` files. Nothing reads it once the `SettingKey` case is gone, so
  it is inert data, and writing migration code for an unreleased key would be
  more risk than the key itself carries.
- Changing anything about how the Plex *token* is sourced.

## Decisions

**Revert the commit rather than delete files by hand.** `git revert --no-commit b2f0b12`
catches the small edits — a constant added to `publicPaths`, a container
definition in `bootstrap.php`, an unlink in `AppTestCase` — that a file-by-file
deletion reliably misses. The two pieces of `b2f0b12` worth keeping are then
re-applied deliberately on top, which is a smaller and more reviewable operation
than reconstructing the removal.

*Alternative considered:* deleting the claim files individually and hand-editing
the rest. Rejected as error-prone; the commit touched nine files incidentally.

**Remove `PlexServerUrl` from `SettingKey` rather than special-casing it.**
`SettingKey` is the single source of truth for three derived behaviours: what
the seeder imports, what `SupersededEnvironment` reports as relocated, and what
the settings screen can offer. Deleting the case makes all three correct at
once, with no flag to keep in sync. In particular `PLEX_SERVER_URL` stops being
reported as "now managed in the application" — which would be an actively
misleading thing to tell a user whose compose file is the only place it works.

*Alternative considered:* keeping the case and marking it non-editable. Rejected:
it would leave the variable reported as superseded while still being obeyed,
which is the one combination the superseded report exists to prevent.

**Resolve the address through `Env::str()` inside `PlexConfig::resolve()`.**
This keeps the "read configuration once at bootstrap" rule intact — the call
sits in the same resolver that already reads the connection store for the token,
not deep in `HttpPlexClient`. `PlexConfig` already owns the parse-and-trim logic
that turns an unusable address into an empty one; only its input changes.

**Keep the existing "withheld from the screen" requirement rather than removing
it.** Its behaviour is unchanged — no field appears either way — so a `REMOVED`
delta would be wrong. What changes is its justification: the current text
promises the field will appear "once the property it provides has been
replaced", and that clause is precisely the invitation to rebuild the claim.
`MODIFIED` withdraws the promise while keeping the requirement.

**State the permanence in the spec, not only in `CLAUDE.md`.** The reason the
claim was built is a reason someone will rediscover. The new `settings`
requirement says plainly that environment-only is load-bearing and must not be
relocated for consistency, so the next person to read the spec finds the
argument before they act on the instinct.

## Risks / Trade-offs

**The stale `plex_server_url` key remains in the maintainer's `settings.json`.**
→ Nothing reads it once the `SettingKey` case is gone. It is inert. Validation
on `:dev` includes confirming that an address change in compose takes effect
despite the stale key being present, which is the case that would expose a
missed read path.

**`git revert` may conflict or may silently drop a keeper.** → The two things
being kept from `b2f0b12` are named explicitly (the `docs/docker.md` s6 block is
from phase 3 and not in this commit; only the claim section appended by phase 4
is dropped). Review `git diff` before committing rather than trusting the revert.

**Removing a `SettingKey` case breaks tests in bulk.** → Expected. `SettingsForm`
helpers in both `SettingsScreenTest` and `SettingsFormTest` build submissions
from a field list, so dropping the field fails every save test at once until
both helpers are updated. This is loud rather than subtle, which is the good
failure mode.

**An install with no address configured has no in-app way to fix itself.** →
Accepted deliberately. That deadlock is the security property: the settings
screen is behind a session, a session needs a sign-in, and a sign-in needs an
address. The remedy is host access, which is the same access that set the
variable in the first place.

**`AppTestCase::makeUnclaimedApp()` disappears, and with it the coverage of the
no-address state.** → The state still exists and still needs a test; it is now
"no `PLEX_SERVER_URL` in the environment" rather than "unclaimed". The helper is
replaced rather than deleted outright.

## Migration Plan

No user-facing migration exists — nothing has been released. The sequence is:

1. `git revert --no-commit b2f0b12`, then re-apply the keepers and inspect the
   staged diff.
2. Layer the environment-only move on top.
3. Delete `openspec/changes/add-first-run-claim/`.
4. Run `composer test && composer stan && composer cs`.
5. Push, build `:dev`, and validate on a real image: an upgrade from the
   existing install, a fresh install with `PLEX_SERVER_URL` set reaching sign-in
   with no wizard, and an address change in compose taking effect after a
   restart.
6. `/ship` offers the `VERSION` bump — the first since 2.5.0, covering phases
   1–3 plus this correction.

Rollback is `git revert` of the resulting commit; no data is written or migrated
by this change, so there is no state to unwind.

## Open Questions

None. Both decisions the handoff flagged — whether to keep the address editable
in Settings, and what becomes of the plan file — have been settled with the
user: the address is not editable, and the plan file is kept with phase 4 marked
abandoned.
