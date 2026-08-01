## Why

Upload and From URL are the only poster-replacing actions in the app that still
fire on a single click. Find Posters asks "Change the poster to this one?" before
it applies a candidate, Send to Plex and Fetch from Plex each ask first, and
Delete asks — but picking a file in the change dialog and hitting the button
overwrites the stored poster (and pushes it to Plex, locked) with no chance to
back out, which is exactly the mis-tap the other confirmations were added to
close. The same dialog also mislabels its own action: the button says "Update
poster" inside a dialog titled "Change poster", and the URL field carries a
parenthetical about Mediux that makes a short label long for no gain.

## What Changes

- The Upload and From URL submit buttons read **Change poster**, matching the
  dialog they sit in and the confirm button Find Posters already uses.
- The URL field is labelled **Image URL**; the "(also supports Mediux URLs)"
  parenthetical is dropped.
- Submitting the Upload form asks for confirmation before anything is uploaded,
  naming the poster that will be replaced.
- Submitting the From URL form asks for confirmation before the image is
  fetched, naming the poster that will be replaced.
- Cancelling either confirmation posts nothing, changes no file, sends nothing to
  Plex, and leaves the change dialog open on the tab and input the user was on.
- Both confirmations use the shared confirm dialog, so the presentation is the
  existing full-screen modal on pointer devices and the existing tray on touch —
  the same one Send to Plex uses.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `poster-editing`: Changing a poster from a local file or a URL gains a required
  confirmation step before the replacement is performed; declining leaves the
  stored poster and Plex untouched. The change dialog opens with empty inputs
  every time, its submit action is named "Change poster", and its URL field is
  labelled "Image URL".
- `poster-library`: Declining a confirmation unwinds exactly one layer — a
  confirmation raised from a tray leaves that tray open, and the tray closes only
  when the action is actually taken.

## Impact

- `templates/gallery.html.twig` — the Upload and From URL forms declare their
  confirmation the way the Send/Fetch/Delete forms already do; two button labels
  and one field label change.
- `public/assets/gallery.js` — no new machinery; the delegated submit handler and
  shared confirm dialog already carry a per-action heading, message, label, and
  tone. The change dialog stops swallowing the Escape key that dismisses the
  confirmation stacked over it.
- `tests/Functional/GalleryTest.php` — asserts the rendered labels and the
  confirmation attributes on both forms.
- No PHP service, route, controller, or database change: `/library/{category}/
  change/upload` and `/change/url` behave exactly as before once confirmed.
- No user-facing docs change: `README.md` and `docs/` do not describe these
  labels, and the Mediux parenthetical appears nowhere else (the attribution
  footer deliberately omits Mediux already).
