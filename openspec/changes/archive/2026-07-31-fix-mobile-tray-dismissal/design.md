## Context

Marquee has two overlay presentations that both end up looking like a bottom
tray on a phone, but only one of them behaves like one.

**`.sheet`** — poster actions, menu, sort, import, orphans. Three elements:
`.sheet__grip` (the grab handle), `.sheet__head`, and `.sheet__body`. The panel
itself is `overflow: hidden`; the body is the scroller. Grip and head both carry
`touch-action: none`.

**`.modal`** — Change Poster, and the two confirmation dialogs. On desktop it is
a centred dialog. Inside `@media (max-width: 640px)` it is restyled to *look*
like a tray: docked to the bottom, rounded top corners, a grab handle drawn as
`.modal__panel::before`, and `.modal__close` set to `display: none`.

The restyle copied the appearance but not the structure, and three things follow
from that:

1. `.modal__panel::before` is a pseudo-element, so it can never be an event
   target. The drag handler in `gallery.js` compensates by matching
   `e.target.classList.contains('modal__panel')`, which is only true for the
   ~39px strip of bare panel above `.modal__head`. A thumb landing on the title
   line or the tabs registers no drag at all.
2. `.modal__panel` is itself `overflow-y: auto` — it is both the drag target and
   the scroller. `touch-action: none` was applied to `.modal__head` but not to
   the panel, and it *cannot* be: applying it to the panel would disable the
   panel's own scrolling, and `touch-action` cannot be scoped to part of an
   element. So a downward drag at `scrollTop: 0` chains out to the document,
   which scrolls the page or starts a pull-to-refresh; the browser then fires
   `touchcancel`, and `endDrag()` runs with a sub-threshold `dy` and snaps the
   panel back.
3. With `.modal__close` hidden and the panel at `max-height: 90vh`, the only
   remaining exit is a ~10vh backdrop sliver.

Together these are why Change Poster is the tray users cannot close. Point 3 is
the largest single contributor — it removes the fallback that would have made
points 1 and 2 survivable.

Two smaller defects sit in the same area. `overscroll-behavior` appears nowhere
in the stylesheet, so scrolling to the end of any tray hands the gesture to the
page; the Find Posters tab is the worst case, nesting `.find-grid`
(`max-height: 62vh`) inside `.modal__panel` (`90vh`) inside the document. And
`.sheet` is `z-index: 55` while `.modal` is `z-index: 50`, so a confirmation
raised from inside the orphans tray — whose markup `_loadTray` injects wholesale
from the orphans page — renders underneath the tray that raised it.

Finally, there is no page-scroll lock anywhere in the app, and no shared "an
overlay is open" state to hang one on: open-state lives in `galleryUI`
(`sheet.open`, `confirm.open`, `change.open`, `sortOpen`, `importOpen`,
`orphansOpen`, `viewer`, `finder.preview`), in `orphansPage`, and in the
standalone `menuOpen` scope on the topbar.

## Goals / Non-Goals

**Goals:**

- Change Poster closes as readily as the poster action tray it was opened from.
- Drag-to-dismiss is never taken over by the browser mid-gesture.
- Every tray leaves enough backdrop above it to tap.
- Scrolling inside a tray does not leak to the page.
- A confirmation raised inside a tray is visible above it.

**Non-Goals:**

- Redesigning the desktop modal presentation. Desktop keeps the centred dialog
  and its `×` unchanged.
- Converging `.sheet` and `.modal` into a single component. That is the clean
  end state, but it re-plumbs desktop and is far more churn than this problem
  justifies. This change makes the two presentations agree on mobile behaviour
  and leaves the duplication in place.
- Adding a slide-down exit animation. No tray has one today; introducing one is
  a separate concern.
- Adding a close button to any tray. Dismissal stays the grab handle, the
  backdrop, and Escape.
- Any change to PHP, routing, or data behaviour.

## Decisions

### Give mobile modals the real `.sheet` skeleton, rather than patching CSS

The three modals get grip / head / body structure in their markup, and the mobile
block restyles them using the sheet rules instead of its own parallel set. The
panel goes back to `overflow: hidden` and a `.sheet__body`-equivalent becomes the
scroller.

*Why:* it is the only option that makes the drag behave, because the property
that makes sheets work — a drag target that is a distinct element carrying
`touch-action: none`, separate from the scroller — cannot be expressed on a
single element that must both scroll and be dragged. It also means the existing
gesture handler in `gallery.js` needs no changes: it already matches
`.sheet__grip, .sheet__head` and already resolves the backdrop generically.

*Alternative rejected — patch in place:* keep the pseudo-element handle, add
`overscroll-behavior: contain`, restore the `×`. Cheapest, and it would fix
"cannot close". But the drag would stay unreliable and the tray would still not
feel like the others, which is the actual complaint.

*Alternative rejected — converge on one component:* render everything as
`.sheet` and restyle *that* into a centred dialog on desktop. Best end state,
removes the layering mismatch for free, but the blast radius covers every dialog
on desktop for a mobile bug.

### No close button; instead, bring the panel height to parity with sheets

`.modal__close` stays hidden on a small screen and no close control is added to
the tray head. Dismissal remains the grab handle plus the backdrop, exactly as on
the sheets. To keep the backdrop a real target, the mobile modal panel drops from
`max-height: 90vh` to the same height a sheet uses (`85vh`), and the Find Posters
grid is sized to fit within that rather than pushing the panel taller.

*Why:* a close button is not the idiom the mobile experience was built around —
no tray has one, and the sheets are dismissible without one. The reason Change
Poster is unclosable is not the absence of a button; it is that its handle cannot
be grabbed and its panel is taller than any sheet, so both of the intended routes
fail at once. Fixing the handle (above) restores the first route, and the height
parity restores the second. Adding a button would paper over both rather than fix
either.

*Consequence accepted:* with no button, drag reliability is load-bearing rather
than a nicety, which is why the gesture fix is task group 1 and why device
validation on both iOS and Android is a required step rather than an optional
one. Escape-to-dismiss already exists on every overlay and is unaffected.

*Alternative rejected — a close control in the shared tray head:* structurally
guarantees dismissal without depending on a gesture, and would help anyone who
cannot perform a drag. Rejected because it adds an affordance to all seven trays
to compensate for a defect in one, and diverges from the app-style presentation
the mobile experience deliberately adopted.

### Contain overscroll on every tray scroller

`overscroll-behavior: contain` on the tray body, on `.find-grid`, and on any
other scrolling region inside an overlay.

*Why:* it is the direct expression of the requirement and is supported in Chrome,
Firefox, and iOS Safari 16+ *on scroll containers*. Note the asymmetry that
drives the next decision: iOS Safari does **not** honour `overscroll-behavior`
on the document itself.

### Layer trays and dialogs on one scale

Give the two presentations a shared, ordered set of z-index values so a dialog
always sits above a tray. The current 55-vs-50 inversion is not a deliberate
choice; it predates confirmations being reachable from inside a tray.

*Why in scope rather than deferred:* it is a dismissal defect in the same code
path — a confirmation you cannot see is a tray you cannot answer — and it is a
few lines once the two presentations are already being reconciled here.

### Page-scroll lock: scope-agnostic observer, and explicitly droppable

A `MutationObserver` watching the inline `display` that `x-show` writes on
`.sheet, .modal, .viewer` elements, toggling a class on `<html>`. The elements
are static per page, so they can be collected once and observed individually.

*Why an observer over shared Alpine state:* it needs no per-overlay wiring and
covers `menuOpen`, which lives outside both page components by design. It also
matches the house pattern already established by the drag handler, which is
deliberately written to work "for every tray without knowing which Alpine scope
owns it".

*Why the lock technique matters:* `body { overflow: hidden }` is unreliable on
iOS Safari, and `overscroll-behavior` on the document is unsupported there, so
neither shortcut works. The reliable technique is `body { position: fixed; top:
-<scrollY>px }` with an exact `window.scrollTo` restore on unlock.

*Why it is sequenced last and written to be droppable:* that technique has a
well-known failure mode with an on-screen keyboard — iOS scrolls the layout
viewport to reveal a focused input and can leave the offset stranded after the
keyboard dismisses — and Change Poster is the one overlay with a text input and a
file input. If it cannot be made clean, this item is dropped. Its spec
requirement ("The page behind an open overlay does not scroll") is written as a
standalone requirement precisely so it can be removed without touching the
others, and the user has described this symptom as occasional rather than
blocking.

## Risks / Trade-offs

- **[iOS keyboard displaces the page while the lock is active]** → Verify on a
  real iOS device with the Change Poster URL field before keeping the lock. If
  the offset cannot be pinned reliably (via `visualViewport`, or by not locking
  while an input inside the overlay has focus), drop the lock task; items 1 and 2
  stand alone and resolve the reported problem.

- **[Restored scroll position drifts and infinite scroll appends pages]** → The
  sentinel observer uses `rootMargin: '600px'`, so a small drift is enough to
  fire `loadMore()`. Restore with the exact integer captured at lock time and
  confirm by opening and closing a tray partway down a long gallery, checking
  that no extra posters were appended.

- **[Restructuring three modals regresses the desktop dialogs]** → The new
  elements must be inert above 640px; the desktop dialog keeps its `×` and its
  centred layout. Covered by the existing functional template tests plus new
  assertions.

- **[With no close button, a regressed gesture makes a tray unclosable again]** →
  This is the cost of keeping the app-style presentation. Mitigated by the
  backdrop staying a full-size target at every tray height, by Escape remaining
  wired on every overlay, and by the asset test in task 6.2 pinning the
  `touch-action` rule that makes the gesture reliable, so a future stylesheet edit
  cannot silently undo it.

- **[Capping the panel at 85vh crowds the Find Posters grid]** → The grid is
  already its own scroller at `max-height: 62vh`; it needs to be re-sized against
  the smaller panel so the tray does not end up with a scroller inside a scroller
  that both want the same 5vh. Check on the smallest supported viewport with a
  full set of results.

- **[`_loadTray` injects overlay markup it does not own]** → The orphans page's
  confirmation markup arrives inside the orphans tray via `innerHTML`. The
  layering fix must hold for markup in that position, not only for overlays that
  are direct children of the page root.

- **Trade-off accepted:** `.sheet` and `.modal` remain two components. This
  change makes them agree on phone behaviour without unifying them, so the
  duplication — and the risk of them drifting apart again — persists.

## Open Questions

- Should the drag-to-dismiss be suppressed while the tray's body is scrolled away
  from the top, as native sheets do? Not required by the spec, and the existing
  handler does not do it for sheets today; noted in case it surfaces during
  validation.
