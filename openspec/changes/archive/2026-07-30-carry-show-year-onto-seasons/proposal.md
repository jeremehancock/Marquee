## Why

A stale TMDB identifier on a TV season can permanently self-heal to the **wrong
show**, and once it does the error is undetectable. Seasons are imported with no
release year, so when the poster source falls back to resolving the title, two
shows sharing a title score identically and popularity picks the winner. Marquee
then records that winner's identifier against the season. From that point the
identifier resolves cleanly, the mismatch check can never fire again, and every
future search for that season silently returns another show's artwork.

This is the one case where a missing year corrupts a stored record rather than
merely degrading a single search.

## What Changes

- A TV season is recorded with **its show's release year** at import. The year is
  already known at that moment — it is simply not carried onto the season.
- Existing season mappings that predate this **backfill** their year on the next
  import, on the skip path, without re-downloading an unchanged poster.
- The stale-identifier self-heal **refuses to write** when the search that
  produced the correction carried nothing to tell same-titled works apart. Such a
  correction is a guess, and recording a guess converts a loud, repairable error
  into a silent, permanent one.

These two fixes cover disjoint halves of the same defect and neither is redundant:
carrying the year closes the case where a year is known, and the self-heal guard
closes the case where no year exists at all. The defect is not "seasons have no
year" — it is "a correction was accepted from a search that could not tell two
works apart". Seasons are where that is guaranteed for every item, but a movie or
show whose Plex metadata reports no year sits in exactly the same trap and is out
of reach of the first fix. After the first fix the guard almost never fires for a
season, so it costs nothing on the common path.

No change is needed in the poster source. It already accepts a release year on a
season search and already interprets it as the show's first-air year — the
plumbing is waiting for a value Marquee is not sending.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `plex-import`: a season is recorded with its show's release year, and a mapping
  that predates this backfills the year on a later import. The existing
  requirement records a release year only for "a movie or show".
- `poster-sources`: the stale-identifier correction is not recorded when the
  search carried nothing to disambiguate the title it fell back to.

## Impact

- `src/Plex/HttpPlexClient.php` — `seasons()` passes the show's year instead of
  `null`.
- `src/Plex/Import/ImportService.php` — the skip path's backfill also writes a
  year that is absent, alongside the identifier it already backfills.
- `src/Controller/ChangePosterController.php` — the self-heal declines to write an
  undisambiguated correction.
- No schema change: the `year` column already exists and already accepts null.
- No user-visible change: `year` has one consumer besides storage — the poster
  search query. Filenames use it for movies only, and no template reads it.
- No poster source change, and no new request or response field.
