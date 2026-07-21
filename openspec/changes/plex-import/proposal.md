## Why

Marquee's purpose is to manage the posters for a Plex library, and Plex is the
source of truth. Until it can talk to Plex, users must add every poster by hand.
This change connects Marquee to a Plex Media Server and imports posters for
movies, TV shows, seasons, and collections into the local library.

It also introduces the persistent store (SQLite) that the rest of Plex
integration depends on: a record of which Plex item each poster came from, so
re-imports update the right file and later phases can detect orphans.

## What Changes

- Add Plex configuration (`PLEX_SERVER_URL`, `PLEX_TOKEN`, timeouts, batch size).
- Add a `PlexClient` that lists libraries and their items (movies, shows,
  seasons, collections) and downloads a poster image.
- Add a SQLite database with repositories that track the Plex libraries seen and
  map each Plex item (by rating key) to the stored poster filename.
- Add an `ImportService` that, for the selected libraries and media types,
  downloads each item's current Plex poster into the matching category and
  records the mapping so re-imports overwrite the same file.
- Add a Plex page in the UI to choose libraries and media types and run an
  import, reporting how many posters were imported.

## Capabilities

### New Capabilities
- `plex-import`: connect to Plex, list libraries and items, import posters into
  the library, and persist the item→poster mapping and library tracking.

### Modified Capabilities
<!-- None at the spec level. Internally, PosterStorage gains a `replace`
     operation (implementation detail of idempotent re-import). -->>

## Impact

- New: `src/Config/PlexConfig.php`, `src/Plex/**` (client, value objects,
  exception, import service), `src/Database/**` (Database + repositories),
  `src/Controller/PlexImportController.php`, `templates/plex.html.twig`.
- Modified: `PosterStorage` interface + `FilesystemPosterStorage` (add `replace`).
- Routes: `/plex`, `/plex/import`.
- Environment: `PLEX_SERVER_URL`, `PLEX_TOKEN`, `PLEX_CONNECT_TIMEOUT`,
  `PLEX_REQUEST_TIMEOUT`.
- Storage: SQLite database at `{DATA_DIR}/marquee.sqlite`.
