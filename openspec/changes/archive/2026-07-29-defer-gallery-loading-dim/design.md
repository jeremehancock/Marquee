## Context

The gallery navigates between views without a full page reload. Every one of
those navigations funnels through two helpers in
[public/assets/gallery.js](../../../public/assets/gallery.js): `load(url, push)`
for tab switches, live search, clearing a search, pagination, `popstate`, and
the `gallery:refresh` event; and `submitForm(form)` for poster actions, which
then calls `load()` itself.

Both bracket their fetch with a class toggle on the gallery root:

```js
root.classList.add('is-loading');
fetch(...).finally(function () { root.classList.remove('is-loading'); });
```

and [public/assets/app.css:411-414](../../../public/assets/app.css#L411-L414)
turns that class into a dim:

```css
[data-gallery].is-loading #results {
    opacity: 0.55;
    transition: opacity 0.1s ease;
}
```

The indication is therefore tied to the exact lifetime of the fetch. Against a
local Plex-adjacent server most view changes resolve in tens of milliseconds, so
what the user sees is not "loading" but a dip to 55% opacity and straight back —
a flash. This is the classic flash-of-loading-indicator problem, and it is worse
here than a spinner flash would be because the dim affects the entire grid.

Constraint: there is no Node build step and no JS test harness in this repo, so
whatever is written has to be plain ES5-compatible script in the existing file,
and its verification is mostly manual.

## Goals / Non-Goals

**Goals:**

- A view change that resolves quickly renders no loading treatment whatsoever.
- A view change that is genuinely slow still dims, and once dimmed stays dimmed
  long enough to be read rather than flashing.
- One implementation covering every in-place navigation, rather than per-call-site
  special-casing.
- Correct behavior when navigations overlap or nest (`submitForm` → `load`).

**Non-Goals:**

- Redesigning what the loading state looks like. The dim stays a dim; no
  progress bar, no skeleton, no spinner overlay.
- Touching the infinite-scroll sentinel's `is-busy` spinner. That indicator sits
  below content the user is already reading, appears only after a deliberate
  scroll, and does not flash the grid — it is a different problem.
- Making the gallery navigation faster. This change is about when feedback is
  shown, not how long the fetch takes.

## Decisions

### Decision: a single reference-counted busy tracker owns the class

Introduce one small helper pair inside the existing gallery IIFE — `beginBusy()`
and `endBusy()` — and make them the *only* place `is-loading` is added or
removed. `load()` and `submitForm()` call `beginBusy()` where they currently
call `classList.add`, and `endBusy()` in their existing `finally`.

The tracker holds:

- a **counter** of in-flight view changes,
- a **grace timer** (200 ms) that has not yet applied the class,
- the **timestamp** at which the class was applied,
- a **hide timer** enforcing the 300 ms minimum.

`beginBusy()` increments the counter and, only on the transition from 0 → 1,
arms the grace timer. `endBusy()` decrements and, only on the transition back to
0, either cancels a still-pending grace timer (fast path — nothing was ever
shown) or schedules removal for the remainder of the 300 ms minimum.

*Why a counter rather than a boolean:* `submitForm()` marks itself busy and then
calls `load()`, which marks itself busy too. With a boolean, the inner `load()`
finishing would clear the indication while the outer submit is still settling.
A counter also makes overlapping navigations — a fast tab switch on top of a
still-pending search — behave correctly for free: the dim reflects "anything in
flight", not "the last thing started".

*Alternative considered — per-call-site timers.* Each of `load()` and
`submitForm()` manages its own `setTimeout`. Rejected: two independent timers
racing on the same class is exactly how a stuck dim gets shipped, and the
nesting above guarantees they overlap.

### Decision: 200 ms grace, 300 ms minimum

200 ms is the conventional threshold below which a transition reads as
instantaneous rather than as a wait — under it, showing feedback creates the
very perception of slowness it was meant to soften. 300 ms is long enough for a
dim to register as intentional. Both live as named constants at the top of the
gallery module so a single edit retunes them.

*Alternative considered — grace only, no minimum hold.* Simpler, and it fixes
the reported symptom. Rejected because it just relocates the flash to loads that
land at 210 ms: the dim appears and vanishes 10 ms later. The minimum is what
makes the indication monotonic — once you see it, it means something.

### Decision: the results swap is never delayed

`setResults()` and the title update happen the moment the response is parsed,
untouched by the tracker. Only class removal waits out the minimum. Holding the
content back to match the dim would trade a cosmetic flicker for real latency,
which is the wrong direction.

The consequence is a brief window where new posters are visible at 55% opacity
before brightening. With a 0.1 s ease that reads as a fade-in of the new view,
which is acceptable — arguably better than a hard cut.

### Decision: keep the CSS rule, do the timing in JS

`transition-delay` or an `animation-delay` on the `is-loading` rule would buy the
grace period with no timers at all. Rejected: CSS can delay the *appearance* of
the dim, but removing the class reverts it instantly, so the minimum-hold half of
the requirement is unreachable. Splitting the two halves across CSS and JS would
leave the behavior described in neither place. The CSS keeps its existing values
and gains a comment pointing at the tracker.

### Decision: the regression guard is a source-shape assertion, not a behavior test

There is no JS test runner here and adding one is well out of proportion to a
timing tweak. The specific regression worth catching is someone reintroducing a
synchronous `classList.add('is-loading')` at a call site. A PHPUnit test that
reads `public/assets/gallery.js` and asserts the class is toggled in exactly one
place, alongside the two named constants, catches that cheaply.

This is a tripwire, not proof of behavior. Actual verification is manual, on the
`:dev` image, on both a throttled and an unthrottled connection.

## Risks / Trade-offs

- **A stuck counter leaves the gallery permanently dimmed.** → `beginBusy()` and
  `endBusy()` are called in strictly matched pairs, with `endBusy()` only ever in
  a `finally`. Both existing helpers already have one. The hide timer also
  re-checks the counter before removing, so it cannot clear a dim that a newer
  navigation still needs.
- **Slow view changes now feel unacknowledged for the first 200 ms.** → That is
  the intent; 200 ms is below the threshold at which a user starts looking for
  feedback. If real-world use shows it is too long on a slow library, the
  constant is one line.
- **The tripwire test is coupled to the source text of `gallery.js`.** → Keep it
  to the narrowest assertion that still catches the regression, and put a comment
  in the test saying why it is shaped that way, so a future reader does not
  mistake it for a behavior test.
- **A cached `gallery.js` would ship the old behavior.** → No action needed: the
  `asset()` Twig helper appends the file's mtime, so the changed script is a new
  URL. The service-worker `CACHE` name does not need bumping.

## Migration Plan

None. Client-side presentation only — no schema, no config, no route, no API.
Rollback is a revert of the single commit; the CSS rule and class name are
unchanged, so a revert cannot leave a half-state.

## Open Questions

None blocking. The two constants are the only tunable, and they are chosen to be
adjusted after seeing the `:dev` image rather than debated up front.
