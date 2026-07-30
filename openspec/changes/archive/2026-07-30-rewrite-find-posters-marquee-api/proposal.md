## Why

Find Posters returns visibly wrong results. Searching a movie returns posters for
sequels, documentaries and unrelated films; searching a TV season returns the show's
own artwork mixed in with the season's; and searching Specials returns nothing at all.
Every failure — an unreachable service, a rejected request, a title that doesn't exist,
a work with genuinely no artwork — reaches the user as the same sentence, "No posters
found for this title."

The cause was never in Marquee's filtering, because Marquee has none: it renders
whatever the poster service returns. The shared legacy endpoint returned every search
hit rather than the requested work — 683 results across 17 different titles for a single
movie query. A new Marquee-only API has been built and verified to fix that at the
source, returning 191 posters for one work in 37 KB instead. This change moves Marquee
onto it and repairs the client-side defects the old contract was hiding.

## What Changes

- **BREAKING** Find Posters targets the new `/marquee/api/v1/posters` endpoint with a
  new request and response contract. The legacy `/api/fetch/posters` endpoint is no
  longer called.
- Marquee identifies itself honestly as `Marquee` (with its version) instead of
  impersonating the Posteria client.
- **The server-time round trip is removed.** The new endpoint tolerates ±24h of clock
  skew, so the extra request per cold search — and the class of failure where a
  drifting clock silently disabled poster search — both disappear.
- **Specials work.** Season searches send an explicit season number sourced from Plex,
  including `0` for Specials, instead of parsing it back out of a display title.
- **Season searches return season artwork only.** The fallback that presented show
  posters as season candidates is removed.
- **Failures are distinguishable.** A title that didn't resolve, a work with no
  artwork, a rate limit, a partial upstream outage and an unreachable service each
  produce their own message.
- Candidate grids load thumbnails where the source provides them, rather than
  full-resolution artwork for every candidate.
- Results render in the order the service ranks them; Marquee does not re-sort or
  re-filter.
- Movie and show searches send the release year as a disambiguation hint.
- **Mediux is removed as a poster source.** It has contributed nothing for the entire
  life of the integration — its host has been unreachable throughout. The accurate
  source list is TMDB, fanart.tv and TheTVDB. Pasting a Mediux image URL into the
  change-by-URL field is unaffected and stays.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-sources`: the poster search contract, what a season search may return, how
  failures are reported to the user, and which services are named as backing the
  search.
- `plex-import`: item records gain the release year and, for seasons, the Plex season
  number, so a poster search can be built from stored facts rather than re-parsed from
  a display title.

## Impact

- `src/Poster/Source/` — `PosterSource` interface and `PosteriaApiPosterSource`
  rewritten; a poster candidate becomes a value object rather than a bare URL string.
- `src/Controller/ChangePosterController.php` — passes real media type, season number
  and year; returns typed failure information instead of a single string.
- `src/Database/` — `plex_items` gains `year` and `season_number` columns via the
  existing idempotent `ensureColumn` migration; `PlexItemRecord` gains both fields.
- `src/Plex/` — season import captures Plex's season index; `PlexItem` year reaches
  the stored record.
- `public/assets/gallery.js`, `templates/gallery.html.twig` — candidates render from
  objects (thumbnail, attribution) and surface distinct error states.
- `README.md`, `openspec/config.yaml` — source list corrected to TMDB / fanart.tv /
  TheTVDB; `POSTER_SOURCE_URL` description updated.
- Configuration: `POSTER_SOURCE_URL` keeps its name and its `https://posteria.app`
  default. No new environment variables.
- External, not blocking: the new endpoint reports TheTVDB as `skipped` until
  `TVDB_API_KEY` is set on posteria.app. Marquee behaves correctly either way — that
  source simply contributes no artwork until it is set.
