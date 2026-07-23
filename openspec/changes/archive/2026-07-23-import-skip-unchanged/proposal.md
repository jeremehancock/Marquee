## Why

Every import used to download each item's full poster image from Plex and
overwrite the local file, even when nothing had changed. For large libraries that
is a lot of needless load on the Plex server. Plex's poster path carries a version
token that changes only when the artwork changes, so we can tell an import is
unnecessary without downloading anything.

## What Changes

- Record each item's Plex poster path (`thumb`) alongside its poster mapping.
- During import, skip the poster download when the stored `thumb` matches Plex's
  current one **and** the local file still exists — the poster is unchanged.
- Re-download when the `thumb` differs, when the local file is missing, or when
  the user forces a full re-import.
- Add a "Re-download unchanged posters" option to the import screen for a forced
  refresh.
- Report how many posters were skipped in the import summary.

## Capabilities

### New Capabilities
- `import-skip-unchanged`: change-detected imports that avoid re-downloading
  unchanged posters, with a force override.

## Impact

- Modified: `Database` (new `thumb` column, idempotent migration),
  `PlexItemRecord`, `PlexItemRepository`, `ImportService`, `ImportResult`,
  `PlexImportController`, `templates/plex.html.twig`.
- Backwards compatible: existing rows get an empty `thumb`, so the first import
  after upgrading re-downloads once (storing the token) and subsequent imports
  skip unchanged posters. No new environment variables.
