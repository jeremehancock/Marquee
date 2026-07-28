## Why

Deleting a poster from the gallery removes the image file but leaves its Plex
item→file mapping row behind. That stale row is invisible until the same Plex
item is recreated and re-imported, at which point the Orphans page can show two
identical entries (same title, same poster) for what the user sees as one thing.
The mismatch is confusing and makes orphan cleanup feel untrustworthy.

## What Changes

- A regular gallery delete now also removes every Plex item mapping row that
  points at the deleted file, so deleting a poster fully forgets it — no
  dangling mapping survives to resurface later.
- Orphan detection now self-heals: when a mapping row's poster file is already
  gone, the scan prunes that row instead of silently skipping it. This clears
  rows already stranded by the previous delete behavior on existing installs.
- Together these guarantee the Orphans page never lists two entries backed by
  the same poster file.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `poster-library`: deleting a poster must also remove any Plex item mapping
  rows for that poster, not just the image file.
- `orphan-detection`: the orphan scan must prune mapping rows whose poster file
  no longer exists, rather than ignoring them.

## Impact

- Code:
  - `src/Database/PlexItemRepository.php` — new method to delete mapping rows by
    `(category, filename)`.
  - `src/Poster/PosterLibrary.php` / `src/Controller/PosterController.php` —
    delete path also clears the mapping.
  - `src/Plex/Orphan/OrphanService.php` — `findOrphans()` prunes records with a
    missing file.
- Data: prunes stale `plex_items` rows; no schema change.
- Tests: `tests/Unit/Plex/OrphanServiceTest.php`, `tests/Functional/OrphanTest.php`,
  and poster-library delete coverage.
- No API, dependency, or configuration changes.
