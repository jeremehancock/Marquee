## 1. Backend

- [x] 1.1 `PlexClient::itemPoster(ratingKey)` + HttpPlexClient/FakePlexClient impls.
- [x] 1.2 `Poster\Source\PosterSource` + `PosteriaApiPosterSource` (posteria.app).
- [x] 1.3 `Poster\Edit\ChangePosterService` (change from file/URL → replace + push; sendToPlex; fetchFromPlex).
- [x] 1.4 `Controller\ChangePosterController` (upload, url, send-to-plex, fetch-from-plex, find-posters); routes.
- [x] 1.5 Remove `UploadController`, `PosterUploader`, the upload routes; container wiring for `PosterSource`.

## 2. Frontend

- [x] 2.1 Gallery: remove Add poster; per-poster hover overlay action stack.
- [x] 2.2 Shared Change-poster modal (Upload / URL / Find Posters) + Copy/Download.
- [x] 2.3 Full-screen import progress overlay on the Plex page.
- [x] 2.4 Standalone Send to Plex button on linked posters.
- [x] 2.5 Wider modal for the found-poster gallery so thumbnails read cleanly.

## 3. Asset delivery

- [x] 3.1 Service worker: stale-while-revalidate + bumped cache name (was cache-first, permanently stale).
- [x] 3.2 `asset()` Twig helper: mtime cache-busting so changed CSS/JS is always a new URL.

## 4. Verify

- [x] 4.1 Unit: change service (file/URL replace + push, send-to-Plex, fetch-from-Plex), poster source parsing, itemPoster.
- [x] 4.2 Functional: change endpoints replace + return; send-to-plex pushes; find-posters returns candidates.
- [x] 4.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 4.4 `openspec validate poster-editing` passes.
