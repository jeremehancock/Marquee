## Context

Posters are files linked (by rating key) to Plex items. "Editing" a poster means
replacing its file and, since Plex is the source of truth, pushing the new image
to Plex and locking it. Three sources feed a change: a local file, a URL, or a
candidate chosen from the posteria.app poster search.

## Goals / Non-Goals

**Goals:**
- One coherent per-poster edit flow that replaces in place and pushes to Plex.
- Reuse the export path (upload + lock + overlay-label) already built.
- Faithful posteria.app poster search, replicated from the original app.

**Non-Goals:**
- No standalone "add poster" any more.
- No client-side image editing/cropping.

## Decisions

- **Change = replace + push.** `ChangePosterService` validates the new image
  (real type via `getimagesize`, size limit), overwrites the exact file via
  `PosterStorage::replace`, then calls the existing `PlexExportService` to upload
  and lock — but only when the poster is linked and Plex is configured. It
  reports whether it reached Plex so the UI can be honest.
- **Fetch from Plex.** `PlexClient::itemPoster(ratingKey)` reads the item's
  current thumb from `/library/metadata/{ratingKey}` and downloads it;
  `ChangePosterService::fetchFromPlex` replaces the local file with it (a pull,
  no push).
- **Find Posters.** `PosterSource` is an interface; `PosteriaApiPosterSource`
  calls `{POSTER_SOURCE_URL}/api/fetch/posters` with the type-specific query
  (`movie=` / `q=&type=tv[&season=]` / `type=collection`) and the `X-Client-Info`
  header (time-synced, as the original did), then extracts each result's poster
  URL (`original`/`large`/`medium`/`small`, or `season.poster` for seasons). The
  media title/type/season come from the stored Plex mapping, looked up by
  filename server-side.
- **UI.** One shared, Alpine-driven Change-poster modal operates on the poster
  chosen from a card's hover overlay. Download and Copy URL are client-side.
  Import shows a full-screen overlay while it runs.

## Risks / Trade-offs

- **posteria.app cannot be tested here.** The client is ported from the original
  and unit-tested against a mocked response; live behavior (and the exact
  `X-Client-Info` contract) may need a small adjustment after real testing.
- **Server-side URL fetch** (URL change and applying a found poster) is the same
  trust model as the previous URL upload — user-initiated fetches of image URLs.
