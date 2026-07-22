## 1. Backend

- [ ] 1.1 `PlexClient::itemPoster(ratingKey)` + HttpPlexClient/FakePlexClient impls.
- [ ] 1.2 `Poster\Source\PosterSource` + `PosteriaApiPosterSource` (posteria.app).
- [ ] 1.3 `Poster\Edit\ChangePosterService` (change from file/URL → replace + push; fetchFromPlex).
- [ ] 1.4 `Controller\ChangePosterController` (upload, url, fetch-from-plex, find-posters); routes.
- [ ] 1.5 Remove `UploadController`, `PosterUploader`, the upload routes; container wiring for `PosterSource`.

## 2. Frontend

- [ ] 2.1 Gallery: remove Add poster; per-poster hover overlay action stack.
- [ ] 2.2 Shared Change-poster modal (Upload / URL / Find Posters) + Copy/Download.
- [ ] 2.3 Full-screen import progress overlay on the Plex page.

## 3. Verify

- [ ] 3.1 Unit: change service (file/URL replace + push, fetch-from-Plex), poster source parsing, itemPoster.
- [ ] 3.2 Functional: change endpoints replace + return; find-posters returns candidates.
- [ ] 3.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [ ] 3.4 `openspec validate poster-editing` passes.
