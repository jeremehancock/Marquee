## Context

`PosteriaApiPosterSource` builds its request from `PosterQuery` — title, media
type, season number, year — and the service resolves that title to a single work.
The resolver requires a near-exact title match by design: an exact normalised
title scores 60 against a floor of 40, which is what stops "The Matrix" returning
*Reloaded* and making-of artwork. Measured against that same scorer:

| Marquee title | Upstream title | Score | Result |
| --- | --- | --- | --- |
| `Spider-Noir B&W` | Spider-Noir | 22.00 | 404 |
| `Breaking Bad El Camino` | Breaking Bad | 22.00 | must **not** match |
| `Ready or Not 2 Hear I Come` | Ready or Not: Here I Come | 2.00 | 404 |

The first two score identically through the same branch, so no threshold admits
one and rejects the other. Title matching cannot be loosened; an exact identifier
sidesteps it.

The service is adding an optional `tmdb_id` parameter:

```
GET /marquee/api/v1/posters?q=<title>&type=<type>&tmdb_id=<id>
```

- `tmdb_id` is optional, integer ≥ 1. `q` stays **required** — the title is the
  fallback when the identifier is unknown upstream.
- With an identifier supplied, title resolution is skipped entirely.
- For `type=season` the identifier is the **show's**, sent with `season=N`.
- `year` is ignored when an identifier is supplied.
- An identifier unknown upstream degrades to resolving `q` — never to an error.
- A valid identifier whose work has no artwork returns 200 with `posters: []` and
  does **not** fall back.
- The response's `query` object gains `tmdb_id`: what was sent, or null.
- `debug=true` returns `debug.identified_by` ∈ {`tmdb_id`,
  `tmdb_id_unknown_then_title`, `title`}.

Marquee already stores a TMDB identifier per item (`plex_items.tmdb_id`, a
nullable string), captured from Plex's `<Guid>` children at import. Seasons store
their show's identifier; collections and unmatched items store none.

Constraints: no new configuration, no schema change, no coordination window — the
service ignores unrecognised parameters, so this ships independently.

## Goals / Non-Goals

**Goals:**

- Identify a work by its recorded TMDB identifier where Marquee has one, so a
  locally annotated title stops being the thing that decides whether a search
  works.
- Keep every item with no recorded identifier on exactly today's behaviour.
- Notice a stale recorded identifier and repair it, so an item that is wrong once
  is not wrong forever.

**Non-Goals:**

- Free-text or manual title entry for a search. Separate work, unaffected.
- Building against 404 suggestions. Deliberately deferred on the service side.
- Any version negotiation or feature detection against the service.
- Any change to how identifiers are captured at import.
- Filling in a *missing* identifier from a search result — see Decision 5.
- Exposing `debug` as a setting. It stays a manual troubleshooting lever.

## Decisions

### 1. The identifier travels on `PosterQuery`, as a string

`PosterQuery` gains `?string $tmdbId`. The field is a string because that is how
it is stored and how Plex reports it (`tmdb://1726`); converting at the boundary
would mean inventing a sentinel for "not known" at every layer in between.

`PosteriaApiPosterSource` is the only place that decides what a *sendable*
identifier is: non-empty, all digits, greater than zero. Anything else — an empty
string, a non-numeric value from an older row — is treated as absent rather than
sent and rejected with a 400 that reads to the user as "temporarily unavailable".

Alternative considered: parse to `?int` in the controller. Rejected because the
validity rule then lives in the caller, and every future caller has to remember
it. The rule belongs with the contract it satisfies.

### 2. Collections are gated on media type, not on the stored value being null

The specs say a collection carries no identifier, and in practice Plex reports no
TMDB guid for one. But `HttpPlexClient::collections()` requests `includeGuids=1`
and runs the same extraction as movies, so a Plex server that *did* report one
would store it — and sending a collection identifier to a `type=collection`
search is not a contract the service offers.

So the source omits `tmdb_id` for `PlexMediaType::Collection` unconditionally.
Collections stay on the title path, which now works for bare names like
"Star Wars". This is one line and it removes a whole class of "what if Plex
returns something odd" reasoning.

### 3. `year` keeps being sent, unconditionally

The service ignores `year` when an identifier is supplied, so suppressing it would
be a branch with no observable effect — and one that would have to be undone the
moment the identifier turns out to be unknown upstream and the title path takes
over, which is exactly when `year` matters again.

### 4. The source reports the matched identifier; the controller decides what to do with it

`PosteriaApiPosterSource` compares `query.tmdb_id` against `match.tmdb_id` in the
response and puts the result on `PosterSearchResult` as
`?string $correctedTmdbId` — populated **only** when the two differ, null in every
other case. It does not touch the database.

Detection lives in the source because that is where the response shape is known;
repair lives in the controller because that is where the stored record is known.
Giving the HTTP client a repository would make an outbound API client a writer of
local state, which is the kind of coupling that makes the class untestable
without a database.

The comparison reads *both* halves from the response rather than comparing the
response against the stored identifier, because the question is what was **sent**,
not what is recorded. Those come apart: a collection's recorded identifier is
deliberately never sent (Decision 2), so comparing against the record would read
"we matched something other than the identifier we withheld" as a correction and
write a title-resolved identifier into a collection row. Naming the field for what
it means — a correction, not a match — keeps that distinction from being something
each caller has to re-derive.

### 5. Repair replaces a stale identifier; it never fills in a missing one

When an identifier was sent and the matched identifier differs, the service fell
back to the title — the stored identifier is wrong, and the matched one is the
work the title actually resolves to. Marquee records the matched identifier
against the item and logs the correction.

When **no** identifier was sent, the matched identifier is discarded. Storing it
looks like a free upgrade but is not: the next search would skip title resolution
entirely and be permanently pinned to whatever the title happened to resolve to
once, with no mismatch left to detect and nothing to correct it. An item with no
identifier keeps searching by title, which is a path that self-corrects every
time. Only a known-bad identifier is worth replacing, because it cannot get worse.

The write reuses `PlexItemRepository::upsert()` with the record read for the
search and the identifier swapped — the same shape as
`ImportService::backfillTmdbId()`.

### 6. A correction is a repaired cache, not an override of Plex

A full re-import writes `tmdb_id` from Plex again, so a corrected identifier is
overwritten if Plex still reports the wrong one and the item's artwork changes.
That is accepted: Plex remains the source of truth for the mapping, the next
search detects the mismatch again and repairs it again, and the alternative — a
"corrected, do not overwrite" flag — is a schema change and a permanent divergence
from Plex to solve a problem that costs one extra request to re-fix.

### 7. Empty results are never retried by title

A valid identifier whose work has no artwork returns 200 with `posters: []`, which
maps to `NoArtwork` and the existing "found, but no posters are available"
message. Marquee has no retry today and must not gain one here; retrying by title
would defeat the identifier and re-open the "Breaking Bad El Camino" case.

### 8. `debug` stays out of the request

`debug.identified_by` is how a maintainer confirms the parameter is arriving. It
is a manual, one-off check against the live service, recorded as a verification
task, not a runtime flag or a logged field.

### 9. A season identifier round-trips unchanged — confirmed, not assumed

The service confirmed from production that `match.tmdb_id` on a season search is
the **show's** id: for `q=Breaking Bad&type=season&season=2` it returns `1396`,
which is Breaking Bad the show, with the season carried only as
`match.season.number`. No season-level id appears anywhere in the response; TMDB
has them internally and this API never surfaces one.

So the value round-trips: what comes back in `match.tmdb_id` is exactly what to
send as `tmdb_id` on the next season search, paired with `season=N` — same value,
same field name, both directions, no transformation. A season request and a show
request for the same series therefore share an id, and `type` plus `season` is
what distinguishes them. This is what makes Decision 5's repair correct for
seasons without a special case: the id written back to a season row is a show id,
which is what that row is defined to hold.

Confirmed on both the title path and the id path, and it predates this change.

One trap that comes with it, avoided here: `match.title` is the **show's** title,
not the season's — it reads "Breaking Bad", not "Season 2". Marquee reads only
`match.tmdb_id` and renders nothing from `match`, so a season's candidates stay
labelled from the stored Plex display title. Anything that later renders
`match.title` in a season context needs `match.season.name` beside it or it will
look like the wrong work was matched.

## Risks / Trade-offs

- ~~**`match.tmdb_id` for a season search may not be the show's identifier.**~~
  Resolved — see Decision 9. The write-back stays defensive regardless: it only
  ever *replaces* an existing identifier, and only when the matched value is a
  positive integer.

- **A wrong-but-known identifier now steers the search completely**, where before
  a good title could rescue it. → The service falls back to the title whenever the
  identifier is unknown upstream, which covers deleted and merged TMDB entries;
  Decision 5 repairs exactly that case. An identifier that is valid upstream but
  points at the wrong work is undetectable from the response and stays wrong — but
  it was already wrong in the mapping, and it comes from Plex's own match.

- **The service may not have deployed the parameter yet.** → Unrecognised
  parameters are ignored, so the search behaves exactly as it does today until it
  lands. Nothing is gated on a version.

- **Items imported before identifiers were captured have none.** → They search by
  title as they do today, and gain an identifier on the next import through the
  existing skip-path backfill. No migration here.

## Migration Plan

Ship on `dev`, validate the `:dev` image against a live Plex library and the live
service, then release. There is no data migration: the column, the captured
identifiers and the backfill all already exist. Rollback is a redeploy of the
previous image; a repaired identifier written by a newer build is a valid
identifier that an older build simply reads and ignores.

## Open Questions

None. The one open question — whether `match.tmdb_id` is the show's identifier on
a season search — was answered by the service side from production and is recorded
as Decision 9.
