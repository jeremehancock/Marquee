## Why

When a user corrects a bad match in Plex — the "Fix Match" feature — the item
keeps its rating key but becomes a different work: new title, new year, new TMDB
id, usually new artwork. Marquee never revisits what it recorded at first import,
so the poster keeps the old work's filename forever. Its caption updates but its
sort position and its search text do not, which puts the poster under the wrong
letter and makes it unfindable by any query for its real name. A user reported
this as the show "disappearing completely", recoverable only by deleting
`/config` and starting over.

The same staleness is worse when the poster is locked or custom, because then the
artwork does not change either. Import skips the item before it ever compares
anything, so the title, the year and the TMDB id all stay wrong indefinitely —
and a wrong TMDB id sends Find Posters to the wrong work every time.

## What Changes

- On re-import, when a mapping already exists for an item's rating key, Marquee
  compares the item's current title, release year and TMDB id against what it
  recorded. When any of them has moved, it refreshes the mapping.
- When the recorded title or year changes, the poster file is renamed to the
  name the item would be given today, through the same uniqueness rule a first
  import uses, so the poster sorts and searches under its real name.
- This reconciliation runs even when the artwork is unchanged and the poster
  download is skipped — it is the skip path, not the download path, where a
  re-matched item most often lands.
- Steady state is unaffected: an item whose facts have not moved writes nothing
  and renames nothing, so a scheduled import over an unchanged library costs
  what it costs today.

Not a breaking change. No configuration, no migration, no user action: the next
import heals whatever a Fix Match left behind.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `plex-import`: the "Plex item mapping" requirement gains reconciliation — a
  mapping's recorded facts are refreshed when the Plex item's own facts change,
  not only when they were previously unknown. The "Safe, unique filenames"
  requirement gains a rename: a stored poster's filename follows its item's
  title, and the uniqueness rule that protects a first import protects the
  rename too.

## Impact

- `src/Plex/Import/ImportService.php` — the skip decision and the existing
  `backfillMissingFacts()` null-only refresh.
- `src/Poster/PosterStorage.php` and `src/Poster/FilesystemPosterStorage.php` —
  need a rename operation that honours the existing unique-filename rule.
- `src/Database/PlexItemRepository.php` — no schema change; `plex_items` is the
  only table that stores a filename, so a rename is contained.
- Fixes the downstream symptom in `search` and `poster-library` (a poster
  sorting and matching under a stale name) without changing either capability's
  own requirements — both correctly read the filename; the filename was wrong.
- Unblocks `poster-sources`: a healed TMDB id stops Find Posters resolving a
  re-matched item to the work it used to be mistaken for.
- Out of scope: seasons, which Plex re-keys on a re-match. The old season
  mapping becomes a genuine orphan and `orphan-detection` already reports it
  correctly; the new season imports under its correct name on its own.
