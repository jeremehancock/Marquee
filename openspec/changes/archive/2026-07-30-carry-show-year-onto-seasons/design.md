## Context

A poster search sends the item's title, media type, release year when known, and
the recorded TMDB identifier when known. A season is identified by its **show's**
identifier plus a season number, because a season has no identifier of its own.

`HttpPlexClient::seasons()` constructs every season with `year: null`, and the
`plex-import` spec records a release year only for "a movie or show". The show's
year is in hand at that moment — `seasons()` already reads `$show->title` and
`$show->tmdbId` off the same object.

### How a season row gets corrupted

```
  season row: tmdb_id = 1396 (stale — TMDB no longer knows it)
                    │
                    ▼
  search sends  q=<show title>  season=N  tmdb_id=1396   (no year — the gap)
                    │
                    ▼
  API: id unknown upstream → falls back to resolving q by title
                    │
                    ▼
  two shows share the title, neither has a year to separate them
  → scores tie → popularity picks the winner → the WRONG show
                    │
                    ▼
  response: match.tmdb_id ≠ the id we sent
                    │
                    ▼
  correctStaleTmdbId() writes the wrong id into the season row
                    │
                    ▼
  every later search resolves cleanly. sent == matched, forever.
  ─────────── the mismatch check can never fire again ───────────
```

The last step is what makes this worth fixing. A stale identifier is
self-announcing: it fails to resolve on every search, so it stays visible and
repairable indefinitely. A *wrong but valid* identifier resolves cleanly, so it is
undetectable from the response by design — which is a deliberate property of the
detection rule, not an oversight in it.

### The defect is not "seasons have no year"

The predicate that corrupts a row is:

```
   stale id   AND   no year sent   AND   a same-titled work exists
                          ▲
                   the actual cause
```

Seasons are where the middle term holds for **every** item. But a movie or show
whose Plex metadata reports no year — `intAttr()` returns null when the attribute
is absent — sits in the same trap, and carrying the year onto seasons cannot reach
it. That is why this change is two fixes over disjoint halves rather than one fix
plus a belt-and-braces guard.

## Goals / Non-Goals

**Goals:**

- A season search can tell same-titled shows apart, by sending the show's year.
- Season mappings that predate this acquire their year without a re-import and
  without re-downloading an unchanged poster.
- The self-heal never records an identifier it cannot justify, whatever the media
  type, and whatever the reason the search could not disambiguate.

**Non-Goals:**

- No change to the poster source. It already accepts a year on a season search.
- No new request or response field, and no new API contract.
- No schema change. The `year` column exists and already accepts null.
- Not repairing rows already corrupted by this defect. A wrong-but-valid
  identifier is indistinguishable from a correct one in the response, so nothing
  in Marquee can detect one. A full re-import rewrites the identifier from Plex
  and is the only recovery.
- Not filling in a *missing* identifier. That rule is unchanged and unrelated.

## Decisions

### Send the show's year on a season search — verified against the source, not assumed

The poster source already handles this. In `marquee/api/v1/lib/tmdb.php`,
`marqueeTmdbSearch()` maps `type=season` to `/search/tv` and sends the year as
`first_air_date_year`; `marqueeCandidateFacts()` scores a season candidate off
`first_air_date`. So the year on a season search is already interpreted as the
**show's first-air year**, which is exactly what Plex reports as a show's year.

**No API change is needed. The plumbing is waiting for a value Marquee is not
sending.**

### The fix is decisive, not merely helpful

The source's scorer weights an exact normalised title at 60, adjusts by +20 for an
exact year / +10 off-by-one / −10 wider, and caps popularity's contribution at 5.
For two works sharing an exact title:

| candidate | title | year | popularity | total |
| --- | --- | --- | --- | --- |
| the right show (year Y) | 60 | +20 | ≤5 | 80–85 |
| impostor, year off by 1 | 60 | +10 | ≤5 | 70–75 |
| impostor, year off by 2+ | 60 | −10 | ≤5 | 50–55 |

The worst-case margin once a year is sent is **10**, against a maximum popularity
swing of **5**. Popularity can no longer break the tie. The mechanism that causes
the corruption is disabled, not mitigated — which is why carrying the year is the
root-cause fix and not a heuristic improvement.

### The guard keys off what Marquee sent, never off the source reporting a fallback

The obvious guard — refuse to write when the source tells us it fell back to
resolving the title — is unavailable, for two independent reasons:

1. **It is behind a debug flag.** `identified_by` and the scored candidate list
   live under `debug`, gated on `debug=true` in the source's request parsing.
   Reading it in production would mean Marquee re-deriving the source's own
   confidence from raw scores — the client-side heuristic that this project has
   already ruled out. Accuracy is diagnosed in the source, not re-implemented here.

2. **It is redundant by construction, which is fatal.** A correction only ever
   fires when the identifier we sent was not one the source recognised — and that
   *is* the fallback. The condition is 100% correlated with the correction firing:

   ```
   correction fires  ⟺  sent id ≠ matched id  ⟺  the title fallback happened
   ```

   Guarding on it would not narrow the self-heal. It would **delete** it.

So the guard must be expressed in terms of what Marquee itself put on the wire.
Today that is: a search that sent no release year had nothing to separate
same-titled works, so a correction from it is a guess.

The requirement is written **broadly** — "a search that carried nothing to
disambiguate the title" — rather than narrowly as "no year was sent". No year sent
is the current instance of that condition, not the definition of it; the broad
wording survives the source someday exposing a real confidence signal.

### The guard restores a precondition the existing code already assumes

`correctStaleTmdbId()` justifies itself as: *"A known-bad id cannot get worse,
which is what makes replacing it safe."* That is sound — but only when the
replacement is well-founded. With no year the replacement is a popularity coin
flip, and the trade runs one way only:

| | result | detectability |
| --- | --- | --- |
| keep the stale id | wrong, re-resolves every search | **loud** — still repairable |
| write a guessed id | resolves cleanly forever | **silent** — unrepairable |

Declining to write costs one wrong search result, which the item was already
getting. Writing a guess costs the ability to ever notice. This guard is not a new
principle; it repairs the precondition the existing argument depends on.

### Backfill on the skip path is mandatory, not a nicety

Seasons take `ImportService`'s skip path whenever Plex's artwork version is
unchanged, which is the steady state for an established library. That path
deliberately avoids re-downloading the poster, and today it calls only
`backfillTmdbId()`.

Without a matching year backfill, **existing libraries never get this fix** — only
newly-imported seasons would, and the corruption window would stay open
indefinitely for every season already on disk.

The backfill follows `backfillTmdbId()`'s shape exactly, including its constraint:
**only null → known writes.** A blanket refresh here would rewrite every row on
every scheduled import, where the skip path's entire purpose is to cost almost
nothing. This fires once per item and then never again. The repository upsert
already writes `year = excluded.year`, so the full-import path is correct as it
stands; only the skip path needs the addition.

### Setting a year on seasons has no user-visible effect

`year` has exactly one consumer besides storage: the poster search query.
`ImportService::deriveFilename()` appends a year for **movies only**, so no
filename changes, and no template reads the field. This is why the change can be
made at the import boundary without a display or migration story.

## Risks / Trade-offs

- **An item with no year can no longer self-heal.** → Intended, and the smaller
  loss. Such an item keeps a stale identifier and re-resolves by title on every
  search — the behaviour it had before self-healing existed — and stays detectable
  so a later fix can still repair it. The alternative is a silent permanent error.
  After the year is carried onto seasons this only affects a show Plex reports no
  year for, so it is rare on the common path.

- **Plex's show year could disagree with TMDB's first-air year.** → The scorer
  already absorbs this: an off-by-one year still scores +10, and the table above
  shows the right show wins by 10 even then. A wrong year adjusts a candidate's
  rank; it never disqualifies one, because an exact title match scores far enough
  above the floor that the year cannot push it under.

- **A show whose seasons span many years gets its show-level year on all of
  them.** → Correct by construction, not a compromise. The year exists to resolve
  the **show** during the title fallback; the season is carried separately as
  `season=N`. A per-season air year would be the wrong value to send.

- **Rows already corrupted stay corrupted.** → Out of scope and undetectable from
  the response, as established above. A full re-import rewrites the identifier
  from Plex and is the documented recovery.
