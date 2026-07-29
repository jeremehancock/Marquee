## 1. Busy tracker in gallery.js

- [x] 1.1 Add `LOADING_GRACE_MS = 200` and `LOADING_MIN_MS = 300` as named
      constants in `public/assets/gallery.js`, with a short comment stating why
      the indication is deferred rather than tied to the fetch.
- [x] 1.2 Add `beginBusy()` / `endBusy()` inside the gallery IIFE, holding the
      in-flight counter, the grace timer, the shown-at timestamp, and the hide
      timer, per the design. `beginBusy()` arms the grace timer only on the
      0 → 1 transition; `endBusy()` acts only on the transition back to 0.
- [x] 1.3 In `endBusy()`, cancel a still-pending grace timer when nothing was
      ever shown, and otherwise schedule removal for the remainder of
      `LOADING_MIN_MS`, re-checking that the counter is still 0 before removing.
- [x] 1.4 Cancel any pending hide timer in `beginBusy()` so a new navigation
      arriving during the minimum-hold window does not get cleared by the old
      timer.

## 2. Route every call site through the tracker

- [x] 2.1 Replace the `classList.add('is-loading')` / `classList.remove(...)`
      pair in `load()` with `beginBusy()` / `endBusy()`, leaving the removal in
      the existing `finally`.
- [x] 2.2 Do the same in `submitForm()`, confirming its nested `load()` call
      composes correctly through the counter rather than clearing early.
- [x] 2.3 Grep `public/assets/` to confirm `is-loading` is added in exactly one
      place and that every mutation of it lives inside the tracker — `endBusy()`
      legitimately removes it from two paths (immediate and deferred) — and that
      no other module toggles it.
- [x] 2.4 Confirm the infinite-scroll `is-busy` sentinel is untouched — it is
      explicitly out of scope.

## 3. CSS

- [x] 3.1 Leave the `[data-gallery].is-loading #results` values as they are and
      add a comment noting that the class is applied on a delay by the busy
      tracker in `gallery.js`, so the rule is not mistaken for the whole story.

## 4. Tests

- [x] 4.1 Add a PHPUnit test asserting `public/assets/gallery.js` defines both
      timing constants, applies `is-loading` in exactly one place, keeps every
      mutation of it inside the tracker, and routes both `load()` and
      `submitForm()` through `beginBusy()`/`endBusy()`.
- [x] 4.2 Comment the test to say it is a shape tripwire against reintroducing
      synchronous dimming, not a behavior test — behavior is verified manually.
- [x] 4.3 Run `composer test`, `composer stan`, and `composer cs`; all three
      must pass.

## 5. Docs

- [x] 5.1 Check `README.md`, `docs/`, and `CLAUDE.md` for staleness. Behavior is
      unchanged in kind (a slow view change still dims), so state explicitly
      that nothing needed editing if that is the finding, rather than inventing
      an edit.

## 6. Validation

- [x] 6.1 Run `openspec validate defer-gallery-loading-dim --strict`.
- [x] 6.2 Build the image locally and smoke-test `/health` per
      [docs/docker.md](../../../docs/docker.md) — no Dockerfile change here, so
      this is only a sanity check that assets are served.
- [ ] 6.3 Manual check on desktop: switch category tabs repeatedly on a normal
      connection and confirm the grid never dims.
- [ ] 6.4 Manual check on a phone-width viewport: switch bottom-bar tabs and
      confirm the same, and that infinite scroll still shows its sentinel
      spinner.
- [ ] 6.5 Manual check with the network throttled: confirm a slow view change
      still dims, that the dim does not flash, and that the gallery always ends
      undimmed after rapid back-to-back tab switches.
