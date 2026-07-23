## 1. Write client & section tracking

- [x] 1.1 `Plex\PlexPosterWriter` interface (upload / lock / remove-label).
- [x] 1.2 `HttpPlexClient` implements it (POST poster, PUT lock, PUT label removal).
- [x] 1.3 `PlexMediaType::plexTypeNumber()`; `PlexConfig` gains `removeOverlayLabel`.
- [x] 1.4 Add `sectionKey` to `PlexItem`; thread it through the client and import.
- [x] 1.5 Add `section_key` to `PlexItemRecord`, the schema, and repository
      (idempotent column add); `findByFilename` + `filenamesForCategory`.

## 2. Export

- [x] 2.1 `Plex\Export\ExportException` with user-facing messages.
- [x] 2.2 `Plex\Export\PlexExportService::sendToPlex(category, filename)`.

## 3. HTTP & UI

- [x] 3.1 `Controller\PlexExportController::send`; route `/library/{category}/send-to-plex`.
- [x] 3.2 Container wiring (alias read/write client to one instance).
- [x] 3.3 Gallery marks linked posters and shows Send-to-Plex when Plex is configured.

## 4. Verify

- [x] 4.1 Unit: export service (fake writer: upload+lock, label removal on/off, not-linked error),
      HttpPlexClient write requests (recorded method/URL), media-type numbers.
- [x] 4.2 Functional: send-to-Plex stores link + returns to gallery with a fake writer.
- [x] 4.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 4.4 `openspec validate plex-export` passes.
