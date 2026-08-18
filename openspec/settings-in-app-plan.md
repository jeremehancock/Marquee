# Plan: move configuration from compose into the app

Scaffolding for a four-phase migration. **Not a spec.** Delete this file when
phase 4 is archived.

## How to use this

Each phase is one OpenSpec change. Context can be cleared between phases.

1. Read **Shared context** below — every phase depends on it.
2. Copy that phase's prompt block into `/opsx:propose`.
3. `/opsx:apply` → user validates the `:dev` image → `/ship`.
4. Tick the phase's checkbox here, in the same PR.
5. Clear context. Next phase.

Phases are strictly ordered, and each must leave `dev` working — every phase is
validated against its own `:dev` image before the next begins.

### Delivery: one release, not four

**No phase reaches production alone.** All four accumulate on `dev`; `main` is
touched once, after phase 4 is validated.

```
  phase 1 ─┐
  phase 2 ─┤  each: /opsx:apply → :dev → validate → /ship → archive
  phase 3 ─┤  all on dev; :dev republished each time, prod untouched
  phase 4 ─┘
             ↓  bump VERSION once
        one PR: dev → main      ← the only release
```

This is the documented flow, not a deviation: `release-publishing` specifies
that a version bump on `dev` publishes no release, and that only a push to
`main` cuts one.

Consequences that bind every phase:

- **Decline the VERSION bump** when `/ship` offers it, until phase 4. It never
  bumps without asking.
- **Still archive each phase** on `dev` after its `:dev` validation. Phase N+1's
  delta specs are written against `openspec/specs/` as phase N left it, so
  archiving per phase is required, not merely permitted.
- **Because no phase ships alone, a phase may leave a capability temporarily
  unreachable from the UI.** Phase 1 makes the settings store authoritative with
  no screen to write to it; that is only acceptable under this delivery model.
  If the one-release decision is ever reversed, revisit phase 1's seeding
  design — see its `design.md`.
- **Documentation edits stay within each phase's scope.** No phase should
  describe a compose file that a later phase creates. Phase 4 owns the README's
  configuration table.
- **A hotfix to `main` during this window** would need a branch off `main`
  rather than off `dev`, since `dev` will carry unreleased work for the whole
  sequence.

- [x] Phase 1 — settings store, env seeding, obsolete-env reporting
      (`add-settings-store`)
- [x] Phase 2 — settings page (preferences, Plex behavior, library exclusions)
      (`add-settings-screen`)
- [x] Phase 3 — app-owned auto-import schedule (cron inversion)
      (`invert-auto-import-schedule`)
- [x] Phase 4 — first-run wizard, claim code, `PLEX_SERVER_URL` moves in
      (`add-first-run-claim`)

---

## Shared context

### Goal

Reduce `docker-compose.yml` to container-level concerns only. Everything a user
would want to change after install becomes a setting in the app, editable
without recreating the container.

Target end state:

```yaml
services:
  marquee:
    image: bozodev/marquee:latest
    container_name: marquee
    ports:
      - "1818:80"
    environment:
      PUID: "1000"
      PGID: "1000"
      TZ: "Etc/UTC"
    volumes:
      - ./marquee/config:/config
    restart: unless-stopped
```

### The precedent this follows

`PLEX_TOKEN` already made this journey in 2.1.0. `PlexConnectionStore` writes
`/config/data/plex-connection.json` at runtime and is read once at bootstrap
into a typed config object. `obsoleteEnvToken` / `obsoleteEnvCredentials` /
`obsoleteEnvBypass` already tell users to delete dead compose lines.

This plan generalizes a pattern that is already built, tested, and documented.
Mirror `PlexConnectionStore`'s guarantees: atomic rename, tight permissions,
missing file means first-run state rather than an error, re-read before write so
two processes cannot clobber each other.

### Settings triage — decided, do not re-litigate

**Stays in the environment.** Not a preference; a bootstrap or container
concern.

| Variable | Why it cannot move |
| --- | --- |
| `PUID`, `PGID`, `TZ` | LinuxServer base, consumed before PHP exists |
| `DATA_DIR` | The settings file lives inside it — circular |
| `POSTERS_DIR`, `SESSION_DIR` | Same class of circularity |
| `DISPLAY_ERRORS` | Debugging escape hatch; must work when the app is too broken to read its own settings |
| `UPDATE_REPO`, `POSTER_SOURCE_URL` | Development overrides, not user settings. Exposing them invites broken installs |

**Moves into the app.**

| Variable | Phase |
| --- | --- |
| `SITE_TITLE`, `IMAGES_PER_PAGE`, `DEFAULT_SORT`, `IGNORE_ARTICLES_IN_SORT`, `MAX_FILE_SIZE` | 1 → 2 |
| `SESSION_DURATION`, `UPDATE_CHECK_ENABLED` | 1 → 2 |
| `PLEX_CONNECT_TIMEOUT`, `PLEX_REQUEST_TIMEOUT`, `PLEX_REMOVE_OVERLAY_LABEL` | 1 → 2 |
| `EXCLUDED_LIBRARIES` | 1 → 2 |
| `AUTO_IMPORT_ENABLED`, `_SCHEDULE`, `_MOVIES`, `_SHOWS`, `_SEASONS`, `_COLLECTIONS` | 1 → 3 |
| `PLEX_SERVER_URL` | 1 → 4 |

Phase 1 moves the *source of truth* for all of them. Later phases add the UI.

### Precedence — decided

One source, never two. `PlexConfig`'s docblock records why dual sources were
tried and removed: precedence had to be re-explained everywhere, produced inert
stored values, and made every error message branch on which source was live.

```
first boot after upgrade
      │
      ├─ settings.json absent?  → seed once from env, record `seeded_at`
      │                            (upgrading users keep their values)
      │
      └─ settings.json present? → the store is the only source.
                                  env is read ONLY to report obsolescence
```

Same treatment `PLEX_TOKEN` gets: read, never obeyed, surfaced as "delete this
from your compose file."

Seeding once — rather than re-seeding until the user first saves something — is
safe **only** because all four phases ship as one release. Phase 1's `design.md`
records the alternative and when it would be needed.

### Security model — the part that must not be dropped

`PLEX_SERVER_URL` is not merely a setting. It is a **trust anchor**: an
out-of-band assertion only someone with host access can make. It is what stops
the first stranger to load an unconfigured install from becoming its owner.
`PlexServerOwner` verifies the signing-in account against *that* server.

Moving it into the browser removes the anchor. An attacker naming a server they
control — real or a stub returning a plausible `<MediaContainer>` — passes the
ownership check, because they chose the server.

Reachability does not save this. Marquee has outbound internet by design
(`plex.tv`, `posteria.app`, GitHub), and nothing restricts the address to
private ranges. Probing the URL for Plex identity is **UX, not a control** — a
stub satisfies it. Say so in the spec rather than dressing it as security.

**Therefore phase 4 introduces a claim code**, restoring the "requires host
access" property in a form a settings page cannot reach:

```
first boot, nothing claimed
      │  generate code → /config/data/claim-code.txt (0600) and marquee.log
      ▼
wizard step 1 requires the code + a Plex server URL
      │  correct code AND verified ownership
      ▼
claimed — code file deleted, gate closed for the life of the volume
```

**The trap:** `PlexConnectionStore::clearToken()` deliberately forgets the owner
so ownership is re-proven on next sign-in. If disconnecting also cleared the
claim, a public install would reopen to the first stranger and the whole control
would be worthless. `claimed_at` MUST survive `clearToken()`, alongside
`client_identifier` and `signing_secret`, which are already preserved across
disconnect for the same class of reason.

Also required: ≥20 bits of entropy, per-IP throttling on the claim endpoint,
`0600` on the file, deletion on successful claim. Reset = delete the marker,
which needs filesystem access. That is the point.

### Impact if a claim is lost anyway — informs docs, not design

The victim's Plex server, token, and media are **not** reachable: the attacker's
token is scoped to their own account, and Plex answers 401 unauthenticated. What
is exposed is any poster library already on disk (`/config/posters` is
independent of claim state) and the LAN, via the URL-fetch SSRF below.

Recovery docs must therefore say: take a publicly reachable install off the
network before deleting its connection state, or the next claimant sees the
library.

### Out of scope, worth doing separately

`ChangePosterService::fetchUrl()` (`src/Poster/Edit/ChangePosterService.php:176`)
validates only that the URL parses and is `http(s)`. No private-range blocking,
so an authenticated user can make the server probe the LAN. Pre-existing,
authenticated, unrelated to this plan — but it is what turns a hijacked install
into a scanning foothold. Propose independently against `poster-editing`.

### Conventions that apply to every phase

- `openspec validate <change> --strict` before coding. Scenarios need exactly
  four `#`.
- `composer test`, `composer stan`, `composer cs` all pass before any commit.
- Docs checked in the **same** commit — `README.md` compose block especially.
- Work on `dev`. `/ship` owns everything after apply. Never archive before the
  user has validated the `:dev` image.

Learned in phase 1, and cheaper to read than to rediscover:

- **In a `MODIFIED` requirement, never rename a scenario.** The header is its
  identity: `openspec archive` compares headers against the current spec and
  refuses the archive as a dropped scenario. Reword the body freely, keep the
  `#### Scenario:` line byte-identical, and add new scenarios rather than
  repurposing old ones.
- **`tests/AppTestCase.php` splits the `$env` it is given.** Anything backed by a
  `SettingKey` seeds the settings store and is then removed from the
  environment, because a test configures an install rather than a compose file —
  and a variable left set raises the superseded notice on every page. Use
  `supersede()` where a leftover compose variable is the point of the test.
- **Adding a setting means adding a `SettingKey` case.** Its default lives there;
  its floor or fallback stays in the config object that owns the meaning, so a
  value the settings screen accepts and a value bootstrap corrects cannot
  disagree. `SupersededEnvironment` derives its list from the enum, so a new
  setting is reported without anyone remembering to list it twice.

Learned in phase 2, and cheaper to read than to rediscover:

- **Each floor is now a constant on its config object** — `AuthConfig::MINIMUM_DURATION`,
  `PlexConfig::MINIMUM_TIMEOUT`, `PosterConfig::MINIMUM_PER_PAGE` / `MINIMUM_FILE_SIZE` —
  read by both `resolve()` and `SettingsForm`. A phase-3 setting with a floor
  follows that pattern rather than writing the number twice. The form MAY be
  stricter than bootstrap (it offers whole days against a sixty-second floor);
  it must never be looser, or a saved value gets silently corrected.
- **`SettingsStore::setMany()` is how a screen saves.** One re-read, one rename,
  every key — so a failure part-way cannot leave an install half-configured.
  `set()` is a one-key call to it.
- **`makeApp()` deletes `settings.json` and re-seeds**, so a second `makeApp()`
  erases what a save just wrote. To assert "the next request sees it", build a
  container directly — `createApp(buildContainer([...]))` — over the store on
  disk, or resolve the config objects from `new SettingsStore($dataDir)`. See
  `SettingsScreenTest::nextRequest()`.
- **`FakePlexClient` filters by its own constructor argument**, not by the
  container's `LibraryExclusions`, so a functional test asserting that an
  exclusion reaches a screen has to hand the fake the stored list.
- **`PlexClient::allLibraries()` exists for exactly one caller.** It reports
  excluded libraries, which every other caller must not see. Phase 3's scheduled
  run wants `libraries()`.
- **Form controls live in `templates/partials/_form.html.twig`** and their styles
  in the "Form controls, in one vocabulary" block of `app.css`. Phase 3's
  auto-import section is macros over those, not new markup.
- **A phase-2 spec requirement records what the screen withholds** — the Plex
  server address and auto-import — with the reason. Phase 3 removes auto-import
  from that requirement rather than merely adding controls; phase 4 does the same
  for the server address.

Learned in phase 3, and cheaper to read than to rediscover:

- **A scenario cannot be repurposed or dropped, so overturning half a requirement
  means REMOVED + ADDED.** Phase 2's "Settings deliberately withheld" covered the
  server address and auto-import in one requirement with a scenario each. Phase 3
  retired the whole requirement, with `**Reason**` and `**Migration**`, and re-added
  the server-address half under its own name. **Phase 4 does the same to that one**
  — do not try to edit it.
- **Cron expressions cannot appear in a PHP docblock.** `*/6` contains the
  character pair that closes a block comment; PHP fails to parse the file. Use a
  `//` comment or describe the schedule in words.
- **Slot identity is `YYYYMMDDnn`, not a timestamp.** A timestamp computed as
  local midnight plus N hours collides across a 23-hour daylight-saving day — that
  day's last hourly slot equals the next day's first — and one run is silently
  skipped. The date-derived identifier cannot collide.
- **`/health` does not prove an s6 service came up.** nginx serves pages happily
  while `svc-cron` is dead. Check `s6-svstat /run/service/<svc>` explicitly;
  `docs/docker.md` now carries the commands.
- **`AutoImportService::run()` takes an optional clock** (`?DateTimeImmutable`),
  which is what makes the scheduling testable without touching the system time.
- **Settings fields are validated against enums** — `SortOrder`,
  `AutoImportInterval` — and refused rather than defaulted. Adding a field to
  `SettingsForm` means adding it to the `submission()`/`form()` helpers in both
  `SettingsFormTest` and `SettingsScreenTest`, or every save test fails at once.

---

## Phase 1 — settings store, env seeding, obsolete-env reporting

**Capability:** new `settings`, plus an `application-shell` delta for bootstrap
wiring.

Adding a capability means adding it to the map in `openspec/config.yaml`. This
is permitted: `settings` names a domain, not this change. Suggested map entry:

> `settings` — the runtime settings store, its resolution into typed config
> objects, first-run setup, and the reporting of superseded environment
> variables.

**Nothing user-visible changes.** Every default and behavior is preserved; only
the source of truth moves.

```
/opsx:propose Introduce a runtime settings store so configuration lives in the
app rather than the environment. This is phase 1 of the plan in
openspec/settings-in-app-plan.md — read its Shared context section first.

Add a `settings` capability to the map in openspec/config.yaml, then:

- A `SettingsStore` writing /config/data/settings.json, mirroring
  PlexConnectionStore's guarantees: atomic rename via tempnam, 0600, re-read
  before write so the web process and the cron CLI cannot clobber each other,
  and missing/malformed file meaning "nothing stored" rather than an error.
  Settings are separate from plex-connection.json: that file holds credentials,
  this one holds preferences, and the SQLite database is specified as a
  deletable cache so neither belongs there.

- Typed config objects resolve their movable fields from the store instead of
  the environment, while DATA_DIR, POSTERS_DIR, SESSION_DIR and DISPLAY_ERRORS
  continue to come from the environment. See the triage table in the plan for
  the exact split. Resolution still happens exactly once at bootstrap; the
  existing flooring and fallback rules (SESSION_DURATION floored at 60,
  timeouts floored at 1, DEFAULT_SORT falling back to A-Z) are preserved
  verbatim.

- One-time seeding: when settings.json is absent, seed it from the environment
  and record that seeding happened, so an upgrading install keeps its existing
  configuration and never re-seeds after a user changes something. After
  seeding, the store is the only source and environment values are never obeyed.

- Generalize the obsolete-environment reporting. Today PlexConfig and AuthConfig
  each carry their own obsolete flags; replace that with one service that
  reports which superseded variables are still set, so a notice can list them.
  Keep the existing PLEX_TOKEN, AUTH_USERNAME/PASSWORD and AUTH_BYPASS reporting
  behavior intact — those are obsolete outright, not merely relocated, and the
  distinction should survive.

No settings UI in this phase, and no change to how any setting behaves.
PLEX_SERVER_URL and the auto-import variables move their source of truth here
but keep their current behavior; their UI arrives in phases 4 and 3.
```

**Done when:** every existing test passes unchanged, seeding runs once, and a
fresh install with no environment variables gets today's defaults.

---

## Phase 2 — settings page

**Capability:** `settings`, plus `visual-design` if new components are needed.

The first real user win. Deliberately excludes auto-import: its toggle does
nothing until phase 3 inverts the cron, and shipping a control that lies is
worse than shipping it late.

```
/opsx:propose Add a settings screen so configuration can be changed without
recreating the container. This is phase 2 of the plan in
openspec/settings-in-app-plan.md — read its Shared context section first. Phase
1 (the settings store) is already shipped; this adds the UI over it.

An authenticated /settings screen, behind the Plex connection gate like the rest
of the app, covering:

- Presentation: site title, images per page, default sort, ignore articles when
  sorting, maximum upload size.
- Plex behavior: connect timeout, request timeout, remove the Kometa overlay
  label.
- Session: session duration.
- Updates: whether to check for updates.
- Library exclusions, as checkboxes listing the libraries the connected Plex
  server actually reports — not a comma-separated string. This is the biggest
  usability win in the whole plan: today a misspelled library name silently
  excludes nothing. Excluded libraries stay app-wide, as LibraryExclusions
  already specifies.
- A panel listing any superseded environment variables still set in the user's
  compose file, telling them these are now managed here and should be deleted.

Auto-import is deliberately NOT on this screen yet; it arrives in phase 3
together with the scheduling change that makes its controls real.

Saved values take effect on the next request — configuration is read once per
request at bootstrap, so no restart is involved. Validation reuses the same
flooring and fallback rules the config objects already enforce, so a value
rejected here and a value corrected at bootstrap cannot disagree. CSRF applies
as it does to every other form.
```

**Done when:** each setting can be changed in the browser and takes effect on
the next page load, with no container restart.

---

## Phase 3 — app-owned auto-import schedule

**Capability:** `auto-import`.

Touches Docker. **Requires a local image build and a `/health` smoke test before
pushing** — CI only exercises the image after a push. See `docs/docker.md`.

This also fixes a live bug: `svc-cron/run` starts `crond` only when
`/etc/crontabs/abc` is non-empty at boot, and `init-marquee-config/run` writes
that file only when `AUTO_IMPORT_ENABLED` is true. A container that boots with
auto-import disabled has no cron process at all, so no setting could ever enable
it without a restart.

```
/opsx:propose Make the auto-import schedule owned by the application rather than
baked into the container at startup. This is phase 3 of the plan in
openspec/settings-in-app-plan.md — read its Shared context section first.

Today AUTO_IMPORT_ENABLED and AUTO_IMPORT_SCHEDULE are consumed by the s6 init
script, which writes /etc/crontabs/abc once at boot; svc-cron then runs crond
only if that file is non-empty. Two consequences: the schedule cannot change
without recreating the container, and a container that booted with auto-import
disabled can never run it at all.

Invert it:

- The crontab becomes a fixed tick, written unconditionally, and crond always
  runs. The interval-to-cron-expression case statement and the enabled check
  both leave the init script entirely.
- bin/auto-import.php decides whether a run is due: it reads enabled, the
  selected media types, and the interval from the settings store, compares
  against the last completed run, and exits quietly when not due. The CLI
  already reads the connection store from disk, so reading settings the same way
  needs no new mechanism.
- Last-run state belongs in SQLite. Losing it costs one extra import, which is
  consistent with the database being specified as a deletable cache.
- Add the auto-import section to the settings screen from phase 2: enable,
  per-type toggles for movies/shows/seasons/collections, and the interval.
  Changing any of them takes effect on the next tick with no restart.

Pick the tick interval so the existing 1h/3h/6h/12h/24h choices remain exact.
The user-facing schedule options should not change in this phase.

The Dockerfile and s6 service definitions change, so this needs a local image
build and a /health smoke test before pushing, per docs/docker.md.
```

**Done when:** toggling auto-import in the browser starts and stops scheduled
runs on a running container, with no restart, verified on the `:dev` image.

---

## Phase 4 — first-run wizard, claim code, `PLEX_SERVER_URL`

**Capability:** `settings` and `authentication`.

The security-relevant phase, done last with 1–3 proven. **Re-read the security
model section above before proposing.** If this phase is ever abandoned,
stopping after phase 3 still yields a five-line compose file and every settings
win except one variable — a real fallback, not a consolation prize.

The wizard is not a new surface. `/connect` is already the first-run screen,
`PlexConnectionMiddleware` already redirects there, and
`PlexConnectionController::screen()` already renders one template in two states.
The wizard is that screen growing a step in front and steps behind.

Step ordering is forced by the architecture, not chosen: `PlexServerOwner` needs
a server URL before it can verify anything, and library checkboxes need a live
connection to render. Only step 1 is reachable without a session.

```
/opsx:propose Move PLEX_SERVER_URL into the app behind a first-run claim, so the
compose file carries no application configuration at all. This is phase 4 of the
plan in openspec/settings-in-app-plan.md — read its Shared context section, and
especially its security model, before proposing anything.

PLEX_SERVER_URL is currently a trust anchor, not just a setting: it is an
assertion only someone with host access can make, and it is what stops the first
stranger who reaches an unconfigured install from becoming its owner. Moving it
into the browser removes that anchor, so this change must replace it.

- A claim code, generated on first boot, written to /config/data/claim-code.txt
  at 0600 and echoed to marquee.log. The first-run wizard requires it. At least
  20 bits of entropy, with per-IP throttling on the claim endpoint. The file is
  deleted once the install is claimed, and the gate never reopens.

- The claim marker MUST survive PlexConnectionStore::clearToken(). That method
  deliberately forgets the owner so ownership is re-proven on the next sign-in;
  if it also cleared the claim, disconnecting would reopen a public install to
  the first stranger and the claim code would be worthless. Keep it alongside
  client_identifier and signing_secret, which are already preserved across
  disconnect for the same class of reason. Reclaiming requires deleting the
  marker from the filesystem, which is the property being preserved.

- Grow /connect into the first-run wizard rather than building a new screen.
  Step 1: claim code and Plex server URL, probed with an unauthenticated request
  to the server's identity endpoint so the server's name can be echoed back
  before the user commits. That probe is a usability feature and must be
  specified as one — it catches typos and wrong ports, but it cannot be a
  security control, because a server the attacker chose can satisfy it. Step 2:
  the existing Plex sign-in, unchanged, verifying ownership against the URL from
  step 1. Step 3: the settings from phases 2 and 3, which require a live
  connection to render library checkboxes at all. Only step 1 is reachable
  without a session.

- Log the owner and server URL when an install is first claimed, so a claim
  nobody expected is visible rather than mysterious.

- Update README.md: the compose example drops to PUID, PGID, TZ, the port, the
  volume and the restart policy. Document how to find the claim code, and
  document resetting a claimed install — including the warning that a publicly
  reachable install should be taken off the network before its connection state
  is deleted, because /config/posters survives independently of claim state and
  the next claimant would see the library.
```

**Done when:** a fresh container with only `PUID`/`PGID`/`TZ` set can be taken
from `docker compose up` to a fully configured install entirely in the browser,
and a second browser reaching it first cannot claim it without the code.
