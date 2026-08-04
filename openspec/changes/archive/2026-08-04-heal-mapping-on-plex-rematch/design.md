## Context

`ImportService::importItem()` treats a mapping as written-once. Two branches
decide an item's fate, and neither revisits the recorded title:

```
  importItem($item)
    │
    ├─ existing && thumb unchanged && file present
    │     └─▶ backfillMissingFacts()   ← null → known only
    │         return skipped               never compares the title
    │
    └─ download poster
          ├─ existing  → storage->replace(existing->filename)   ← keeps the old name
          └─ new       → storage->store(deriveFilename($item))  ← the only place a
                                                                  name is derived
          upsert(...)  ← refreshes title/year/tmdb, but the filename is whatever
                         the branch above chose
```

After a Plex "Fix Match" the rating key survives but the work behind it changes.
Both branches then misbehave, in different ways. Reproduced against the real
services with a fake Plex client:

- **Download branch.** `title` becomes "The Shield" and the caption follows, but
  the file stays `Marvel_s_Agents_of_S.H.I.E.L.D._TV_Shows.png`. The gallery sorts
  by `Poster::sortKey()` and search matches `Poster::title()`, both derived from
  the filename, so the poster sits under M and a search for "the shield" — or even
  "shield", since `S.H.I.E.L.D.` normalises to `s h i e l d` — returns nothing.
- **Skip branch.** A locked or customised poster keeps its `thumb`, so the import
  returns before comparing anything. Title, year and TMDB id all stay wrong
  indefinitely, and the stale TMDB id keeps `poster-sources` resolving the item to
  the work it used to be mistaken for.

Constraints this design inherits:

- The skip path is the hot path. Its existing comment states the rule plainly: a
  blanket refresh there would rewrite every row on every scheduled import, and
  that is unacceptable. Steady state must stay at zero writes.
- `plex_items` is the only table storing a filename, so a rename is contained. No
  schema change is required.
- Filenames must remain unique within a category; `store()` already guarantees
  this for first imports and a rename must not bypass it.

## Goals / Non-Goals

**Goals:**

- A re-matched item's recorded title, year and TMDB id converge on what Plex
  reports, on the next ordinary import, with no user action.
- Its poster file is renamed so the gallery sorts it and search finds it under
  the name it now displays.
- Both hold whether or not the artwork changed.
- An unchanged library still writes nothing and renames nothing.

**Non-Goals:**

- Seasons re-keyed by a re-match. Plex destroys and recreates season metadata
  items, so the new season imports under a correct name on its own and the old
  mapping becomes a genuine orphan that `orphan-detection` already reports
  correctly. Verified; no change needed.
- Detecting a re-match as an event. Marquee has no signal for it and does not need
  one — reconciling observed facts covers every cause of drift, including a
  library rename or a title corrected upstream.
- Backfilling posters whose filename drifted before this change lands by any
  route other than the next import. The next import is enough.
- Changing how the gallery sorts or how search matches. Both correctly read the
  filename; the filename was wrong.

## Decisions

### Reconcile before the skip decision, not after the download

The reconciliation moves into a single step that runs for every item with an
existing mapping, ahead of the branch that decides whether to download. The skip
branch is where a re-matched-but-locked poster lands, so putting the work only on
the download path would leave the worse half of the bug in place.

Alternative considered: reconcile after the download and separately inside
`backfillMissingFacts()`. Rejected — two call sites for one rule is how the
current null-only asymmetry arose in the first place.

### Widen `backfillMissingFacts()` from "null → known" to "differs → refresh"

The existing method already carries the right shape: compute the values that
should be recorded, compare, return early when nothing moved, and otherwise write
one upsert. Only the merge rule changes.

```
  per fact:   recorded  |  Plex reports  |  result
              ----------+----------------+---------------------------
              null      |  known         |  take Plex's   (today)
              known     |  differs       |  take Plex's   (new)
              known     |  null          |  keep recorded (new, explicit)
              known     |  same          |  no write
```

Keeping a recorded fact when Plex now reports nothing is deliberate. A transient
gap — an agent mid-refresh, a server that stops reporting guids — must not erase
a year or a TMDB id the app depends on. Losing a known fact is worse than holding
a stale one, and the next import that does report a value corrects it.

The early return survives unchanged, so the steady-state cost stays zero writes.
The comparison itself reads values already in memory.

Rename the method to reflect what it now does; `backfill` describes only the
null-filling half.

### Trigger the rename off title and year, not off a re-derived filename

`deriveFilename()` needs the poster bytes to pick an extension, which the skip
path does not have. Comparing the recorded `title` and `year` to the item's
current ones needs neither, and it is the more direct question: the filename is a
function of those two plus the library title.

The rename derives the new name from the item and reuses the file's existing
extension. That is correct by construction — a metadata change does not change
the stored image, so its format is unchanged.

Alternative considered: always re-derive and compare the full filename. Rejected;
it forces a download on the skip path purely to learn an extension the file
already has.

### Add `PosterStorage::rename()` rather than reusing `store()`

`store()` moves a source file in and returns the name it settled on. A rename is
a move within the category, and routing it through `store()` would mean handing
the storage a path it already owns. A dedicated method keeps
`FilesystemPosterStorage` the only class that knows about directories and
filename safety, which is the invariant its docblock states.

The method sanitises the desired name and resolves collisions through the same
`uniqueFilename()` helper `store()` uses, then returns the name it actually used
— which may differ from the one requested. The caller records the returned name,
never the requested one.

### The rename and the mapping update are one operation

A rename that does not update `filename` strands the poster: the mapping points
at a path that no longer exists, and the next import's skip check fails its
`exists()` test and re-downloads forever. So the flow is: rename first, take the
name the storage returns, then write that name into the same upsert that carries
the corrected facts. One write, and the mapping is never briefly wrong.

### A failed rename degrades to the status quo

If the rename fails — a permission problem, a filesystem that will not move the
file — the item keeps its existing filename and the facts are still corrected.
That is strictly better than today and leaves the poster reachable. It must not
fail the import: `importItem()` already catches `Throwable` per item and counts a
failure, and a cosmetic rename is not worth burning an item's import.

## Risks / Trade-offs

- **A renamed poster's URL changes, so any open page holding the old filename
  gets a 404 on its next action** (change-poster, delete, send-to-Plex) → The
  window is one import against a stale tab, and the failure is a visible error
  rather than silent corruption. A reload fixes it. Not worth a redirect layer.

- **A user who deliberately renamed a poster on disk loses that name on the next
  import** → In practice they cannot: renaming on disk without updating
  `plex_items` already breaks the mapping today, and the file reads as an orphan.
  There is no supported path for a hand-picked filename, so none is being taken
  away.

- **The rename fires on any title change, not just a re-match** — a Plex library
  renamed, a title edited by hand, an agent refresh that changes capitalisation →
  This is correct behaviour, not a risk to mitigate: the filename should track the
  title in all of those cases. The one cost is churn on a library whose agent
  rewrites titles, which is bounded by one rename per actual change.

- **Comparing three facts per item adds work to the hot skip path** → All three
  are already loaded into memory for the existing skip check; the comparison is
  free relative to the Plex request that fetched them.

- **Case-only renames on a case-insensitive filesystem** (a title recapitalised)
  → `uniqueFilename()` would see the existing file and pick a `-1` suffix, giving
  a spurious rename. The rename should treat "same file, different case" as a
  collision with itself rather than with another item. Worth an explicit test.

## Migration Plan

None required. No schema change, no configuration, no data migration. Existing
mappings heal on the next ordinary import — scheduled or manual — and mappings
that are already correct are untouched.

Rollback is a straight revert. Posters renamed before a rollback keep their new
names and stay correctly mapped, because the mapping was updated with them; the
reverted code simply stops renaming.

## Open Questions

- Whether Plex always preserves a *show's* rating key across "Fix Match" is
  inferred, not confirmed — the reporting user's "a normal import does not bring
  it back" only holds if it does, since a new rating key would take the
  `store()` branch and produce a correctly named file. Either way the outcome is
  correct: preserved keys are healed by this change, new keys already work and
  leave an orphan that `orphan-detection` reports. No design decision hangs on
  the answer.
