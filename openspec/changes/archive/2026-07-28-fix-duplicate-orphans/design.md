## Context

Posters map to Plex items via the `plex_items` table, keyed by `rating_key`.
Two delete paths exist and behave asymmetrically:

- **Orphan delete** (`OrphanService::delete` / `deleteAll`) removes the image
  file *and* the mapping row (`deleteByRatingKey`).
- **Regular gallery delete** (`PosterController` → `PosterLibrary::delete` →
  `FilesystemPosterStorage::delete`) removes only the image file. The mapping
  row survives.

A surviving mapping row is normally invisible: `OrphanService::findOrphans()`
skips any record whose file is missing (`!$this->storage->exists(...) → continue`).

The bug chains two facts. First, a recreated Plex collection/item gets a **new**
`rating_key`, so re-import cannot match the stale row by key and instead inserts
a second row. Second, `FilesystemPosterStorage::uniqueFilename()` dedupes only
against files on disk, not the DB — since the original file was deleted, the new
import reuses the *identical* filename. Now two rows (old dangling + new live)
reference the same file. When both rating keys later go missing from Plex, both
surface as orphans: two entries, same title, same poster.

Constraints: SQLite via PDO, thin controllers → services, PHPStan max + PHPUnit
must pass. No schema change is needed.

## Goals / Non-Goals

**Goals:**
- A regular gallery delete leaves no residual mapping for the deleted poster.
- Orphan detection prunes mapping rows whose file is already gone, cleaning up
  rows stranded by the old delete behavior on existing installs.
- The Orphans page never shows two entries backed by the same poster file.

**Non-Goals:**
- Changing how filenames are derived or made unique on import.
- Migrating or reconciling mappings at import time.
- Any schema or table change.
- A one-off migration script; the self-heal in detection covers existing data.

## Decisions

### Delete by `(category, filename)`, not by rating key
`PlexItemRepository` gains `deleteByCategoryAndFilename(string $category,
string $filename): void` that removes *all* rows matching the pair. The gallery
delete path calls it after the file is removed.

- Why the pair, not the rating key: at the gallery/controller layer we hold the
  category and filename, not a rating key. More importantly, the whole point is
  that multiple rows can share one filename — deleting all of them is exactly
  the cleanup we want, and it is idempotent when zero rows match (e.g. a
  never-imported poster).
- Alternative considered — look up the row, delete by its rating key: would
  leave a sibling row behind in precisely the duplicate case we are fixing.

### Wire the mapping cleanup through `PosterLibrary::delete`
`PosterLibrary::delete()` already owns "delete a poster." It will delete the
file via storage and, on success, clear the mapping via the repository, keeping
the controller thin and giving one place that fully forgets a poster. The
repository is already a collaborator available to inject.

- Order: delete file first, then clear the mapping. If the file delete fails we
  return false and leave the mapping untouched (nothing was removed).

### Self-heal + dedupe in `findOrphans()`
Detection is reworked into two passes over the mappings, grouping by
`(category, filename)` so a poster is judged once even when several mappings
point at the same file:

1. **Prune missing files.** Where `findOrphans()` previously did `continue` for
   a record whose file is missing, it now prunes that record
   (`deleteByRatingKey`). It is stale (no poster to clean up) and should not
   linger. It also records which surviving files are still backed by a live Plex
   item.
2. **List each orphaned file once.** A file is an orphan only when *no* mapping
   for it is live. It is listed once; any further absent mappings for the same
   file are redundant duplicates and are pruned.

This is why the missing-file prune alone is not enough: the reported bug leaves
two mappings pointing at one *existing* file (the recreated item re-imported to
the same filename), so both would otherwise surface as orphans. Grouping by file
collapses them, and the live-file check guarantees a file that any live mapping
still backs is never orphaned — so a shared file can never be wrongly deleted.

- This is the retroactive fix: installs that already have duplicate or dangling
  rows get them cleared the next time the Orphans page runs, with no migration
  step.
- Safety: pruning only removes a mapping when its file is absent, or when the
  file is already represented by another orphan entry. A live poster is never
  affected. Detection already requires a reachable Plex server, so this runs in a
  known-good context.

## Risks / Trade-offs

- [Detection now writes to the DB, not just reads] → The write is a narrow,
  idempotent delete of rows whose file is confirmed missing; it cannot remove a
  mapping for an existing file. Covered by a unit test asserting a
  missing-file record is pruned and present-file records are untouched.
- [A concurrent import racing a detection prune] → Worst case a just-pruned row
  is re-created by a legitimate re-import (file present again), which is correct
  behavior; the prune only fires when the file is absent at scan time.
- [`deleteByCategoryAndFilename` could over-delete if filenames collided across
  unrelated items] → Filenames are unique per category on disk, and rows sharing
  a `(category, filename)` are by definition pointing at the same physical
  poster, so deleting them together is the intended semantics.

## Migration Plan

No migration script. Deploy the code; existing dangling rows are pruned lazily
the next time orphan detection runs. Rollback is a straight revert — no data
shape changed.

## Open Questions

None.
