## Why

Searching the gallery and then reloading the page presents two different
controls for clearing the same search — one beside the search box and one in the
results summary. Worse, the one beside the search box is unreliable: it never
appears during live search, and it lingers after the search has already been
cleared, offering to clear a search that is no longer active.

## What Changes

- Remove the `Clear` link rendered inside the search toolbar, next to the search
  input.
- Keep the `Clear search` control in the filtered-results summary strip as the
  single way to clear an active query. It sits with the match count, is
  server-rendered, and is refreshed by every live update.
- No change to clearing behaviour itself: emptying the search box, the browser's
  native clear affordance inside the search field, and the retained control all
  continue to restore the full, unfiltered view.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `search`: the "Filtered view is clearly indicated" requirement gains the
  constraint that exactly one clear control is presented for an active query,
  co-located with the filtered-state summary so the indication and its clear
  control appear, update, and disappear together.

## Impact

- `templates/gallery.html.twig` — the toolbar `Clear` link is removed.
- `templates/partials/gallery_results.html.twig` — unchanged; it already renders
  the control that is being kept.
- `public/assets/app.css` — unchanged. The `.search__clear` rule is still used by
  the retained control and by the "Back to gallery" links on the Plex and
  orphans pages.
- `public/assets/gallery.js` — unchanged. Its delegated `.search__clear` click
  handler already served both links, and its tray loader still relies on the
  class to strip the "Back to gallery" link.
- `tests/Functional/GalleryTest.php` — existing assertions target the retained
  control; a new assertion covers the single-control requirement.
- No user-facing documentation describes the removed link.
