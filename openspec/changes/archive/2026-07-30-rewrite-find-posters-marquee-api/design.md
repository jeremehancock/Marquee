## Context

`PosteriaApiPosterSource` currently calls the legacy shared endpoint
`posteria.app/api/fetch/posters`, which returns one result object per poster with the
item's metadata repeated on each, mixes works together (683 results across 17 titles for
`?movie=The+Matrix`), and attaches a `season` object to nearly every result whose
top-level `poster` is still the show's artwork.

Marquee reads almost none of that payload. Its entire consumption surface is `success`,
`results`, `results[].poster` and `results[].season.poster`, and `find()` returns
`list<string>` — bare URLs. Every failure path funnels through
`catch (Throwable) { return []; }`, so the controller cannot tell a 401 from a timeout
from a genuinely empty result, and the user always sees "No posters found for this
title."

A replacement endpoint, `posteria.app/marquee/api/v1/posters`, has been built and
verified. Its contract is recorded in `marquee-api-handoff-notes.md` in the Posteria.app
repo, which is the authority for anything this document does not restate. The legacy
endpoint is untouched and keeps working, so there is no deadline pressure and no dead
window.

Constraints:

- `POSTER_SOURCE_URL` must keep working as a configuration name; users have it set.
- No new environment variables in Marquee.
- Marquee is early alpha, so no client-side backward compatibility with the old
  response shape is required. Existing **user data** (their SQLite database) still must
  not break.

## Goals / Non-Goals

**Goals:**

- Move Find Posters onto the new endpoint and its contract.
- Make search failures distinguishable and actionable for the user.
- Fix Specials, which currently returns nothing.
- Stop presenting show artwork as season candidates.
- Send the facts the endpoint can use to disambiguate (year, explicit season number).
- Load candidate grids from reduced-size images where the source supplies them.
- Remove Mediux as a named poster source.

**Non-Goals:**

- Any client-side filtering, ranking or re-ordering of candidates. The whole point of
  the new endpoint is that this belongs server-side; re-introducing it in Marquee would
  recreate the problem in a place with less information.
- Exposing `sources`, `limit` or `debug` as user-facing settings.
- Batch or bulk poster search. Find Posters stays one item at a time.
- Changing the change-by-URL or change-by-upload paths.
- Re-adding Mediux. That is blocked on a working credential on the service side.

## Decisions

### 1. `find()` returns a result object, not a list of URLs

`PosterSource::find()` becomes:

```
find(PosterQuery $query): PosterSearchResult
```

with `PosterQuery` carrying title, media type, season number and year, and
`PosterSearchResult` carrying a list of `PosterCandidate` plus an outcome.

Alternative considered: keep `list<string>` and throw typed exceptions for failures.
Rejected because the `partial` case is a success *and* a warning simultaneously — it
returns candidates and reports degraded providers. An exception cannot express that, and
a sentinel empty list is what caused the current problem.

### 2. Outcome is a typed enum, not an HTTP status or a message string

`PosterSearchOutcome`: `Ok`, `Partial`, `NoMatch`, `NoArtwork`, `RateLimited`,
`Unavailable`. The source class maps transport reality onto it; the controller maps it
onto user-facing text. Nothing outside the source class sees an HTTP status.

Mapping:

| Endpoint response | Outcome |
| --- | --- |
| 200, candidates present, no `code` | `Ok` |
| 200, candidates present, `code: partial` | `Partial` |
| 200, `posters: []` | `NoArtwork` |
| 404 `no_match` | `NoMatch` |
| 429 `rate_limited` | `RateLimited` |
| 503 `upstream_unavailable` | `Unavailable` |
| 401, 400, 405, transport failure, unparseable body | `Unavailable` |

401/400/405 collapse into `Unavailable` deliberately: each means Marquee sent something
the endpoint rejected, which is a bug in Marquee or a service change, not something the
user can act on. They must be logged with the real status so they are diagnosable, and
never silently swallowed as they are today.

`code` is **not** a failure marker — a `partial` response has `success: true`. Branch on
`success` and HTTP status, never on the presence of `code`.

### 3. No client-side de-duplication

The endpoint de-duplicates within a response. Marquee's `in_array` check goes away.

Note that the same URL can legitimately appear in both a show response and a season
response, because TMDB files some images under both scopes. That is provider
classification, not leakage, and Marquee must not try to reconcile across requests.

### 4. Drop the server time-sync round trip

The new endpoint tolerates ±24h of clock skew and accepts future timestamps, so
`timeOffset()`, the `timeOffset` property and the `/api/time.php` call are all deleted.
`X-Client-Info` becomes base64 of `{name: "Marquee", version: <VERSION>, ts: <now ms>}`.

`name` must be exactly `Marquee`, case-sensitive; `Posteria` is rejected by the new
endpoint. `version` is logged, never validated, and reuses the existing version helper
in `bootstrap.php`. No `?key=` is sent.

This removes a real failure class: a Marquee host with a drifting clock (a Pi without an
RTC, a resumed VM) currently gets a 401 that surfaces as "No posters found."

### 5. `POSTER_SOURCE_URL` stays a base URL

Default stays `https://posteria.app`; the source class appends
`/marquee/api/v1/posters`. Users keep their existing setting, and the path stays a
code-level detail rather than something users must get right.

Alternative considered: make the variable the full endpoint URL. Rejected — it would
silently break every existing configuration, for no benefit.

### 6. `year` and `season_number` are persisted, not re-derived

`plex_items` gains both via the existing idempotent `ensureColumn` helper.

`season_number` is **nullable**, not a defaulted integer. `0` is a real season number
(Specials), so no numeric sentinel can mean "not applicable" — `NULL` expresses it
precisely. `year` is `INTEGER NULL` on the same reasoning applied loosely; a year of `0`
would be meaningless anyway.

Season numbers come from Plex's `index` attribute on the season `Directory`, which is
authoritative (`index="0"` for Specials). This replaces regex-parsing
`"Show - Season 2"` back out of `displayTitle()`.

Every `!== null` check must be explicit. `if ($season)` and `if (season)` are both false
for `0`, and that single mistake would silently reintroduce the Specials bug this change
exists to fix.

### 7. A transitional fallback for season number

A user whose database predates this change has `season_number = NULL` on every row until
they re-import. Requiring a re-import before season search works again would be a silent
regression, so when `season_number` is `NULL` and the item is a season, the source falls
back to deriving the number from the title — **including** the Specials and `S00` cases
the current regex misses.

This is explicitly transitional. It can be deleted once auto-import has cycled through
deployed installs, and it is marked as such in the code.

Alternative considered: require a re-import. Rejected — it breaks a working feature for
existing users with no visible prompt to fix it.

### 8. `sources` and `limit` are omitted from requests

The endpoint defaults to all available sources and a limit of 200, which is more than
the grid needs. Omitting both keeps the request minimal and avoids inventing
configuration nobody asked for. `debug=true` is not sent from application code.

If a per-source toggle is ever wanted, it belongs in a later change with its own UI.

### 9. Candidates carry optional fields; absence is normal

`thumb`, `width`, `height`, `language` and `score` are **omitted, not null**, when a
source does not supply them. `thumb` presence varies by source — TMDB and TheTVDB
provide one, fanart.tv does not — so the grid falls back to `url` per candidate. Only
TMDB posters carry `score`.

`PosterCandidate` therefore has nullable accessors and the grid resolves
`thumb ?? url`. Nothing may assume a key exists.

### 10. `providers` is read, not enumerated

The `providers` map's keys are whichever sources exist, and the token names differ from
the map keys (`fanart` → `fanart.tv`, `tvdb` → `thetvdb`). Marquee does not iterate it
expecting a fixed key set and does not use it for control flow; the `partial` code
already carries the only decision. It is logged for diagnosis.

`skipped` means "not available here, from any cause" — including a source with no
credential configured on the service. That is not an error and must not be surfaced as
one. In particular TheTVDB reports `skipped` until `TVDB_API_KEY` is set on
posteria.app; Marquee simply gets fewer candidates.

### 11. Mediux is removed as a *source*, not as a URL host

Remove Mediux from the source list in the class docblock, `openspec/config.yaml`, and
the `poster-sources` spec Purpose.

**Keep** the "also supports Mediux URLs" hint on the change-by-URL field in
`gallery.html.twig`. That path downloads whatever image URL a user pastes, from any
host, and Mediux URLs still work there. It is `poster-editing`, not a poster source, and
removing it would take away working functionality. This distinction is the easiest thing
to get wrong while "stripping Mediux."

Marquee has no "powered by" attribution UI today — the only user-visible source naming
is in `README.md`, which names posteria.app without enumerating providers, so it needs
no source-list edit.

## Risks / Trade-offs

- **`if ($season)` treating Specials as absent** → the single highest-value test in this
  change. Cover season 0 explicitly at every layer: query building, controller, repository
  round-trip, and the JS request.
- **Existing installs have no `season_number` until re-import** → Decision 7's
  transitional fallback, with the Specials case fixed in it.
- **Nullable columns widen the `PlexItemRecord` contract** → PHPStan level 10 will force
  every read site to handle `null`; that is the desired outcome, not an obstacle.
- **The endpoint caches for 24h, keyed on the resolved work** → a poster added upstream
  today may not appear until tomorrow, and re-phrasing the query will not bypass it.
  Nothing to do in Marquee; worth knowing before chasing a "missing poster" report.
- **Rate limit is 60/min per IP** → Find Posters is user-initiated, one item at a time,
  so this is unreachable in normal use. Handled as `RateLimited` if it ever is hit.
- **The endpoint is unreleased at the time of writing** → the handoff notes state it is
  built and verified but not yet committed or deployed. Implementation can proceed
  against the documented contract, but final verification needs it deployed. If it is
  still unavailable when tests are written, fixture tests carry the change and the live
  check is deferred.
- **Removing client-side filtering means the client is only as good as the endpoint** →
  accepted, and the point. It also means a future accuracy regression is diagnosable in
  one place instead of two, with `debug=true` available as the tool.

## Migration Plan

1. Schema columns land first via `ensureColumn`; they are additive and safe on every
   boot.
2. Import writes the new facts; existing rows gain them on the next import of that
   library.
3. The source class and controller move to the new contract behind the same
   `POSTER_SOURCE_URL` setting.
4. Frontend consumes candidate objects and the new outcome states.
5. Docs and the source list are corrected in the same commit as the code.

No rollback step is needed beyond reverting the commit: the legacy endpoint stays live
and unmodified, so a revert restores a working state without any service-side action.

## Open Questions

- None blocking. The handoff notes resolved the contract; the only external unknown is
  when `TVDB_API_KEY` is set on posteria.app, which changes how many candidates come
  back but not Marquee's behavior.
