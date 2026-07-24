## Why

The gallery currently sorts only by title, but the original app (Posteria)
also let users order posters by when their media was added to Plex, which is
the natural way to find what's new. Restoring this gives users a "newest
first" view and a preferred-default they can set for their install.

## What Changes

- Add a second gallery sort order: **Date added to Plex** (newest first),
  alongside the existing **Alphabetical** order.
- Add a Docker environment variable `DEFAULT_SORT` to choose the preferred
  sort for an install. Accepts `alphabetical` (default) or `date_added`;
  Alphabetical remains the default when unset or invalid.
- Add a UI control in the gallery toolbar to toggle between Alphabetical and
  Date added. The choice persists across navigation (like the remembered
  category) and falls back to `DEFAULT_SORT` when the user hasn't chosen.
- Capture each Plex item's "added at" timestamp during import and store it in
  the item→poster mapping so date-added ordering has a real value to sort on.
- Posters with no Plex "added at" (e.g. items imported before this change, or
  never mapped) fall back to their file modification time so every poster
  still has a stable position in the date-added order.
- Date-added sort applies to single categories and the aggregate **All** view;
  search results remain relevance-ranked and are unaffected by the sort toggle.

## Capabilities

### New Capabilities

<!-- None. -->

### Modified Capabilities

- `poster-library`: gallery ordering gains a second sort order (date added to
  Plex), a `DEFAULT_SORT` environment default, and a user-facing toggle whose
  selection persists across navigation.
- `plex-import`: the import records each item's Plex "added at" timestamp in
  the item→poster mapping so the library can order by it.

## Impact

- **Config**: new `DEFAULT_SORT` env var, read once into `PosterConfig`.
- **Data**: new `added_at` column on the `plex_items` table (idempotent
  migration); `PlexItem`, `PlexItemRecord`, and the import upsert carry it.
- **Plex client**: `HttpPlexClient` parses the `addedAt` XML attribute.
- **Library/UI**: `PosterLibrary` orders by the selected sort; a `SortOrder`
  concept, the gallery controller resolving the effective sort, session
  persistence, the toolbar toggle, and pagination links carrying the sort.
- **Docs**: README documents `DEFAULT_SORT`.
- No breaking changes; existing installs default to Alphabetical exactly as
  today until a user opts into date-added.
