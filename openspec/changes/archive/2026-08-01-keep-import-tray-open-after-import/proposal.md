## Why

Importing from Plex on a phone is naturally repetitive: the form imports one
content type at a time, so bringing in a library usually means running it four
times — movies, shows, seasons, collections. Today each run closes the import
tray, so every repeat costs a trip back through the menu to reopen it. Keeping
the tray standing, with a fresh form in it, turns four imports into four taps
instead of four re-openings.

## What Changes

- After an import finishes inside the mobile import tray, the tray stays open
  instead of closing.
- The form inside it is returned to its initial state — no content type
  selected, no libraries checked, "Re-download unchanged posters" cleared — so
  the next import starts at step 1 rather than from the previous selection.
- The result is still reported and the gallery behind the tray still refreshes;
  only the tray's dismissal changes.
- The tray remains dismissible exactly as before (drag down, backdrop tap,
  Escape), and closing it still discards the loaded form.
- No other tray changes. The orphans tray, the poster action tray, the sort
  tray, the menu tray, and every confirmation tray keep their current
  open/close behavior.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `poster-library`: the requirement "Import and orphans run inside their trays
  on small screens" currently leaves what happens to the tray after an import
  unstated, and the implementation closes it. It gains an explicit requirement
  that a completed import leaves the import tray open with the form reset to
  its initial state, scoped so no other tray's behavior is implied.

## Impact

- `public/assets/gallery.js` — `runImport` (stop closing the tray, reset the
  form in place instead of discarding it) and the interaction between
  `_resetImport` and the cached `_importForm` reference.
- `tests/Unit/Asset/` — a new asset tripwire test pinning the post-import tray
  behavior, alongside the existing `OrphansTrayRescanTest` which pins the
  deliberate import/orphans asymmetry.
- No PHP, template, route, or stylesheet changes; no change to the standalone
  `/plex` page used on pointer devices.
