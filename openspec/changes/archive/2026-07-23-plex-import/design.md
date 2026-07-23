## Context

Plex exposes an HTTP API returning XML. Libraries ("sections") contain items;
movies are `Video` elements, shows are `Directory` elements, seasons are the
children of a show, and collections are a section's collections. Each item has a
`ratingKey` (stable id) and a `thumb` path for its current poster.

The legacy app hand-rolled Plex calls, JSON "id storage", and library tracking
files. This change replaces that with a `PlexClient` behind an interface, a
small SQLite store, and a focused import service.

## Goals / Non-Goals

**Goals:**
- A `PlexClient` interface so the import service is unit-tested with a fake and
  the HTTP implementation is tested with mocked responses — no live Plex needed.
- Idempotent re-import: the item→poster mapping lives in SQLite keyed by rating
  key, so re-importing overwrites the same file instead of duplicating.
- Graceful behavior when Plex is unconfigured or unreachable (clear messages,
  never a crash).

**Non-Goals:**
- No send-to-Plex / poster locking / overlay-label handling yet (next change,
  `plex-export`).
- No scheduled auto-import or orphan detection yet (later phases).
- No background/batched progress UI; import runs per request and reports counts.

## Decisions

- **Interface + implementation.** `PlexClient` (interface) with
  `HttpPlexClient` (Guzzle). Read methods: `libraries()`, `items(section)`,
  `seasons(showRatingKey)`, `collections(section)`, `downloadPoster(thumb)`, plus
  `isConfigured()`. Responses parsed with SimpleXML into value objects.
- **Value objects.** `PlexLibrary` (key, title, type) and `PlexItem` (ratingKey,
  mediaType, title, year, thumb, libraryTitle). `PlexMediaType` enum maps to a
  `PosterCategory`.
- **SQLite.** A single `Database` opens `{DATA_DIR}/marquee.sqlite` lazily and
  runs idempotent `CREATE TABLE IF NOT EXISTS` migrations. `PlexItemRepository`
  maps rating key → filename/category; `PlexLibraryRepository` records libraries
  seen. Tests use a temp-file database.
- **Import.** `ImportService::import(sectionKeys, mediaTypes)` resolves each
  section's type, fetches the requested item kinds, downloads each poster to a
  temp file, and writes it: if a mapping exists it overwrites via
  `PosterStorage::replace`, otherwise it stores a new unique file and records the
  mapping. Failures per item are counted, not fatal. Returns an `ImportResult`.
- **Filenames.** Derived once from the item: `"{title} ({year}) [{library}]"`
  for movies, `"{title} [{library}]"` for shows/collections,
  `"{show} - {season} [{library}]"` for seasons. The mapping keeps re-imports
  stable even if the title later changes.
- **SQLite concurrency.** The database uses WAL journaling, a busy timeout, and
  `synchronous = NORMAL`, so an import (writer) overlapping the gallery (reader)
  does not raise "database is locked". Import only calls Plex's HTTP API
  sequentially; it never touches Plex's own database.
- **Import feedback.** Import runs in a single request; the page indicates it is
  running and disables re-submission. PHP `max_execution_time` and nginx
  `fastcgi_read_timeout` are raised so large libraries are not cut off. A
  batched/streaming progress UI remains a possible later refinement.

## Risks / Trade-offs

- **No live Plex in CI/dev**, so correctness rests on realistic XML fixtures and
  the interface seam. The HTTP client is intentionally thin.
- **Synchronous import** can be slow for very large libraries; batched/progress
  import is a deliberate later refinement.
- **SQLite file** is new runtime state under `/config/data`; it is created
  automatically and safe to delete (it only caches Plex mappings).
