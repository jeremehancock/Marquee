## Context

Import already records, per Plex rating key, which poster file was stored.
Sending back is the inverse: given a poster file, find its Plex item and upload
the image, then lock the poster so Plex keeps it. Locking and label edits are
addressed through the item's library section and Plex type number, so the import
mapping must also remember the section.

## Goals / Non-Goals

**Goals:**
- A write path (`PlexPosterWriter`) separate from the read `PlexClient`, so the
  export service is tested against a fake and the HTTP calls are asserted with a
  request-recording mock.
- Only offer "Send to Plex" for posters that are actually linked to a Plex item.
- Optional, safe overlay-label removal gated by config and by having a section.

**Non-Goals:**
- No matching of manually-uploaded posters to Plex items by title (only imported
  posters carry a mapping).
- No bulk send / no scheduled export.

## Decisions

- **Writer interface.** `PlexPosterWriter` with `uploadPoster(ratingKey, bytes)`,
  `lockPoster(ratingKey)`, `removeOverlayLabel(sectionKey, plexType, ratingKey)`.
  `HttpPlexClient` implements both read and write; the container aliases both
  interfaces to one instance.
- **Plex calls.** Upload: `POST /library/metadata/{ratingKey}/posters` with the
  image bytes. Lock: `PUT /library/metadata/{ratingKey}?thumb.locked=1`. Label
  removal: `PUT /library/sections/{sectionKey}/all?type={n}&id={ratingKey}&label[].tag.tag-=Overlay`.
- **Section tracking.** `plex_items` gains a `section_key` column (added
  idempotently for databases created before this change). `PlexItem` carries the
  section from its library through import.
- **Export service.** `PlexExportService::sendToPlex(category, filename)` looks
  up the mapping by filename; if none, it raises a clear error. Otherwise it
  uploads the stored bytes, locks the poster, and — when
  `PLEX_REMOVE_OVERLAY_LABEL` is set and a section is known — removes the label.
- **UI.** `GalleryController` marks which of a category's posters are linked
  (one indexed lookup) and, when Plex is configured, the template renders a
  Send-to-Plex form only on those.

## Risks / Trade-offs

- **No live Plex** to verify against; correctness rests on asserting the exact
  HTTP method/URL of each write and on the service's orchestration via a fake.
- **Only imported posters are linkable.** Matching arbitrary uploads to Plex is
  intentionally out of scope.
