## Why

On a phone the only way to change category is to hit one of five targets in a
bottom bar that divides a phone's width five ways — around 70px each, and the
gesture to reach them is a deliberate tap at the very bottom edge of the screen.
Every other library-shaped app on the device changes section with a thumb
anywhere on the content, and Marquee is the one that asks you to aim.

The switch itself is already instant and reload-free; what is missing is a way
to ask for it that does not require precision. A drag is that: it starts at the
edge of the screen, it is the same gesture whichever tab you are on, and it
shows you where you are going while you can still change your mind.

## What Changes

- **A horizontal drag on the gallery grid moves between adjacent category tabs
  on touch devices.** The grid follows the thumb one-to-one: the outgoing
  category leaves toward the gesture while the incoming one arrives from the
  opposite edge, edge to edge and never overlapping. Release commits or
  abandons; both are animated over a duration set by the distance left to
  travel.
- The tab order the gesture traverses is the order already rendered —
  All, Movies, Shows, Seasons, Collections. A drag off either end resists with
  damped movement and returns to rest rather than doing nothing, so "there is
  nothing there" is distinguishable from "the gesture was not recognised".
- **Both neighbouring categories are fetched ahead of time and held**, so the
  incoming grid is real content from the first frame rather than a placeholder.
  The held copy is used only when it is provably current for the live search,
  sort and library state; otherwise the gesture shows a skeleton and fetches,
  which is the same gesture with a slower middle rather than a different one.
- **The bottom tab bar marks the destination as soon as the gesture is
  claimed**, and marks the original again if it is abandoned. The bar is
  `position: fixed`, so it stays put while the grids slide beneath it and needs
  no movement of its own — only its active mark changes.
- The gesture is refused at the moment the touch begins — not when it ends — if
  any overlay is open or the touch starts inside one. A touch on the bottom tab
  bar belongs to the bar.
- Under reduced motion the grid still follows the thumb, because the viewer is
  moving it directly; only the settle after release becomes effectively
  instant.
- Nothing changes on pointer devices. Tapping a tab remains an instant cut, and
  the gesture is bound behind the same touch test the action tray already uses.

Not a breaking change: no route, no setting, no environment variable, and no
change to what a category shows. A device that never fires a touch event
behaves exactly as it does today, as does a browser with JavaScript disabled —
the tabs are still ordinary links.

## Capabilities

### New Capabilities

None. This is a new way to reach an existing destination, not a new
destination.

### Modified Capabilities

- `poster-library`: adds the drag itself — how it is claimed, how far it
  follows, what commits it and what abandons it, how it resists at the ends,
  how the neighbouring categories are kept ready and when a held copy may be
  trusted, when the bottom bar's active mark moves, and how an interrupted drag
  is guaranteed to leave nothing pinned.
- `visual-design`: the drag's presentation — two full-width panels sliding
  horizontally with no lift, scale or dim — and its account under reduced
  motion, which is an exception to the app-wide suppression rather than an
  omission from it: a gesture the viewer is driving with their own thumb is not
  motion done to them.

## Impact

- `public/assets/gallery.js` — the gesture (axis lock, tracking, commit test,
  settle, teardown), the neighbour prefetch and its currency check, and the
  reuse of `syncActiveTab()`. The existing `anyOverlayOpen()` in the scroll-lock
  block becomes shared rather than private to it.
- `public/assets/app.css` — the sliding panel presentation, the pinning that
  takes both grids out of the scroller for the gesture's duration, and the
  `touch-action` declarations that let the gesture be claimed.
- `templates/partials/gallery_results.html.twig` and `templates/gallery.html.twig`
  — a wrapper for the moving panel and the skeleton shown on a cache miss.
  `#results` itself is the swap target for the existing no-reload path and its
  identity must not move.
- `tests/Unit/Asset/` — a new source-shape test alongside `TrayDismissalTest`,
  which pins the app's other touch gesture the same way. What a browser is
  needed to see is stated in the design's verification section rather than
  claimed by a test that cannot see it.
- `docs/` and `README.md` — the gesture is undiscoverable by inspection and
  belongs wherever the phone experience is described.
- No PHP behaviour, no routes, no database, no `Dockerfile`, no `composer.json`.
