## Context

The gallery intercepts pagination clicks and swaps the results grid in place
(`load()` in [gallery.js](../../../public/assets/gallery.js)), so the browser
never performs a navigation and never resets the scroll position. Because the
pagination control sits below the grid, the user is always at the bottom of the
document when they click it — and stays there while an entirely new set of
posters appears above them.

Two neighbouring behaviours constrain the design:

- The tab-switch handler already does `window.scrollTo(0, 0)` before its
  `load()`, so returning to the top on a view change is an established pattern
  here; only pagination was missed.
- The overlay scroll lock restores the pre-open offset with
  `window.scrollTo(0, scrollY)` when a tray or modal closes. That restore must
  stay instantaneous — animating it would show the page visibly sliding back
  every time a tray is dismissed.

On a narrow screen `.pagination` is `display: none` and infinite scroll appends
pages instead, so this change has no reachable effect there.

## Goals / Non-Goals

**Goals:**

- Following any pagination link puts the view back at the top of the gallery.
- The movement is a smooth scroll, started at click time so it overlaps the
  fetch rather than waiting on it.
- `prefers-reduced-motion: reduce` gets the same end position with no animation.

**Non-Goals:**

- Changing the tab-switch scroll. It is already instant and correct; making view
  switches smooth is a separate call.
- Restoring or animating scroll on browser back/forward (`popstate`). The
  browser's own scroll restoration keeps applying.
- Any change to infinite scroll, the pagination markup, or the server.

## Decisions

### Scroll in JS at the click site, not via CSS `scroll-behavior`

Setting `scroll-behavior: smooth` on `html` would be one line, but it applies to
*every* programmatic scroll on the document — including the scroll-lock restore
described above, which would start animating on every tray dismissal. A
per-call `window.scrollTo({ top: 0, behavior: … })` in the pagination branch
affects exactly the one interaction being changed.

### Scroll before `load()`, not after the swap

The scroll is issued in the same tick as the click, before the fetch is started,
matching the tab-switch handler. Alternatives considered:

- *After the results are swapped in* — the user watches the old page sit still
  for the length of a network round trip, then jumps. The animation would also
  begin exactly when the DOM is being replaced, the least stable moment.
- *Before the swap* — the motion is immediate feedback that the click landed,
  and it runs in parallel with the fetch, so the new grid is usually already in
  place when the scroll finishes.

Scrolling to offset 0 is safe regardless of what the new page does to the
document height: 0 is always a reachable position, so a shorter destination page
cannot leave the animation targeting a clamped offset.

### Reduced motion read at call time

`window.matchMedia('(prefers-reduced-motion: reduce)').matches` is evaluated on
each activation rather than cached at startup, so a user who changes the system
setting mid-session gets the new behaviour without a reload. `behavior` becomes
`'auto'` (the browser default, an instant jump) rather than skipping the scroll —
the destination is a requirement, only the animation is a preference.

### One shared helper

The scroll is expressed as a single small helper rather than inlined, so there
is one place that knows about the reduced-motion check. The tab-switch call site
keeps its existing instant `scrollTo(0, 0)` per the non-goal above.

## Risks / Trade-offs

- **A smooth scroll from deep in a long page takes a beat, and a user who
  immediately clicks the next page number interrupts it** → Harmless: the second
  click issues its own scroll to the same target, and browsers replace an
  in-flight smooth scroll rather than queueing it.
- **Focus is not moved, so a keyboard user who activated a pagination link keeps
  focus on the control they can no longer see** → Out of scope here and
  pre-existing (the tab switch has the same shape). Not introduced by this
  change; worth a separate accessibility pass.
- **No JS test runner in this repo, so the animation itself cannot be asserted**
  → Follow the established pattern of a source-shape tripwire test (see
  `tests/Unit/Asset/GalleryLoadingIndicationTest.php`) plus hand verification
  against the `:dev` image.

## Migration Plan

None — a behavioural change to one client-side handler, shipped in the image.
Rollback is reverting the commit.

## Open Questions

None.
