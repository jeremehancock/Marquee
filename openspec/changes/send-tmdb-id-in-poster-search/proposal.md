## Why

Find Posters fails for a whole class of items whose Plex title does not match the
title upstream — "Spider-Noir B&W", "Ready or Not 2 Hear I Come" — and the search
service cannot fix that by loosening its title matching, because the same
loosening would make "Breaking Bad El Camino" return *Breaking Bad* artwork.
Marquee already records each item's TMDB identifier at import; sending it turns
those failures into exact hits without weakening anything for everyone else.

## What Changes

- Poster searches for movies and shows send the item's recorded TMDB identifier
  alongside the title. The title is still sent and is still what the service falls
  back to.
- Season searches send the **show's** identifier together with the season number,
  which is how the service addresses a season. There is no season-level identifier.
- Collection searches send no identifier. A Plex collection is a local grouping
  with no upstream record, so it stays on the title path.
- Items with no recorded identifier search exactly as they do today.
- When the response shows the service resolved a *different* work than the
  identifier sent — the sign of a stale stored identifier — Marquee records the
  identifier the service actually matched, so the item stops being wrong on the
  next search.

Not breaking. The identifier is optional to the service, and a service that does
not yet understand it ignores it, so this can ship ahead of the service change and
starts working when that lands. No version check, no coordination window.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-sources`: a search identifies the work by its recorded TMDB identifier
  where one exists, rather than by title alone; a stale recorded identifier is
  corrected from the search result.

## Impact

- `src/Poster/Source/PosterQuery.php` — carries the TMDB identifier.
- `src/Poster/Source/PosteriaApiPosterSource.php` — sends `tmdb_id`, and reads the
  identifier the service matched back out of the response.
- `src/Poster/Source/PosterSearchResult.php` — carries the matched identifier so
  the correction can be made outside the HTTP client.
- `src/Controller/ChangePosterController.php` — supplies the identifier from the
  stored Plex item mapping and applies the correction.
- `src/Database/PlexItemRepository.php` — records a corrected identifier.
- No new configuration, no database migration, no user-facing UI change. The
  recorded identifier and the `tmdb_id` column already exist.
- External: the posteria.app Marquee API's optional `tmdb_id` parameter. Marquee
  does not depend on it being deployed.
