## Why

Two of the three ways to change a poster commit blind. Find Posters shows the
candidate full screen and asks before it replaces anything, but Upload and From
URL go straight from a file picker or a pasted URL to a text-only confirmation —
the user never sees the image they are about to put on the wall, and finds out it
was the wrong crop, the wrong season, or a 404 page only after Plex has it. The
one operation the app is built around should look and feel the same whichever of
its three doors the user came through.

## What Changes

- The Upload and From URL submit controls are relabelled for what they actually
  do — **Upload poster** and **Fetch poster** — and they no longer change
  anything: they hand the chosen image to the same full-screen preview Find
  Posters uses.
- Both tabs' images are previewed full screen — the picked file rendered
  locally, the pasted URL loaded from its source — with the identical two-step
  commitment Find Posters already has: *Use this poster* → *Change the poster to
  this one?* → *Change poster* / *Cancel*, plus the same progress overlay and
  single-run guard while the change is applied.
- Closing the preview returns the user to the change dialog with their file or
  URL still there. Today Escape over the Find Posters preview closes the change
  dialog behind it as well; that is fixed for all three tabs, since the input a
  dismissal would now discard is the user's own.
- The text-only "Replace the poster for X with the selected image?" confirmation
  is **removed** for these two tabs. The shared confirm dialog stays exactly as
  it is for Send to Plex, Fetch from Plex, and Delete.
- Cleanup that falls out: the two change forms stop being AJAX mutation forms
  (`js-mutate`, `data-refresh`, and the four `data-confirm*` attributes come
  off), the preview's state and its apply/close handlers stop being
  Find-Posters-specific, and the preview's stylesheet block is renamed to match
  what it now serves.

## Capabilities

### New Capabilities

None. This changes how an existing capability is presented, not what the app can
do.

### Modified Capabilities

- `poster-editing`: Upload and From URL preview the replacement full screen and
  take their confirmation from that preview, replacing the text-only
  confirmation dialog those two tabs use today; their submit controls are
  relabelled; the preview is dismissible back to the change dialog with input
  intact.
- `poster-sources`: dismissing the found-poster preview leaves the change dialog
  standing rather than closing it too — the preview is now shared with the other
  two tabs, where a dismissal that closed the dialog would throw away the user's
  own file or URL.

## Impact

- `templates/gallery.html.twig` — tab submit labels, the two forms' attributes,
  and the preview overlay markup (no longer Find-Posters-only).
- `public/assets/gallery.js` — preview state lifted out of `finder`, shared
  open/close/apply handlers, an object URL for the picked file, and the Escape
  guard over the change dialog.
- `public/assets/app.css` — the `.viewer--finder` block renamed.
- `tests/Functional/GalleryTest.php` — the change-dialog confirmation test is
  rewritten around the preview.
- No PHP behavior changes: `/change/upload` and `/change/url` are posted exactly
  as before, so validation, Plex export, and flash handling are untouched.
- User-facing docs (`README.md`, `docs/testing.md`) describe Change poster only
  in terms of the three sources, which still holds — check, expect no edit.
