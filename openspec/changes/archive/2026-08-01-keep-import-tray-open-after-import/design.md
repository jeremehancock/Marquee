## Context

On a phone the gallery's "Import from Plex" destination opens as a tray rather
than navigating away. `openImport` fetches `/plex` once, drops the rendered form
into `x-ref="importBody"`, re-runs `Alpine.initTree` over it, and intercepts the
form's submit so `runImport` POSTs to `/plex/import` with `fetch`
([gallery.js:632-691](public/assets/gallery.js#L632-L691)).

Two pieces of state matter here. `_importForm` is the cached reference to the
loaded `<form>`; `_resetImport` clears `importLoaded`, nulls `_importForm`, and
empties `importBody.innerHTML`. On success `runImport` today sets
`importOpen = false` and then calls `_resetImport()` — closing the tray and
throwing the loaded form away together.

The form's own Alpine component ([plex.html.twig:34-44](templates/plex.html.twig#L34-L44))
holds `type` (bound to the step-1 radios), `sections` (bound to the step-2
checkboxes), and `importing` (drives the tray-contained progress overlay and the
disabled submit button). Steps 2 and 3 are `x-show="type"`, so clearing `type`
alone collapses the form back to step 1. The "Re-download unchanged posters"
checkbox is deliberately *not* bound to the component — it is plain DOM the
component does not own.

The user's ask is narrow: this tray only. The orphans tray, the poster action
tray, sort, the menu, and confirmations are working as intended and must not be
touched.

## Goals / Non-Goals

**Goals:**

- A completed import leaves the import tray open, showing a form at step 1.
- Reporting and gallery refresh keep working exactly as they do now.
- A failed import still leaves the tray open with the user's selections intact.
- The change is confined to `runImport` and its immediate helpers.

**Non-Goals:**

- Changing the standalone `/plex` page (pointer devices), which still does a
  normal POST/redirect.
- Changing any other tray's open/close behavior.
- Multi-type import in a single run, or any change to what the import does.
- Persisting the previous selection as a convenience default — the user asked
  explicitly for step 1.

## Decisions

### Reset the loaded form in place; do not refetch the tray

The alternative is to call `_resetImport()` as today and immediately re-run
`_loadTray('/plex', 'importBody')`. Rejected: it empties the tray to a spinner
and back for a form whose content did not change, and re-running
`Alpine.initTree` over a re-fetched fragment is the exact hazard called out for
the orphans tray (`openOrphans`) — it re-binds whatever the fragment binds on
init. The form's correctness does not decay, which is why the tray is fetch-once
in the first place; `OrphansTrayRescanTest::testTheImportTrayStillLoadsOnce`
pins that asymmetry and should keep passing unchanged.

So: reach into the loaded form's Alpine component and put it back to its initial
values.

### What "reset" concretely means

Three separate things, because they live in different places:

1. `type = ''` — clears the step-1 radios via `x-model` and collapses steps 2
   and 3 (`x-show="type"`).
2. `sections = []` — clears the step-2 library checkboxes via `x-model`. Setting
   `type` alone is not enough; `@change` on the radios clears `sections` only
   when the user picks a type, so a stale selection would otherwise reappear the
   moment they chose the same type again.
3. The `force` checkbox has no `x-model`, so no Alpine assignment reaches it.
   Clear it directly (`form.querySelector('input[name="force"]').checked =
   false`). Using `form.reset()` instead is a trap: it would restore the DOM but
   leave the Alpine component holding the old `type`/`sections`, and `x-model`
   would write them straight back.

Guard the whole thing the way `_rescanOrphans` guards its component lookup —
`window.Alpine && window.Alpine.$data`, and a `try` around the assignment — so a
form that failed to load, or an Alpine that is not present, degrades to "tray
stays open, nothing reset" rather than throwing inside the success path.

### Clear `importing` in the same place, not in `finally`

Today `runImport`'s `finally` clears the form component's `importing` flag, but
the success path has already run `_resetImport()` and nulled `_importForm`, so
its `if (window.Alpine && self._importForm)` guard is false and the flag is
never actually cleared on success. That is invisible today because the DOM is
discarded. Once the tray stays open it is not invisible: a stuck `importing`
leaves the progress overlay over the tray and the submit button disabled
forever.

Fold the flag into the single reset helper that runs on both paths, so success
and failure clear it through the same code rather than relying on ordering
between `then` and `finally`.

### Keep `_resetImport` for dismissal only

`closeImport` keeps calling `_resetImport()`. Discarding the fragment on
dismissal is still right — it is what makes a reopened tray fetch a fresh form —
and it is a different operation from "put the live form back to step 1". Two
small helpers with distinct jobs (`_resetImport` discards; the new one rewinds)
beats one helper with a mode flag.

### Scroll the tray body back to the top

After a run the tray may be scrolled down to where the submit button was.
Rewinding to step 1 shortens the form, so leaving the scroll position alone can
land the user on whitespace, or on a form that looks empty. Set the tray body's
scroll to 0 as part of the rewind. The scroller is `.sheet__body`, not the
`.sheet__panel` (see the drag-gesture notes at
[gallery.js:221-260](public/assets/gallery.js#L221-L260)) and not
`$refs.importBody` either — the ref is a plain `div` nested *inside*
`.sheet__body`, so the rewind has to walk up to the real scroller.

### Failure keeps the selection

`runImport`'s `catch` already leaves `importOpen` true, so a failed import
already keeps the tray open — nothing to change there. It must *not* rewind:
the selections are precisely what the user would have to re-enter to retry. Only
`importing` gets cleared on that path, so the overlay lifts and the button
re-enables.

### Testing: an asset tripwire, plus hand verification

This repo has no JS test runner; the established pattern for pinning
gallery.js behavior is a `tests/Unit/Asset/*Test.php` that reads the source and
asserts on the shape of the relevant method (`OrphansTrayRescanTest`,
`PreviewApplyProgressTest`, `TrayDismissalTest`). Follow it: assert that
`runImport`'s success path no longer sets `importOpen = false`, that it rewinds
rather than calls `_resetImport`, and that the rewind covers `type`, `sections`,
the unbound `force` checkbox, and `importing`. The behavior itself is verified
by hand on a phone against the `:dev` image, as with every other tray change.

## Risks / Trade-offs

- **The rewind silently misses a future form field** → The failure mode is a
  field carrying over to the next import, which on this form means importing
  with an option the user did not re-choose. Mitigated by the asset test naming
  each field, and by the form being small and server-rendered in one place.

- **`Alpine.$data` on the cached `_importForm` throws or returns nothing** →
  Guarded like `_rescanOrphans`; the tray stays open, unreset, rather than the
  success path throwing and the result never being reported.

- **The toast lands behind the open tray** → It does not: `.toast` is
  `z-index: 80` and `.sheet` is `z-index: 50`, and the toast markup lives in
  `_overlays.html.twig` outside the tray. Worth confirming on device, since
  reporting the result over an open tray is a presentation this app has not
  used before.

- **The gallery refresh behind the open tray is visible** → `gallery:refresh`
  dims `#results` (`[data-gallery].is-loading #results { opacity: .55 }`) rather
  than raising an overlay, so it stays behind the tray. No conflict, but it is
  the one thing this change newly makes visible.

- **A user submits again while the previous run is still in flight** → Not newly
  possible: `importing` disables the submit button for the whole run, and the
  rewind that re-enables it happens only after the response resolves.
