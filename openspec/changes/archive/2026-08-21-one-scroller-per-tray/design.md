## Context

On a narrow screen the Change Poster dialog is presented as a bottom tray. Two
scroll containers end up nested inside it:

1. `.modal__body`, made the tray's scroller by the `@media (max-width: 640px)`
   block — `flex: 1 1 auto; min-height: 0; overflow-y: auto;
   overscroll-behavior: contain`.
2. `.poster-groups`, the stack shared by the Plex Posters and Find Posters
   tabs — `max-height: 62vh; overflow-y: auto; overscroll-behavior: contain`,
   with no mobile override at any width.

Both are sized against the viewport, but roughly 230–270px of fixed-pixel
chrome sits between them inside the panel: the grip (~17px), the head (~54px),
the poster title line (~36px), the tab strip (~71px), the "Tap a poster to
preview it." hint (~36px), and the body's `20px + env(safe-area-inset-bottom)`
of bottom padding.

```
┌─ .modal__panel  max-height: 85vh, overflow: hidden ────┐
│  ▬▬  grip                                    ~17px    │
│  Change poster                               ~54px    │
│ ┌─ .modal__body  flex:1, overflow-y: auto ──────────┐ │  ← scroller #1
│ │  Poster title                             ~36px   │ │
│ │  [Upload][From URL][Plex][Find]           ~71px   │ │
│ │  Tap a poster to preview it.              ~36px   │ │
│ │ ┌─ .poster-groups  max-height: 62vh ────────────┐ │ │  ← scroller #2
│ │ │  ▢ ▢ ▢                                       │ │ │
│ │ │  ▢ ▢ ▢                                       │ │ │
└─┴─┼──────────────────────────────────────────────┼─┴─┘  ← panel clips here
    │  ▢ ▢ ▢   ← last row, below the panel's edge  │
    └──────────────────────────────────────────────┘
```

On an 844px-tall viewport the panel is capped at 717px, the body's available
height is ~646px, and the body's content measures 523px (62vh) + ~200px of
title/tabs/hint + ~54px of padding ≈ 777px. The ~130px of overflow is almost
exactly one poster row at the phone's 100px column width. The shorter the
viewport, the worse the ratio, because the fixed-pixel chrome takes a larger
share of it.

Both reported symptoms follow:

- **The last row is cut off** because the inner box's bottom ~130px lies below
  `.modal__panel`'s `overflow: hidden` edge.
- **The second scrollbar appears dead** because `.modal__body` genuinely has
  ~130px of range, but every flick starting over the grid is consumed by the
  inner scroller, whose `overscroll-behavior: contain` then refuses to chain
  the remainder up to the parent. The parent moves only for a drag begun on the
  thin band of title, tabs, or hint text — which is not where anyone drags.

### How it got here

This is a coverage gap, not an oversight in reasoning. The same diagnosis is
already written in this stylesheet, one media query away, against `.find-grid`:

> The Change Poster tray body is already the scroller, so the results grid does
> not need to be a second one nested inside it — two scrollers competing for
> the same few vh is how a flick ends up moving the wrong thing.

The `group-find-posters-by-source` change then **moved** `max-height: 62vh` and
`overflow-y: auto` up from `.find-grid` to `.poster-groups`, because a sticky
heading can only travel inside its own scroll container and the headings had to
pin across a whole stack of grids. Its design records the move. What did not
move with it was the mobile reset: the desktop rule was relocated, the mobile
one stayed pointing at the element that no longer scrolls. The reasoning
survived; its coverage did not.

## Goals / Non-Goals

**Goals:**

- Exactly one scrolling region inside the Change Poster tray on a phone, so a
  flick anywhere in the tray moves the same thing.
- Every candidate reachable, including the last row, on a short viewport as
  well as a tall one.
- Group headings keep behaving as specified: visible while their own group is
  on screen, leaving with it.
- The desktop presentation is untouched.

**Non-Goals:**

- Pinning the tab strip and poster title while candidates scroll under them.
  Considered and deferred; see Decisions.
- Converting `vh` to `dvh` anywhere. Separate change, separate blast radius.
- Any change to the drag-to-dismiss gesture, the tray's height cap, or the
  overscroll containment guarantee itself.

## Decisions

### Hand the scroll up to the tray body

Add to the existing `@media (max-width: 640px)` block:

```css
.poster-groups {
    max-height: none;
    overflow-y: visible;
}
```

`.modal__body` becomes the tray's only scroller. The grouped stack lays out at
its natural height, the panel no longer clips content that nothing can scroll
to, and the second scrollbar disappears because there is no second scroll
container.

The rule is a deliberate mirror of the `.find-grid` reset ten lines above it,
and the new comment should say so and name the move that stranded it —
otherwise the next person to relocate the scroll has no reason to look for a
mobile counterpart either.

**Alternative considered — pin the chrome and flex the grid.** Make
`.modal__body` a flex column on a phone, give the tab panel and
`.poster-groups` `flex: 1; min-height: 0`, and drop the `vh` cap. Also yields
one scroller, and a nicer one: title and tabs stay put like a native app, so
switching tabs does not require scrolling back up. Rejected for this change on
size. It needs a class on the currently unclassed tab-panel `<div>`, it makes
`.modal__body` stop being the scroller for two of four tabs — which collides
with `TrayDismissalTest::testTrayBodyIsTheScroller` and the mobile overscroll
assertion — and it changes the geometry the drag-dismiss handler assumes. That
is a design change to the tray, worth proposing on its own merits, not a bug
fix.

**Alternative considered — shrink the cap to fit.** Replace `62vh` with a value
that accounts for the chrome, e.g. `calc(85vh - 270px)`. Rejected outright: it
leaves both scrollers in place, so the flick still moves the wrong thing and
the dead second scrollbar remains. It also hard-codes a chrome height that
changes whenever the head, tabs, or hint text change, and gets it wrong on any
device whose safe-area inset differs.

### The desktop rule keeps the cap, and that is not inconsistency

Above 640px, `.modal__body` has no `overflow` declaration at all — the mobile
block is its only one — so the dialog's body is not a scroller and
`.poster-groups` is the only one there is. Removing the cap globally would let
a well-covered title's stack grow past the viewport with nothing scrolling it,
and would break the sticky headings, which need a scroll container to travel
in. `PosterGroupsTest::testTheGroupStackOwnsTheScrollAndNotTheGridsInsideIt`
reads the base rule and must keep passing unchanged.

So the two presentations legitimately own the scroll at different levels: the
dialog's stack owns it, the tray's body owns it. What must not happen is both
at once.

### Sticky headings re-parent, and the requirement still holds

`position: sticky` pins to the nearest scrolling ancestor. With the inner
scroller gone on a phone, `.poster-group__heading` pins to `.modal__body`
instead — flush under the tray head, since that body has no top padding. The
heading is already `background: var(--surface)` and `z-index: 2`, so the title
and tab strip scroll opaquely beneath it exactly as candidates already do.

The behaviour `poster-sources` requires — "While a group's candidates are on
screen, its heading SHALL remain visible as the user scrolls, and SHALL leave
with its own group" — is written about what the user sees, not about which
element is the scroll container, and is preserved. No delta to `poster-sources`
is expected. Confirm it against the running tray rather than assuming it.

### Overscroll containment is not weakened

`overscroll-behavior: contain` stays on `.modal__body`, on `.sheet__body`, and
in `.find-grid`'s base rule — the three
`TrayDismissalTest::testTrayScrollersContainTheirOverscroll` asserts. The
declaration left on `.poster-groups` simply becomes inert on a phone, since the
property has no effect on an element that does not scroll; it is harmless and
is left in place rather than split across the media query for the sake of it.

What changes is only that a *nested* scroller is no longer expected inside this
tray on a phone. The spec must be rewritten to guarantee containment for
whatever scrolling regions a tray has, rather than for a nesting arrangement
that no longer exists — and while there, to drop a detail that is already
stale: the requirement names "the Find Posters results grid" as the nested
region, which stopped being true when the scroll moved up to the grouped stack.

## Risks / Trade-offs

- **The tab strip scrolls away on a phone.** → Accepted, and the cost is small:
  the tabs sit at the top of the tray's content, so returning to them is the
  same upward flick that returns to the top of the results. The alternative
  that keeps them is documented above and can be proposed later; this change
  does not foreclose it.

- **A group heading now pins to the tray head, higher up the screen than
  before.** → Visually this is the improvement, not the risk: the label lands
  at the top edge of the scrollport instead of floating at the top of an inner
  box mid-panel. Worth a look on both tabs, since Plex Posters can show two
  headings in quick succession where Find Posters usually shows one at a time.

- **No test can catch the general fault.** → The tripwires added here pin *this*
  reset. They cannot catch a future rule that introduces a different nested
  scroller inside a tray, any more than the `.find-grid` comment caught
  `.poster-groups`. That is precisely why the added spec requirement is written
  about reachability of a tray's contents rather than about this one selector —
  a reviewer has something to check against, even where a test cannot look.

- **The arithmetic above is measured, not exact.** → Element heights were read
  from the stylesheet, not from a device. They establish that the overflow is
  real and roughly one row; they are not a contract, and no rule should be
  written against those numbers.
