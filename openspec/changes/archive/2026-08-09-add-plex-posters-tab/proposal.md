## Why

A poster a user applied months ago and has since replaced still exists — Plex
keeps every poster ever uploaded to an item and never cleans them up. Marquee
cannot see any of them. If a user loses a poster locally, or simply wants the
one they had before, their only recourse today is Find Posters, which searches
TMDB / fanart.tv / TheTVDB and may never surface that image again.

The posters are already there, on a server Marquee is already connected to. This
change makes them reachable, turning a one-way door into something a user can
walk back through.

## What Changes

- The change-poster dialog gains a fourth tab, **Plex Posters**, listing every
  poster Plex reports for the linked item. Tab order becomes
  Upload | From URL | **Plex Posters** | Find Posters.
- Candidates are split into two labelled groups: **Uploaded to Plex** — the
  user's own history — and **Offered by Plex**, everything else Plex reports for
  the item. Whether Plex already holds a given image decides how applying works,
  but it is not surfaced: a user is choosing a poster, not a mechanism.
- The poster Plex currently has selected is marked in the grid.
- Choosing a candidate uses the existing preview → "Use this poster" → confirm
  flow unchanged, then stores the poster, makes it the item's poster in Plex,
  and locks it — the same end state the other three tabs reach.
- For the two held groups, applying **selects** the poster rather than uploading
  it back. Plex never prunes an item's posters, so uploading one it already
  holds would leave a duplicate behind. Offered artwork is uploaded, because
  Plex does not have that image.
- Held candidate images are served through a signed-token proxy: Plex image URLs
  carry the token, which must never reach the browser. Offered ones are plain
  provider URLs with no credential involved, and load directly.
- The tab reports two failure states in the manner Find Posters already uses:
  Plex holds no posters for this item (final), and Plex could not be reached
  (transient). A poster with no Plex mapping gets a disabled tab with an
  explanation rather than a hidden one.

Not a breaking change: nothing existing behaves differently.

### Out of scope

**Deleting or purging posters from Plex.** Deliberately deferred to a separate
change, for two reasons:

- It is **not established** that Plex exposes a working endpoint to delete an
  individual poster. That must be verified against a real server before any of
  it can be specified. (The *selection* endpoint this change relies on was
  verified that way and works; deletion is a separate question.)
- Purging is far safer to build on top of this change than beside it. Deleting
  from a list the user can see, with the selected poster marked, beats deleting
  blind.

One edge case is already known and should be carried into that change: deleting
the poster Plex currently has selected must be either blocked outright or made
to require a replacement first.

## Capabilities

### New Capabilities

None. This is a second source of poster candidates, not a new kind of thing the
app does.

### Modified Capabilities

- `poster-sources`: gains requirements covering the Plex Posters tab — listing
  an item's posters from Plex, grouping them by provenance, marking the selected
  one, proxying their images without disclosing the token, applying one through
  the existing preview-and-confirm flow, and the tab's outcome and
  no-mapping states. The existing Find Posters requirements are untouched; the
  capability's scope widens from "Find Posters via posteria.app" to poster
  candidates from any source.

## Impact

**New**

- A Plex poster listing service and its value objects (a candidate carries a
  Plex image path, a provenance, and whether it is selected).
- A route serving proxied Plex candidate thumbnails, and a route listing an
  item's Plex posters as JSON.
- A route applying a chosen Plex poster.

**Modified**

- `App\Plex\PlexClient` / `HttpPlexClient` — a `GET` on
  `/library/metadata/{id}/posters`. The path is already used for the `POST` that
  uploads.
- `App\Plex\PlexPosterWriter` — `selectPoster()`, a `PUT` on
  `/library/metadata/{id}/poster?url={posterKey}`.
- `App\Plex\Export\PlexExportService` — `selectInPlex()`, the counterpart to
  `sendToPlex()` for a poster Plex already has: select, lock, and handle the
  Kometa label identically.
- `App\Controller\ChangePosterController` — list and apply actions.
- `App\Poster\Edit\ChangePosterService` — apply a poster from a Plex image path,
  mirroring the existing `fetchFromPlex`.
- `templates/gallery.html.twig` — the fourth tab and its grid.
- `public/assets/gallery.js` — the tab's state, fetch, and grouping.
- `src/Routes.php`, container wiring.

**Precedent being followed, not invented**

`App\Poster\Wall\StreamToken` and `PosterWallController::streamPoster` already
solve proxying a Plex image behind a signed path token. The same shape applies
here.

**Documentation**

`README.md` describes Find Posters as the way to get a different poster; it will
need to name this second route, particularly for the recover-a-lost-poster case.
