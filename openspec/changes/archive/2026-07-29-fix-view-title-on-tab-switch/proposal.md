## Why

Switching category tabs — All, Movies, TV Shows, TV Seasons, Collections —
leaves the browser tab title showing the previous view. The title only catches
up on a full page refresh, so a user with several Marquee tabs open cannot tell
them apart, and a bookmark or history entry made after a tab switch is labelled
with the wrong view.

## What Changes

- Update the browser title as part of the gallery's no-reload navigation, so it
  always names the view that is actually on screen.
- The same fix covers every navigation that goes through that code path:
  category tab switches, live search, clearing a search, pagination, and
  back/forward through history.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: the gallery's no-reload navigation SHALL keep the browser
  title in sync with the view being displayed, not just the URL and the grid.

## Impact

- `public/assets/gallery.js` — the `load()` function, which fetches a view and
  swaps `#results` into the page.
- `tests/Functional/` — no server-side behavior changes; the server already
  renders the correct `<title>` for every view (`templates/gallery.html.twig`
  sets `{{ view.label }} · {{ site_title }}`). The defect is purely that the
  client discards it.

No template, route, controller, or configuration change is needed.
