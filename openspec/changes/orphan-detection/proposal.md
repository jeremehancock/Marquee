## Why

When media is removed from Plex, the poster Marquee imported for it stays on
disk, disconnected from any Plex item. Over time these orphaned posters pile up.
This change finds posters whose Plex item no longer exists and lets the user
remove them.

## What Changes

- Detect orphaned posters: imported posters whose Plex rating key is no longer
  present on the Plex server.
- Show the orphans in a dedicated page with their thumbnails and category.
- Delete all orphans in one action (removing both the file and its mapping).
- Leave manually-uploaded posters (which were never linked to Plex) untouched —
  they are intentional, not orphans.

## Capabilities

### New Capabilities
- `orphan-detection`: identify imported posters whose Plex item is gone and
  remove them.

### Modified Capabilities
<!-- None. -->

## Impact

- New: `src/Plex/Orphan/OrphanService.php`,
  `src/Controller/OrphanController.php`, `templates/orphans.html.twig`.
- Modified: `PlexItemRepository` (add `deleteByRatingKey`, `distinctMediaTypes`),
  gallery toolbar (link to Orphans).
- Routes: `/orphans`, `/orphans/delete-all`.
