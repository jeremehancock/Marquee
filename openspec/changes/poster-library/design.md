## Context

Posters are image files. The legacy app scanned directories directly and mixed
listing, filtering, and rendering in one 10k-line file. This change introduces a
small poster domain with a storage boundary so the filesystem details stay in
one place and the rest of the code works with value objects.

No database yet: browsing, search, and pagination all operate on the files on
disk. SQLite arrives with Plex tracking in a later phase.

## Goals / Non-Goals

**Goals:**
- A `PosterStorage` boundary (interface + filesystem implementation) so tests
  use a temp directory and services never touch `scandir` directly.
- Immutable `Poster` value objects and a `PosterCategory` enum.
- Deterministic pagination and a *specific* search (the legacy fuzzy search was
  deliberately tightened over time).
- Safe uploads: validate extension and size, sanitize filenames, avoid
  collisions and path traversal.

**Non-Goals:**
- No Plex import/export, no auto-import, no orphan detection (later phases).
- No SQLite/metadata store yet.
- No poster editing beyond upload/delete.

## Decisions

- **Category = enum.** `PosterCategory` (movies, tv-shows, tv-seasons,
  collections) maps slug ↔ label ↔ directory. Unknown slugs 404.
- **Storage boundary.** `PosterStorage` exposes list/exists/store/delete/path.
  `FilesystemPosterStorage` is the only place that knows about directories and
  allowed extensions. Filenames are validated to a safe charset and can never
  contain path separators, blocking traversal.
- **Library service.** `PosterLibrary` composes storage + search + sort +
  pagination and returns a `Page` value object. Sorting is article-aware when
  `IGNORE_ARTICLES_IN_SORT` is true (leading "a/an/the" ignored).
- **Search.** `PosterSearch` normalizes (lowercase, strip diacritics, collapse
  separators) and requires every query term to appear in the poster title —
  specific rather than fuzzy — ranked by match position.
- **Image serving.** Posters are streamed by `PosterImageController` behind auth
  with long cache headers, rather than symlinked into the document root. This
  keeps dev and container behavior identical and keeps images private.
- **Uploads.** `PosterUploader` handles both a PSR-7 uploaded file and a URL
  fetch (Guzzle). It validates MIME/extension against the allow-list and size
  against `MAX_FILE_SIZE`, then asks storage to persist a unique, sanitized
  filename.
- **Interactivity.** Alpine.js is vendored as a static asset; the gallery uses
  it for the upload modal and fullscreen viewer. No bundler.

## Risks / Trade-offs

- **PHP streams images** instead of nginx serving them statically: simpler and
  auth-protected, at some throughput cost. Acceptable for self-hosted libraries;
  revisit if the Poster Wall needs it.
- **No metadata store** means titles derive from filenames for now; Plex import
  will enrich this later.
