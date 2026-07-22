## 1. Configuration & client

- [ ] 1.1 `Config\PlexConfig` from env (url, token, timeouts).
- [ ] 1.2 `Plex\PlexMediaType` enum + mapping to `PosterCategory`.
- [ ] 1.3 `Plex\PlexLibrary` and `Plex\PlexItem` value objects.
- [ ] 1.4 `Plex\PlexClient` interface + `Plex\HttpPlexClient` (Guzzle, SimpleXML).
- [ ] 1.5 `Plex\PlexException` for connection/response failures.

## 2. Persistence

- [ ] 2.1 `Database\Database` — lazy PDO SQLite + idempotent migrations.
- [ ] 2.2 `Database\PlexItemRepository` — upsert / findByRatingKey / all.
- [ ] 2.3 `Database\PlexLibraryRepository` — sync / all.
- [ ] 2.4 Extend `PosterStorage` with `replace()`; implement in filesystem storage.

## 3. Import

- [ ] 3.1 `Plex\Import\ImportResult` (imported, failed, per-category counts).
- [ ] 3.2 `Plex\Import\ImportService::import(sectionKeys, mediaTypes)`.

## 4. HTTP & UI

- [ ] 4.1 `Controller\PlexImportController` — show page + run import.
- [ ] 4.2 Routes `/plex`, `/plex/import`; container wiring.
- [ ] 4.3 `templates/plex.html.twig`; link from the gallery.

## 5. Verify

- [ ] 5.1 Unit: config, client XML parsing (mocked HTTP), repositories, import service (fake client).
- [ ] 5.2 Functional: `/plex` renders; import runs with an injected fake client and stores posters.
- [ ] 5.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [ ] 5.4 `openspec validate plex-import` passes.
