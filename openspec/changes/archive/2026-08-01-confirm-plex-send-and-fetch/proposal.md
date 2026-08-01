## Why

Send to Plex and Fetch from Plex are one-click, irreversible overwrites sitting
next to Change poster and Download on every card — Send replaces whatever
artwork the Plex item currently has, Fetch discards the custom poster Marquee is
holding. Delete already asks before it acts; these two do not, so a mis-tap on a
crowded card overlay (or on the mobile tray, where the buttons are full width and
stacked) destroys work with no warning and no undo.

## What Changes

- Send to Plex asks for confirmation before it uploads, naming the poster and
  saying that the artwork currently on the Plex item will be replaced and locked.
- Fetch from Plex asks for confirmation before it downloads, naming the poster
  and saying that Marquee's stored poster will be overwritten and cannot be
  recovered.
- Cancelling either dialog performs no Plex request and changes nothing, on the
  card overlay and in the mobile action tray alike.
- The shared confirm dialog stops assuming every confirmation is a delete: its
  heading, its action label, and the tone of its action button are supplied by
  the action being confirmed, so "Send to Plex" is not offered under a red
  "Delete" button.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `poster-editing`: Re-send and Fetch each gain a required confirmation step
  before the operation runs; cancelling leaves both Plex and the local file
  untouched.
- `poster-library`: The shared confirm dialog is generalised from the delete
  confirmation to any card action that needs one, carrying that action's own
  heading, confirm label, and button tone.

## Impact

- `templates/partials/gallery_results.html.twig` — the Send and Fetch forms
  declare their confirmation the way the Delete form already does.
- `templates/partials/_overlays.html.twig` — the confirm dialog's action button
  takes its style from the confirmation rather than being permanently
  destructive.
- `public/assets/gallery.js` — the delegated submit handler and
  `overlayComponent` carry per-action heading/label/tone instead of the
  hardcoded "Delete poster?" pair; the orphans page keeps its existing
  confirmation unchanged.
- `tests/Functional/GalleryTest.php` — asserts the rendered confirmation
  attributes on the Send and Fetch forms.
- No PHP service, route, controller, or database change: both endpoints behave
  exactly as before once confirmed.
