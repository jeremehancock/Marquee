## Why

The orphans page can only delete every orphan at once. When a user wants to
keep some orphans (a show they plan to re-add) and clear others, the all-or-
nothing control forces them to either delete more than they intend or nothing
at all. Per-orphan actions let the user resolve orphans one at a time, using
the same familiar card controls they already know from the main library.

## What Changes

- Each orphan card gains its own action controls: **Download** (save the poster
  file) and **Delete** (remove just that orphan's file and Plex mapping).
- These controls use the same interaction pattern as the poster library: a
  hover overlay on the card for pointer/desktop, and a tap-to-open action tray
  (sheet) on touch/mobile.
- Deleting a single orphan happens in place — the card is removed and the
  orphan count updates without a full page reload, with a confirmation step and
  a toast, matching how the library deletes a poster.
- The existing "Delete all orphans" control is unchanged and remains available.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `orphan-detection`: The delete requirement expands from "delete all" to also
  allow deleting a single detected orphan, and the orphans page gains per-orphan
  Download and Delete actions delivered through the shared overlay (pointer) and
  action-tray (touch) presentation already used by the poster library.

## Impact

- **Routes**: new `POST /orphans/delete` for deleting one orphan.
- **Backend**: `OrphanController` gains a `delete` action; `OrphanService`
  gains a single-orphan delete that removes one orphan's file and its Plex
  mapping while preserving non-orphans.
- **Templates**: `templates/orphans/_results.html.twig` renders per-card
  Download/Delete controls; the orphans shell (`templates/orphans.html.twig`)
  gains the shared action-tray, confirm, and toast overlays.
- **Frontend**: `public/assets/gallery.js` extends the orphans page so tapped
  cards open the action tray on touch and single-orphan deletes run in place
  and refresh the scan result. Reuses existing `card__overlay`, `card__actions`,
  and `sheet` styles from `public/assets/app.css` — no new visual system.
- No new dependencies, no database schema changes, no breaking changes.
