## Why

On a phone the Change Poster tray is very hard to close: its grab handle cannot
actually be grabbed, dragging it down usually scrolls the gallery behind it or
triggers the browser's pull-to-refresh instead, and it stands taller than any
other tray so there is barely any backdrop left to tap. The common outcome of
trying to dismiss it is reloading the whole page and losing your place. Every
other tray closes cleanly, which makes the one tray users reach most often feel
broken.

## What Changes

- Trays converted from modals (Change Poster, and both confirmation dialogs) get
  the same structure the real trays already use on a phone: a grab handle that is
  a real, grabbable element, a heading, and a separate scrolling body. Drag-down
  to dismiss then works on them exactly as it does on the poster actions, menu,
  sort, import and orphans trays.
- A drag that starts on a tray's handle or heading is claimed by the tray, so the
  page behind it can no longer scroll or pull-to-refresh mid-gesture.
- Trays converted from modals stop growing taller than a real tray, so the
  backdrop above them stays a large enough target to tap. No tray gains a close
  button — the grab handle and the backdrop remain the whole dismissal story, as
  they already are on the trays that work.
- Scrolling inside a tray stops at that tray's own boundary instead of handing
  the gesture off to the page behind it. This includes the Find Posters results
  grid, which is a scrolling area nested inside a scrolling tray.
- While any tray, dialog or the fullscreen viewer is open, the page behind it no
  longer scrolls. This part is deliberately separable — see Impact — and can be
  dropped without affecting anything above it.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: the touch-device tray requirements gain dismissal guarantees
  that currently only hold by accident. Specifically: every tray SHALL be
  dismissible by dragging its handle and by tapping its backdrop, with the drag
  region a real element distinct from any scrolling content; a tray's dismissal
  gesture SHALL NOT be lost to the page behind it; a tray SHALL leave enough
  backdrop to tap; scrolling within a tray SHALL NOT propagate to the page; and
  confirmation dialogs SHALL be presented and dismissed as trays on a phone,
  layered above whatever opened them.

## Impact

- `templates/gallery.html.twig` — the Change Poster modal's markup gains the tray
  skeleton.
- `templates/partials/_overlays.html.twig`, `templates/orphans.html.twig` — the
  two confirmation modals get the same treatment.
- `public/assets/app.css` — the mobile block that restyles modals as sheets; the
  drag-target and scroll-container rules; tray panel height; overscroll
  containment; the layering of trays against dialogs.
- `public/assets/gallery.js` — the existing drag-to-dismiss handler should need no
  change once the markup provides a real handle; the optional page-scroll lock
  adds one scope-agnostic observer.
- `tests/Functional/ApplicationShellTest.php` and the gallery/orphans template
  tests — markup assertions covering the tray skeleton, plus an asset test
  pinning the stylesheet rules that make the drag gesture reliable.

Risks and sequencing:

- The page-scroll lock is listed last on purpose. Locking the page reliably on
  iOS Safari requires a technique that interacts badly with an on-screen
  keyboard, and Change Poster is the one overlay containing a text input. If that
  interaction cannot be made clean, the lock is dropped and the rest of the
  change still resolves the reported problem. Its requirement is written so it can
  be removed independently.
- The gallery's infinite scroll reacts to page scroll position, so any change to
  page scrolling must leave the scroll position exactly as it found it or the
  gallery will silently load extra pages when a tray closes.
- No PHP, routing, or data behaviour changes; this is presentation and client
  interaction only.
