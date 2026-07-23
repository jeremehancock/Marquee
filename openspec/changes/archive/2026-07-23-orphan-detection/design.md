## Context

Import records, per Plex rating key, which poster file it stored. An orphan is a
stored poster whose rating key is no longer present in Plex. Detecting this needs
the current set of Plex rating keys, gathered by scanning the libraries.

## Goals / Non-Goals

**Goals:**
- Correctly identify orphans without flagging deliberate manual uploads.
- Bound the Plex scan to the media types that actually have posters.
- Detection and deletion tested against a fake Plex client.

**Non-Goals:**
- No search/filter within the orphan list yet (possible later refinement).
- No automatic deletion; removal is always an explicit user action.

## Decisions

- **Definition.** Only posters with a Plex mapping are candidates. A mapped
  poster is an orphan when its rating key is absent from the current Plex scan
  and its file still exists on disk. Posters without a mapping (manual uploads)
  are never orphans.
- **Bounded scan.** `OrphanService` asks the repository which media types are
  present (`distinctMediaTypes`) and only scans those — e.g. it does not walk
  every show's seasons when no season posters were imported.
- **Detection.** `findOrphans()` builds the current rating-key set from Plex,
  then returns the mapped records whose key is missing. It requires a configured,
  reachable Plex server (otherwise it would wrongly flag everything), raising a
  `PlexException` when Plex cannot be reached.
- **Deletion.** `deleteAll()` re-detects and, for each orphan, deletes the file
  and removes its mapping, returning the count.
- **UI.** An `/orphans` page lists detected orphans with thumbnails and a single
  "Delete all" action; it explains when Plex is unconfigured or unreachable.

## Risks / Trade-offs

- **Full scan cost.** Detection scans the relevant libraries (and seasons when
  season posters exist); acceptable for an explicit, occasional action.
- **Requires Plex reachable.** By design, orphans cannot be computed while Plex
  is down — failing safe rather than deleting real posters.
