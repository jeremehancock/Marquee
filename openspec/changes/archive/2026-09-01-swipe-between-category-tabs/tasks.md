## 1. Groundwork in `gallery.js`

Nothing here is visible to a user. Each step leaves the application working
exactly as it does today, so a failure at any point is isolated.

- [x] 1.1 Promote `anyOverlayOpen()` out of the page scroll-lock IIFE into the
      module scope so the gesture and the lock share one answer. Leave the lock's
      call site reading identically; the only change is where the function lives.
- [x] 1.2 Extract the body of the delegated click handler's `.tabs a` branch into
      `switchCategory(pathname, options)`, where `options` may carry a
      pre-fetched results body. It must do what the branch does today: carry the
      live search term into the URL, `syncActiveTab()`, scroll to top, and
      `load(url, true)`. Rewire the click branch to call it. Verify by hand that
      tapping tabs, search, paging and back/forward are all unchanged.
- [x] 1.3 Teach `load()` to accept an already-fetched results body and skip its
      fetch when given one, still applying the title, the `#results` swap through
      `setResults()`, and the `pushState`. Existing callers pass nothing and are
      unaffected.
- [x] 1.4 Add an ordered list of the five category paths, derived from the
      rendered `.tabs` anchors rather than hardcoded, plus helpers for the
      neighbour in each direction (null at the ends).

## 2. The neighbour cache

- [x] 2.1 Add a module-level mutation counter, incremented in `submitForm()`
      after a mutation settles and in the `gallery:refresh` listener. This is what
      makes deletes, poster changes and completed imports invalidate held copies
      without enumerating them.
- [x] 2.2 Add the cache: a map from category path to `{ html, query, mutation }`.
      A held copy is current only when its `query` equals the live search input's
      trimmed value and its `mutation` equals the counter. Anything else is
      discarded rather than shown.
- [x] 2.3 Prefetch both neighbours after a category settles — on first load and at
      the end of every `switchCategory()` — using the same
      `X-Requested-With: fetch` request the rest of the file uses, and extracting
      `#results` the same way. Schedule it so it never delays the active
      category: fire it after the active load resolves.
- [x] 2.4 Confirm no work is needed for sort: the single sort path does a full
      `window.location.assign()`, so a sort change reloads the page and takes the
      cache with it. Note it in a comment at the cache so the next reader does
      not add a redundant sort key.
- [x] 2.5 Clear the cache on `popstate`, since the history entry being restored may
      predate mutations the counter has already moved past.

## 3. Panel presentation in CSS

- [x] 3.1 Add the sliding panel rules to `app.css`: a custom property carrying the
      horizontal offset, a `transform: translate3d(var(--offset), 0, 0)` with no
      scale and no vertical component, and `will-change: transform` applied only
      while a gesture is live.
- [x] 3.2 Add the pinned state: `position: fixed` with `top`, `left` and `width`
      written inline by the gesture from the measured box. The stylesheet must
      **not** declare `left: 0; right: 0` — see design decision 3; that is the
      bug that widens the grid by both container gutters the moment a thumb
      lands.
- [x] 3.3 Add the settle state: a transform transition whose duration is read from
      a custom property the gesture writes per release, and which is not present
      while the finger is down.
- [x] 3.4 Add `overflow-x: clip` (or equivalent) to the containing block for the
      duration of a gesture, so a panel parked a viewport away is never reachable
      by scrolling sideways.
- [x] 3.5 Confirm the app-wide reduced-motion rule already neutralises the settle
      transition, and that it does **not** reach the tracked transform. If the
      existing blanket rule catches both, narrow it here so the tracked offset
      survives — the panels must still follow the finger.
- [x] 3.6 Add the placeholder's appearance, reusing the existing spinner and grid
      metrics so a placeholder panel is the same shape as the grid it precedes.

## 4. The gesture

- [x] 4.1 Bind `touchstart` (passive) on the gallery root: record the origin and
      claim nothing. Lock the axis to vertical immediately when there is more than
      one touch point, when `anyOverlayOpen()` is true, when the target is inside
      `.sheet`, `.modal`, `.viewer` or a backdrop, or when it is inside `.tabs`.
- [x] 4.2 Bind `touchmove` **non-passively from the outset** — not upgraded later.
      Below 8px of travel on both axes, do nothing. At the threshold, decide the
      dominant axis once and hold it for the life of the touch; a vertical
      assignment returns for good.
- [x] 4.3 On a horizontal claim, run setup: measure `#results`' box **once, before
      any style is written**; pin `#results` at that box; insert and pin the
      incoming sibling one viewport away on the side it comes from; populate it
      from the cache or with the placeholder; set the page to 0; and call
      `syncActiveTab()` with the destination.
- [x] 4.4 When the incoming panel was populated with a placeholder, fetch its
      results and replace the placeholder in place without touching the panel's
      offset or interrupting the gesture.
- [x] 4.5 Track the drag: coalesce moves into one `requestAnimationFrame` write per
      frame, write both panels' offsets, and **read no layout property** — every
      value comes from the capture at 4.3. Push a `{x, t}` sample and trim the
      sample window.
- [x] 4.6 Implement the resisted case for a drag off either end: pin only the
      outgoing panel, apply a damped fraction of the travel, render no incoming
      panel, and never commit.
- [x] 4.7 Implement the commit test: travelled ≥ ⅓ of the viewport toward the
      incoming category, **or** a flick above the velocity threshold in that
      direction, with velocity read from the trailing samples rather than the
      whole gesture. Not latched — a drag brought back below the threshold is an
      abandon.
- [x] 4.8 Implement the settle on `touchend`: cancel any pending frame, compute the
      duration from the distance remaining (floored, capped at the standard
      transition duration), write it, add the settling class, and move both
      panels to their targets.
- [x] 4.9 Implement the commit path: hand the incoming HTML to `switchCategory()`
      as a pre-fetched body so the tap and the drag share one routine, then tear
      down.
- [x] 4.10 Implement the abandon path: return both panels to rest, call
      `syncActiveTab()` with the original category, and restore the exact scroll
      offset captured at setup.
- [x] 4.11 Write one teardown routine, safe to call repeatedly, that clears both
      panels' pinning, inline box, transforms and settle duration, removes the
      incoming sibling, releases the horizontal containment, cancels any pending
      frame and timer, and re-arms infinite scroll for whichever category is now
      active. Call it from the end of both paths.
- [x] 4.12 Bind `touchcancel` to the same settle, and resolve any live gesture on
      `resize` and `orientationchange`. Without these a system interruption leaves
      the page unable to scroll with nothing on screen to explain it.

## 5. Templates

- [x] 5.1 Add the placeholder markup as a partial, so the gesture injects a
      template rather than building HTML in a string.
- [x] 5.2 Confirm `#results` keeps its id and position and that no existing caller
      of `setResults()` changed behaviour. The sibling panel exists only for the
      length of a gesture and is never a swap target.

## 6. Tests

Source-shape tests, in the style of `tests/Unit/Asset/TrayDismissalTest.php`.
Each assertion must be demonstrated failing against its own bug before it is
committed — a test that passes against the broken source is not pinning
anything.

- [x] 6.1 Create `tests/Unit/Asset/TabSwipeTest.php`.
- [x] 6.2 Assert the `touchmove` listener is registered with `passive: false` at
      its only registration site, and that no code path upgrades a passive
      listener later.
- [x] 6.3 Assert the axis is decided at one place and that a vertical assignment
      returns without re-arbitrating.
- [x] 6.4 Assert the refusal happens in the `touchstart` handler and names all four
      cases — multi-touch, `anyOverlayOpen()`, an overlay target, and `.tabs`.
- [x] 6.5 Assert `anyOverlayOpen()` has exactly one definition and that both the
      scroll lock and the gesture call it.
- [x] 6.6 Assert the pinned rule in `app.css` does **not** declare `left` or
      `right`, and that the gesture writes `top`, `left` and `width` inline.
- [x] 6.7 Assert no `scale(` appears in any rule or inline write that moves a
      panel, and that the transform's vertical component is zero.
- [x] 6.8 Assert the lock distance and the commit fraction are separate named
      constants with different values.
- [x] 6.9 Assert the tracking callback contains no layout-reading call
      (`getBoundingClientRect`, `offset*`, `client*`, `scroll*`), and that setup's
      single measurement precedes its first style write.
- [x] 6.10 Assert the commit path calls `switchCategory()` rather than
      reimplementing the tab change, so the two paths cannot drift.
- [x] 6.11 Assert the reduced-motion block exempts the tracked transform and names
      both the tray drag and the category drag, so the exception is stated rather
      than inferred.
- [x] 6.12 Assert the cache's currency check reads both the search term and the
      mutation counter.
- [x] 6.13 Run `composer test`, `composer stan` and `composer cs`. All three must
      pass; do not commit around a failure.

## 7. Documentation

- [x] 7.1 Add the gesture wherever the phone experience is described in
      `README.md` and `docs/`. It is undiscoverable by inspection, so it has to be
      written down somewhere.
- [x] 7.2 Add a `CLAUDE.md` note for the traps that no test can catch: a new
      overlay or panel added without an entry in the gesture's refusal list, and a
      second code path for changing category. Both are the same hazard shape as
      the bullets already there.
- [x] 7.3 State explicitly whether anything else in `docs/` went stale. If nothing
      did, say so rather than inventing edits.

## 8. Verification on a real device

`composer test` has no browser and cannot see a transform. None of the following
is optional, and none of it can be replaced by a test.

- [ ] 8.1 On a real iPhone: confirm the panels follow the thumb from the first few
      pixels and the page does not scroll during a horizontal drag. This is the
      platform where a late `preventDefault()` fails silently.
- [ ] 8.2 On a real Android phone: the same.
- [ ] 8.3 Both directions from every category, including a resisted drag off All
      and off Collections.
- [ ] 8.4 Commit, abandon, and a drag taken past the threshold and brought back.
- [ ] 8.5 A drag with a search active, confirming the destination opens filtered.
- [ ] 8.6 A drag immediately after a delete and after an import, confirming the
      destination is not a stale cached copy.
- [ ] 8.7 A drag started with a tray open, and a tray dismissal drag while the
      gesture is bound, confirming neither interferes with the other.
- [ ] 8.8 A drag interrupted by a lock, an app switch, and a rotation — the page
      must scroll normally afterwards in every case.
- [ ] 8.9 Back and forward after several committed drags.
- [ ] 8.10 Infinite scroll into a category, drag away, drag back: it shows page one
      from the top, and scrolling still appends.
- [ ] 8.11 With reduced motion enabled: the panels still follow the finger and the
      settle is instant.
- [ ] 8.12 On desktop with a mouse: nothing changed, and tab clicks are still an
      instant cut.
