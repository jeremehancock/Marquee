## Why

On a phone, the bottom of the Change Poster tray is unreachable. Open Find
Posters for a well-covered title and the last row of candidates is cut off at
the panel's edge — no flick brings it into view. A second scrollbar appears
beside the first and does not appear to move.

Both symptoms are one fault: two scroll containers are nested inside the tray,
and the outer one holds the missing rows while the inner one swallows every
gesture aimed at it. A user who cannot reach the last row cannot choose the
poster it holds, and nothing on screen explains why.

## What Changes

- On a narrow screen, the stack of grouped candidates stops being its own
  scrolling region. The tray body becomes the single scroller for the Change
  Poster tray, so one flick moves everything and the last row is reachable.
- The group headings continue to stay with their groups as the user scrolls;
  they pin to the top of the tray body instead of to the top of an inner box.
- Accepted trade-off: the tab strip and the poster title scroll away with the
  rest of the tray's contents on a phone. Keeping them pinned is a larger
  structural change and is deliberately not attempted here.
- Unchanged on a pointer device. The dialog is not a tray there, its body does
  not scroll, and the grouped stack remains the scroller that the sticky
  headings depend on.

Not a breaking change; no user-facing setting, route, or stored data is
touched.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: the requirement "Scrolling within a tray stays within the
  tray" currently guarantees overscroll containment *for a scrolling region
  nested inside another scrolling region*, naming the Change Poster tray as the
  case. That nesting is what this change removes on a phone, so the requirement
  is rewritten to guarantee containment for whatever scrolling regions a tray
  has, without depending on one being nested inside another. Its example is
  also stale — the region it names stopped being the nested one when the
  grouped stack took over the scroll — and is corrected.

  A new requirement is added for the defect itself: a tray's contents must be
  reachable in full by scrolling, with nothing left clipped beyond the panel's
  edge. Nothing in the spec says this today, which is why the fault was
  expressible without any requirement being violated.

## Impact

- `public/assets/app.css` — one added rule inside the existing
  `@media (max-width: 640px)` block. The desktop rules are untouched.
- `tests/Unit/Asset/PosterGroupsTest.php` and
  `tests/Unit/Asset/TrayDismissalTest.php` — the existing CSS-shape tripwires
  gain coverage for the mobile reset, so a future edit that reintroduces the
  nested scroller fails rather than shipping.
- No PHP, template, or JavaScript change. The drag-to-dismiss handler in
  `gallery.js` already keys on the tray body and needs nothing.
- Both tabs that render grouped candidates are affected on a phone: Find
  Posters and Plex Posters.

### Out of scope

Converting the stylesheet's `vh` units to `dvh`. On iOS Safari `85vh` measures
the large viewport while the tray's fixed container is the visual viewport, so
a panel can be capped taller than the box that holds it. That is a real but
separate and much smaller source of clipping, it affects every tray rather than
this one, and it gets its own change.
