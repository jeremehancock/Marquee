## 1. Configuration & client

- [x] 1.1 `Config\PlexConfig` from env (url, token, timeouts).
- [x] 1.2 `Plex\PlexMediaType` enum + mapping to `PosterCategory`.
- [x] 1.3 `Plex\PlexLibrary` and `Plex\PlexItem` value objects.
- [x] 1.4 `Plex\PlexClient` interface + `Plex\HttpPlexClient` (Guzzle, SimpleXML).
- [x] 1.5 `Plex\PlexException` for connection/response failures.

## 2. Persistence

- [x] 2.1 `Database\Database` — lazy PDO SQLite + idempotent migrations.
- [x] 2.2 `Database\PlexItemRepository` — upsert / findByRatingKey / all.
- [x] 2.3 `Database\PlexLibraryRepository` — sync / all.
- [x] 2.4 Extend `PosterStorage` with `replace()`; implement in filesystem storage.

## 3. Import

- [x] 3.1 `Plex\Import\ImportResult` (imported, failed, per-category counts).
- [x] 3.2 `Plex\Import\ImportService::import(sectionKeys, mediaTypes)`.

## 4. HTTP & UI

- [x] 4.1 `Controller\PlexImportController` — show page + run import.
- [x] 4.2 Routes `/plex`, `/plex/import`; container wiring.
- [x] 4.3 `templates/plex.html.twig`; link from the gallery.

## 5. Verify

- [x] 5.1 Unit: config, client XML parsing (mocked HTTP), repositories, import service (fake client).
- [x] 5.2 Functional: `/plex` renders; import runs with an injected fake client and stores posters.
- [x] 5.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 5.4 `openspec validate plex-import` passes.
