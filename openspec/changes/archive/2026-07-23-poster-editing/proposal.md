## Why

Marquee's model is: import all your Plex posters, then refine each one in place.
The gallery must therefore center on per-poster editing, not adding standalone
posters. Updating a poster should push it to Plex and lock it, so Plex uses your
choice. The earlier "Add poster" flow contradicted this and is removed.

## What Changes

- Remove the global "Add poster" action and its endpoints.
- Give every gallery poster an on-hover action stack: Change poster, Send to
  Plex, Fetch from Plex, Download, Copy URL, Full screen, Delete.
- **Change poster** (upload a file, paste a URL, or pick from Find Posters)
  replaces that poster in place and — when it is linked to Plex — uploads it to
  Plex and locks it.
- **Send to Plex** pushes the poster's currently stored image to Plex and locks
  it without changing it first, so a user can re-apply Marquee's copy after Plex
  has drifted.
- **Fetch from Plex** re-pulls the item's current Plex poster into the library.
- **Find Posters** searches the posteria.app API for that specific media and
  offers candidate posters to apply, shown as a clean thumbnail gallery.
- Make the import progress a prominent full-screen overlay.
- Fix stale asset delivery (service worker + mtime cache-busting) so front-end
  changes actually reach the browser.

## Capabilities

### New Capabilities
- `poster-editing`: per-poster change (upload/URL/Find), fetch-from-Plex,
  download, and copy-URL, with automatic push-and-lock to Plex on change.
- `poster-sources`: find candidate posters for a media item via the posteria.app
  API.

### Modified Capabilities
- `poster-upload`: removed as a standalone capability; uploading is now one way
  to change an existing poster, not to add a new one.

## Impact

- New: `src/Poster/Edit/ChangePosterService.php`,
  `src/Poster/Source/{PosterSource,PosteriaApiPosterSource}.php`,
  `src/Controller/ChangePosterController.php`; `PlexClient::itemPoster()`.
- Removed: `UploadController`, `PosterUploader`, the `/upload` + `/upload-url`
  routes, and the global upload modal.
- Modified: gallery template + CSS (action overlay, change modal, find grid),
  Plex import overlay, routes, container wiring.
- Environment: `POSTER_SOURCE_URL` (default `https://posteria.app`).
