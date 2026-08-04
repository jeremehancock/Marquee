## Why

Two small things make the gallery harder to use on a phone than it needs to be.
The topbar menu button uses a hamburger glyph, which conventionally promises a
navigation drawer — but nothing behind it is a navigation destination: Import
from Plex and Orphans open as trays over the gallery, Poster Wall and Support
Development open new tabs, and Log out is an action. The glyph sets an
expectation the menu does not meet. Separately, search and sort scroll away as
soon as the user starts browsing, so filtering a large library means scrolling
back to the top every time.

## What Changes

- The topbar menu trigger becomes an overflow ("more") glyph instead of a
  hamburger, matching what the menu actually contains: actions, not navigation.
  The menu's contents, its bottom-sheet tray presentation, and its shared
  swipe-down dismissal are unchanged.
- The menu requirement is re-framed from a *navigation* menu to an *actions*
  menu so the spec describes what it is.
- On a narrow screen the gallery toolbar pins to the top of the viewport as the
  gallery scrolls, keeping search and the sort trigger reachable at any scroll
  position.
- The topbar itself deliberately stays non-sticky. The menu trigger scrolls away
  with it; persistent content controls plus a persistent category tab bar are
  enough, and a second permanently pinned bar would cost too much of a phone
  screen.
- No change to the desktop layout in either case.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the "App-wide mobile navigation menu" requirement is
  re-framed as an actions menu and the trigger's glyph is specified as an
  overflow/more affordance rather than a hamburger.
- `poster-library`: the "Responsive gallery layout" requirement gains a pinned
  mobile toolbar, so search and sort remain available while scrolling.
- `search`: the "Live search" requirement gains a return to the top of the
  results, so a search started part-way down the gallery shows its matches from
  the first one. The pinned toolbar makes searching mid-scroll possible for the
  first time, which is what exposed this.

## Impact

- `templates/layout.html.twig` — the trigger button's inline SVG.
- `public/assets/app.css` — `.toolbar` gains sticky positioning, an opaque
  background, and full-bleed side margins inside the mobile overrides block; the
  documented overlay layering scale gains the toolbar's tier.
- No PHP, routing, data, or JavaScript behavior changes. The existing overlay
  scroll-lock and infinite-scroll paths are unaffected.
- User-facing docs: none of `README.md` or `docs/` describe the menu glyph or
  the toolbar's scroll behavior, so no doc updates are expected — to be
  confirmed during implementation.
