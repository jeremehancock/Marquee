## Context

The gesture is a port. Glimpse — the sibling application in this workspace —
shipped exactly this over three changes: `animate-tab-transition` (a discrete
swipe that played a slide after the finger lifted), `drag-the-tab-transition`
(which replaced it with a drag, because a swipe that worked and one that fell
short produced the same first frame), and `remove-tab-drag-lift` (which stripped
the scale and scrim, because a vertical drop at the start of a horizontal
gesture reads as the page glitching). The end state of that sequence is what is
being ported, not the sequence.

Marquee arrives at this in better shape than Glimpse did. **Changing category is
already a client-side operation**: `gallery.js` intercepts a tab click, fetches
the destination, swaps `#results`, updates the title and pushes a history entry
(`gallery.js`, the `.tabs a` branch of the delegated click handler and `load()`).
The gesture therefore adds a way to ask for something that already exists rather
than a new navigation model. Two more pieces are already built and reusable:
`anyOverlayOpen()` in the scroll-lock block, which is the refusal the gesture
needs; and the tray dismissal drag, which is the same gesture shape on the
vertical axis and establishes the house style for touch handling.

Four differences from Glimpse shape every decision below.

1. **Content lives on the server.** Glimpse renders the incoming tab
   synchronously at axis-lock, affordable because it keeps the inactive tab warm
   at idle and skips the rebuild when a render signature says it need not.
   Marquee's equivalent of that render is a network fetch.
2. **Five categories, not two.** All, Movies, Shows, Seasons, Collections.
   Resistance applies at two ends; three interior categories commit both ways.
3. **The tab bar is fixed to the bottom of the viewport** and all five fit at
   once (`app.css`, the phone `.tabs` block). Glimpse's tab controls travel with
   the content; Marquee's cannot and need not.
4. **The phone has infinite scroll.** The outgoing grid can be several pages and
   many thousands of pixels tall.

Constraints: no Node build step, so this is hand-written ES5-flavoured JS in
`gallery.js` alongside everything else; no browser in CI, so `composer test`
cannot observe a transform; and the gesture must not exist at all on pointer
devices.

## Goals / Non-Goals

**Goals:**

- A drag that follows the thumb one-to-one from the first few pixels, with the
  outgoing and incoming categories edge to edge and never overlapping.
- Commit and abandon both animated from the distance left to travel; neither
  latched, so a drag past the threshold can still be brought back.
- A committed drag indistinguishable in outcome from tapping the tab, via one
  shared code path rather than two that must agree.
- Zero change on pointer devices, and zero change with JavaScript off.
- No state left pinned by any interruption.

**Non-Goals:**

- Restoring scroll depth when returning to a category. Today a category switch
  shows page one from the top; that stays true. Preserving depth would make the
  prefetch cache decide what the viewer sees rather than only how fast.
- Swiping anywhere but the gallery. The Change Poster dialog's own tabs
  (Upload / From URL / Plex Posters / Find Posters) are a separate surface and
  are explicitly out of scope; a drag inside any overlay belongs to that
  overlay.
- Swiping on desktop, or a mouse-drag equivalent.
- Any change to what a category contains, to pagination, or to sort.
- Free-scrolling momentum across multiple categories in one gesture. One drag
  moves at most one category.

## Decisions

### 1. Prefetch both neighbours; degrade to a placeholder, never to a refusal

**Decision.** After a category settles, fetch the results of both adjacent
categories in the background and hold their HTML. A drag uses a held copy when
it is current; otherwise it opens a placeholder panel and fetches during the
gesture.

**Why.** Glimpse's drag depends on the incoming tab being renderable in the
frame the gesture is claimed — 175.3ms to rebuild 7,000 items against 1.4ms to
prove it need not be rebuilt, so warming at idle is what makes the gesture
possible at all. The same logic applies here with a fetch in place of the
render. On a self-hosted box the fetch is usually fast, but "usually" is not a
foundation for the first frame of a gesture.

**Alternatives considered.**

- *Discrete swipe, recognised at `touchend`* (Glimpse's first version). Rejected
  for the reason Glimpse rejected it, made worse here: a network round trip
  would sit between the finger lifting and anything moving.
- *Fetch on claim, no prefetch.* Honest and cache-free, and it is retained as
  the fallback path — but as the only path it shows a placeholder on every
  single gesture, which is a worse default than a stale-check.
- *Prefetch as a hard requirement.* Rejected. The spec states the gesture must
  be fully correct with every held copy absent, so the cache can never be the
  reason something is wrong — only the reason it was quick.

**The currency check is smaller than it first appears.** The inputs to what a
category shows are the search term, the sort order, and the library itself.
Sort turns out to be free: there is exactly one sort path and it does a full
`window.location.assign()`, so a sort change reloads the page and takes the
cache with it. That leaves the search term — compared as a string — and library
mutations, which are already funnelled through `submitForm()` and the
`gallery:refresh` event. A counter incremented at those two points, stored
alongside each held copy, covers deletes, poster changes and completed imports
without enumerating them.

The comparison errs toward discarding. Wrongly discarding costs one fetch;
wrongly trusting shows a grid that does not match the viewer's search — the
failure mode the spec calls a wrong library that looks like a working one.

### 2. `#results` keeps its identity; a sibling panel holds the incoming category

**Decision.** During a drag, insert a sibling element beside `#results` to hold
the incoming category. Both are pinned out of the scroller and translated. On
commit, the incoming HTML goes through the existing `setResults()` into
`#results` and the sibling is removed.

**Why.** `#results` is the swap target for every existing no-reload path —
search, paging, `load()`, `gallery:refresh` — and `setResults()` is where
`initImages()` and `setupInfinite()` are re-armed. Moving or renaming it would
put the gesture in the middle of five unrelated flows. A sibling that exists
only for the length of a gesture touches none of them, and the commit reduces to
one call the rest of the file already makes.

**Alternative considered.** Wrapping a pane inside `#results` and moving that.
Rejected: `results.innerHTML = html` would destroy the wrapper on every
unrelated update, so every caller would need to know about it.

### 3. Both panels leave the scroller for the gesture's duration

**Decision.** Pin both panels with `position: fixed`, at the box measured from
`#results` before any style is written, and set the page to 0 in the same frame.

**Why.** The two categories scroll as one document, so with both in flow the
incoming one cannot show its top while the outgoing shows where the viewer was.
Pinning both — rather than only the outgoing one — is what makes an abandon
possible: a page already reset to 0 has discarded the offset it must return to,
so the captured offset stays the only record of where the viewer was. A fixed
element contributes no scrollable overflow, so pinning both collapses the
document to viewport height and the scroll position becomes irrelevant until
teardown.

**Two traps carried over from Glimpse**, both worth stating because neither is
visible in review:

- *The box must be written, not implied.* A fixed element does not inherit
  `.container`'s 14px gutters, so pinning to `left: 0; right: 0` widens the grid
  by 28px the instant a thumb lands and narrows it again on release. Glimpse
  shipped this bug invisibly for weeks because the lift's scale happened to
  shrink the over-wide panel back to within 2px; removing the lift uncovered it.
  Marquee has no lift to hide it, so it would be visible on day one.
- *Measure before writing, then never again.* One `getBoundingClientRect()` at
  setup, above every style write, is free — layout is clean at that instant. The
  same read after a write forces a synchronous re-layout on the gesture's
  opening frame. The tracking loop reads nothing at all; the origin, width and
  scroll offset are all captured at the claim.

### 4. One category-change routine, shared by tap and commit

**Decision.** Extract the body of the existing `.tabs a` click branch into a
routine taking a destination pathname, and call it from both the click handler
and the drag's commit.

**Why.** A committed drag has to leave the same state a tap leaves: active tab,
results, title, history entry, carried-over search, scrolled to top, infinite
scroll re-armed. That is seven things, and two code paths that must agree about
seven things will stop agreeing. The commit's only addition is that it may
already hold the HTML, so the routine takes an optional pre-fetched body and
skips the fetch when given one.

### 5. The gesture is claimed at 8px, non-passively, and never re-arbitrated

**Decision.** `touchstart` records the origin and claims nothing. `touchmove` is
registered `{ passive: false }` from the outset; on the first move exceeding 8px
on either axis, the dominant axis wins and holds for the life of the touch.

**Why.** Registering passive and upgrading later is not possible, and it is not
merely a lost optimisation: on iOS a touch sequence whose early moves went
uncancelled has already been given to the scroller, and later `preventDefault()`
calls are ignored *silently*. The gesture then works on every platform except
the one it was written for, with nothing in the console. The single arbitration
is what stops a moving page being handed back to the scroller halfway through.

8px is the lock distance, not the commit distance. The commit test is ⅓ of the
viewport or a flick above the velocity threshold — the two are unrelated
measurements that an earlier Glimpse version had conflated at 100px, which made
the drag unable to move until the gesture was already nearly decided.

**Velocity is read from the end of the gesture, not its average**, over a short
trailing sample window: a slow drag that finishes in a flick is a flick.

### 6. Refusal is a `touchstart` decision, and reuses the scroll lock's check

**Decision.** At `touchstart`, refuse (by locking the axis to vertical) if
`anyOverlayOpen()` is true, if the touch began inside `.sheet`, `.modal`,
`.viewer` or a backdrop, or if it began on `.tabs`. Promote `anyOverlayOpen()`
out of the scroll-lock IIFE so both use one function.

**Why.** Refusing at `touchend` is adequate only when nothing has moved. A drag
that discovers the conflict later has already suppressed the browser's handling
and taken both grids out of the scroller. Sharing the overlay check matters
because two independent answers to "is an overlay open" will drift, and the cost
of them disagreeing is a gesture fighting a tray — the existing tray dismissal
drag lives on the other axis of the same touches.

`.tabs` is excluded because a touch on the bottom bar belongs to the bar. This
is not the horizontal-scroll exclusion an earlier reading of the code suggested:
the phone bar is `flex: 1 1 0` across five columns and never scrolls. The
exclusion is about ownership, not overflow.

### 7. The bar marks the destination at the claim, not the release

**Decision.** Call the existing `syncActiveTab()` with the destination when the
gesture is claimed, and with the origin again if it is abandoned.

**Why.** This is what Glimpse does — `applyTabState()` runs inside its setup, not
its commit. The mark is the application's acknowledgement that the gesture was
recognised, and an acknowledgement owed at the start of a gesture is not worth
much at the end of it. A mark that interpolated between two tabs was considered
and rejected: at five equal columns it would be a smear across a fifth of the
screen, and it has no meaning at a resisted end.

The bar itself does not move. It is `position: fixed`, so the grids slide
beneath it for free — and it is the only stationary thing left on screen during
a drag, which is what the movement is read against.

### 8. Reduced motion is an exception, stated rather than omitted

**Decision.** The panels follow the finger under reduced motion; the settle
after release is effectively instant.

**Why.** The app-wide suppression rule exists so a new animation is covered
without being listed, which makes any exception a thing that must be argued
rather than assumed. The argument is that the rule removes motion the
application performs *at* the viewer, and a panel moving because a thumb is
moving it is not that. Freezing it would leave the gesture with no feedback
rather than less. The travel after the finger lifts is motion the application
performs on its own, and is suppressed.

The same account already covers the tray dismissal drag, which has been
following fingers under reduced motion since it shipped without the rule ever
saying so. The `visual-design` delta writes the account down and names both, so
the next drag has a test to meet rather than a precedent to infer.

### 9. Verification: what a PHP test can and cannot see

**Decision.** Pin the source decisions in a new `tests/Unit/Asset/TabSwipeTest.php`,
alongside `TrayDismissalTest.php` which does the same for the other gesture.
State plainly, here and in the tasks, that the behaviour itself is verified by
hand on a real device.

**Why.** `composer test` has no browser. A PHPUnit test that reads `gallery.js`
and `app.css` can assert the decisions that are visible in the source and would
be silently reverted otherwise — the listener registered non-passive, the axis
decided once, the refusal at `touchstart` rather than `touchend`, the shared
`anyOverlayOpen()`, the measured box written inline rather than `left: 0;
right: 0`, no `scale(` in the drag transform, the commit and lock distances
being different constants, and the reduced-motion carve-out naming both drags.
It cannot see 60fps, a 23px drop, or a panel that fails to follow a thumb. Every
assertion should be demonstrated failing against its own bug before it is
committed, which is this project's existing standard for source-shape tests.

## Risks / Trade-offs

**A slow or cold fetch shows a placeholder mid-gesture** → The prefetch covers
the common case; the placeholder is a designed state rather than a failure, and
the existing deferred loading indication already handles the case where a commit
outruns its content. The gesture never blocks on the network.

**The outgoing grid can be enormous after several pages of infinite scroll** →
It is pinned and translated, not re-laid-out; a transform on a promoted layer is
cheap regardless of height. Glimpse measured 59.9fps translating a 1,228,722px
document. What cost there was landed on the incoming side's first layout, which
is why the panel is populated before the slide rather than during it.

**Two fetches per category change** → Small HTML fragments to a local
self-hosted server, issued after the active category has settled so they never
delay it. If this proves noticeable on a large library, the prefetch can be
narrowed or dropped entirely without touching correctness — by construction it
is an optimisation.

**iOS silently ignoring a late `preventDefault()`** → The single most likely way
this ships broken on the platform that matters most, and invisible in
desktop-emulated touch. Addressed by decision 5 and pinned by a source test, but
it is the first thing to check on a real iPhone.

**The interaction between the drag and infinite scroll's `IntersectionObserver`**
→ Pinning both panels collapses the document, which can fire the sentinel. The
teardown routine re-arms infinite scroll for whichever category ends up active,
and the observer is disconnected for the panel that is discarded.

**A gesture that half-works is worse than none** → The whole gesture is behind
`isTouch()`, and the tabs remain plain links. Backing it out is deleting one
block; there is no data migration, no setting, and no persisted state.

## Migration Plan

None required. No schema, no settings key, no environment variable, no route.
Deployment is the ordinary image build. Rollback is reverting the commit; a
client holding the old cached `gallery.js` from the service worker simply does
not have the gesture, which is the pre-change behaviour.

## Open Questions

- **Where the gesture is taught.** It is undiscoverable by inspection. Glimpse
  shows a first-load tip. Whether Marquee wants one, and whether it belongs to
  this change or a follow-up, is a product call and is deliberately left out of
  the specs so the answer is not pre-empted by silence.
- **Whether resistance at the ends should be tuned differently from Glimpse's
  damping factor** — a five-category strip reaches its ends less often than a
  two-tab one, so the resisted case may want to be more pronounced rather than
  less. Best answered on a device rather than in a document.
