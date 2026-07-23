## 1. Domain & storage

- [x] 1.1 `PosterCategory` enum (slug ↔ label ↔ directory).
- [x] 1.2 `Poster` value object (category, filename, size, mtime, title, url).
- [x] 1.3 `PosterStorage` interface + `FilesystemPosterStorage` (list/exists/store/delete/path, filename validation).
- [x] 1.4 `Config\PosterConfig` from env (per-page, max size, allowed extensions, ignore-articles).

## 2. Library, search, pagination

- [x] 2.1 `PosterSearch` — normalize + specific term matching + ranking.
- [x] 2.2 `Page` value object (items, page, perPage, total, totalPages, prev/next).
- [x] 2.3 `PosterLibrary` — list, article-aware sort, search filter, paginate.

## 3. Upload & delete

- [x] 3.1 `Upload\PosterUploader` — from PSR-7 file and from URL (Guzzle); validate type/size; unique filename.
- [x] 3.2 `Upload\UploadException` with user-facing messages.

## 4. HTTP

- [x] 4.1 `GalleryController` — `/` redirect + `/library/{category}` (search, page).
- [x] 4.2 `PosterImageController` — stream `/posters/{category}/{filename}` with cache headers.
- [x] 4.3 `UploadController` — disk + URL upload endpoints.
- [x] 4.4 `PosterController` — delete endpoint.
- [x] 4.5 Register routes; wire container definitions.

## 5. UI

- [x] 5.1 Vendor Alpine.js; include in layout.
- [x] 5.2 `gallery.html.twig` — tabs, search, grid, pagination, upload modal, fullscreen.

## 6. Verify

- [x] 6.1 Unit tests: category, search, pagination, uploader validation.
- [x] 6.2 Functional tests: gallery listing/search/pagination, image serving, upload, delete.
- [x] 6.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer all green.
- [x] 6.4 `openspec validate poster-library` passes.
