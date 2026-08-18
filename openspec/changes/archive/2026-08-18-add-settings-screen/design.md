## Context

Phase 1 made `/config/data/settings.json` the only source for every movable
setting, seeded once from the environment and read once per request into the
typed config objects. Nothing writes to it. This change adds the writer.

What already exists and constrains the design:

- `SettingsStore` reads on demand, caches for the request, and writes one key at
  a time via `set()` — re-reading first, then an atomic rename.
- `SettingKey` holds each setting's name, its seeding variable, and its default.
  Floors and fallbacks live in the config object that owns the meaning:
  `max(60, …)` in `AuthConfig`, `max(1, …)` in `PlexConfig` and `PosterConfig`,
  `SortOrder::fromSlug(…) ?? SortOrder::default()`.
- `HttpPlexClient::libraries()` drops excluded libraries at the single point
  where libraries enter the application, so nothing downstream can observe one.
- `SupersededEnvironment` already separates retired from relocated variables, and
  the connection screen already renders the retired ones.
- The import screen's shape — fetch libraries synchronously, catch
  `PlexException`, render the message — is the established pattern for a page
  that needs the server.

## Goals / Non-Goals

**Goals:**

- Every setting listed in phase 2 of the plan is editable in the browser and in
  effect on the next request.
- Library exclusions are chosen from the libraries the server reports.
- A value the screen accepts is never one bootstrap silently corrects.
- The screen degrades rather than fails when Plex is unreachable.

**Non-Goals:**

- Auto-import controls. Phase 3.
- `PLEX_SERVER_URL`. **Deliberately not on this screen**, and not merely for
  sequencing: it is the trust anchor that stops the first stranger reaching an
  unconfigured install from claiming it. Phase 4 replaces that property with a
  claim code before the field is ever rendered. Adding a server-URL field here
  would remove the anchor with nothing in its place.
- Per-user settings. Marquee has one owner; settings are install-wide.
- Any change to a default, a floor, or a fallback.

## Decisions

### The screen owns no rules of its own

Validation reuses the floors the config objects already apply, so the two cannot
disagree. Today those floors are literals inside `resolve()`. Each becomes a
public constant on its owning config object — `AuthConfig::MINIMUM_DURATION`,
`PlexConfig::MINIMUM_TIMEOUT`, `PosterConfig::MINIMUM_PER_PAGE` — read by both
`resolve()` and the form. One number, two readers.

*Alternative rejected:* a validation table beside the screen. It is the same
literal written twice, and the failure mode is silent — a value the form accepts
and bootstrap corrects looks to the user like a setting that will not stick.

### The invariant is one-directional

The form MAY be stricter than bootstrap; it MUST NOT be looser. Bootstrap floors
a session duration at 60 seconds; the form offers whole days with a floor of one.
Nothing the form accepts is corrected later, which is the property that matters.
Where the form is stricter, the spec says so rather than implying the floors are
identical.

Stored values outside a field's offered range — a seeded `SESSION_DURATION` of
3600, say — are displayed clamped into it rather than rejected. Rendering a value
the form cannot accept would make an unrelated field unsavable.

### Units are chosen for the reader, converted at the boundary

| Setting | Stored | Shown | Range offered |
| --- | --- | --- | --- |
| Session duration | seconds | days | 1–365 |
| Max upload size | bytes | MB | 1–100 |
| Timeouts | seconds | seconds | 1–300 |
| Posters per page | count | count | 1–200 |

Conversion happens in the form service, never in the store or the config
objects — the store's contract is unchanged and the seeded value of an upgrading
install still means exactly what it meant. A stored value that is not a whole
number of its display unit is rounded to the nearest, never to zero.

### The exclusions editor sees what nothing else may

`libraries()` hides excluded libraries deliberately, which makes exclusion a
one-way door from inside the application: a library you excluded is one you can
no longer see to un-exclude. The editor is the one caller that needs the
unfiltered list.

Add `PlexClient::allLibraries()` — the same fetch and parse without the exclusion
filter — and have `libraries()` filter its result. One place still parses
`/library/sections`, and the filtering stays at one line rather than becoming a
boolean argument threaded through callers.

*Alternative rejected:* build a second `HttpPlexClient` with empty exclusions for
the settings screen. Two clients in the container, one of which quietly ignores
configuration, is a trap for the next caller who injects the wrong one.

The spec carve-out is narrow and named: exclusions stay invisible to every
screen, import, and scheduled run **except** the editor that manages them.

### A stored exclusion the server does not report is kept

The form submits the boxes that are checked. Naively, saving would then delete
any exclusion whose library the server did not report in that render — a library
that was renamed, removed, or on a server that answered slowly. Excluding a
library is how a user hides content deliberately; un-hiding it as a side effect of
saving an unrelated field is the worst failure this screen can have.

So a save is a merge: exclusions for libraries the server reported are replaced
by the checkboxes; stored names the server did not report are preserved
untouched. The screen lists those preserved names, so a stale entry is visible
and removable rather than invisible and permanent.

### Plex unreachable degrades to a read-only list

If `allLibraries()` throws, the screen renders the Plex failure message in the
exclusions section, lists the stored exclusions as text, and offers no
checkboxes. Every other section saves normally, and a save made in that state
preserves the stored exclusions by the merge rule above — there were no reported
libraries, so nothing is replaced.

### One form, one write

The screen is a single form and a single POST. `SettingsStore::set()` writes one
key at a time; sixteen settings would mean sixteen read-modify-rename cycles, and
a failure halfway leaves a half-saved install. Add `setMany(array $values)`:
re-read once, apply every changed key, write once. `set()` becomes a one-key call
to it, so both paths share the same merge.

### POST-redirect-GET, with failures kept on the form

A successful save flashes and redirects to `/settings`, matching every other form
in the application. A rejected submission re-renders the form with the submitted
values and per-field messages — a flash cannot say which field was wrong, and
discarding the input to show a banner loses work.

CSRF applies through the existing middleware; the form carries `csrf_field()`.

### Navigation

Settings becomes the fourth secondary action — after Orphans, before Support
Development — in the single macro that feeds both the desktop header and the
mobile tray. It needs a new glyph in the shared icon set, drawn in the same
24-viewbox stroke style as its neighbors.

`application-shell` and `poster-library` both enumerate this set by name, so both
enumerations change. That is the cost of specs that list rather than describe,
and paying it keeps them true.

### The superseded panel lists relocated variables only

Retired variables keep their sentences on the connection screen, where what
replaced each of them is the subject. The settings screen lists the relocated
ones with the single instruction they share: these are managed here now, delete
them from your compose file. Collapsing the two kinds is exactly what the phase-1
spec forbids.

## Risks / Trade-offs

- **A settings page that fetches libraries makes an outbound Plex call on every
  render** → Bounded by the configured connect timeout, the same exposure the
  import screen already accepts. Not fetched at all when Plex is unconfigured.
- **A wrong site title or a one-day session could be entered and locks nothing,
  but a one-second timeout would make the app feel broken** → The floors and the
  offered ranges are the mitigation; a timeout below 1 is not offered and would
  be floored anyway.
- **`allLibraries()` widens the client interface, and a future caller might use
  it by mistake, defeating exclusions app-wide** → The interface docblock states
  the single legitimate caller, and the spec names the carve-out. A test asserts
  that the import screen and orphan detection still observe no excluded library.
- **The days/MB conversions could round a seeded value into a different stored
  value on first save** → Real but visible: the screen shows the rounded value
  before the user saves it, so nothing changes silently. Documented in the spec.
- **This phase ships a screen while `PLEX_SERVER_URL` is still environment-only,
  so the compose file is not yet empty** → Intended. The plan delivers all four
  phases as one release; nothing here is user-visible until phase 4 lands.

## Migration Plan

No data migration. The store's format is unchanged; this change only writes keys
that phase 1 already defined and seeded. An install upgrading mid-sequence sees a
new screen whose fields already hold the values its compose file set.

Rollback is reverting the change: the store is left as it is, and bootstrap reads
it exactly as phase 1 does.

## Open Questions

None. The remaining unknowns in this plan belong to phases 3 and 4 — the cron
tick interval, and the claim-code flow.
