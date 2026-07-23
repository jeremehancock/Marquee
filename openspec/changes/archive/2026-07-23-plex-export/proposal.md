## Why

Importing brings Plex's posters into Marquee, but the point of customizing a
poster is to push it back so Plex uses it. This change lets users send a
customized poster to Plex and lock it, so Plex treats Marquee's choice as the
one to keep. It also handles the Kometa "Overlay" label so overlay users stay
compatible.

## What Changes

- Send a poster from the library to its linked Plex item (upload the image).
- Lock the poster field in Plex so a metadata refresh does not overwrite it.
- Optionally remove the Kometa "Overlay" label after updating, controlled by
  `PLEX_REMOVE_OVERLAY_LABEL`, so Kometa re-applies overlays.
- Show a "Send to Plex" action in the gallery, only for posters that are linked
  to a Plex item.

## Capabilities

### New Capabilities
- `plex-export`: send a stored poster to its Plex item, lock the poster, and
  optionally remove the Kometa overlay label.

### Modified Capabilities
- `plex-import`: the stored item mapping also records the Plex section, so export
  can address the item for locking and label edits.

## Impact

- New: `src/Plex/PlexPosterWriter.php`, `src/Plex/Export/PlexExportService.php`,
  `src/Plex/Export/ExportException.php`, `src/Controller/PlexExportController.php`.
- Modified: `HttpPlexClient` (implements the writer), `PlexItem`/`PlexItemRecord`
  and the `plex_items` schema (add `section_key`), `PlexMediaType`
  (Plex type numbers), `PlexConfig` (`PLEX_REMOVE_OVERLAY_LABEL`),
  `ImportService` (records the section), `GalleryController` + gallery template
  (Send-to-Plex action).
- Routes: `/library/{category}/send-to-plex`.
- Environment: `PLEX_REMOVE_OVERLAY_LABEL`.
