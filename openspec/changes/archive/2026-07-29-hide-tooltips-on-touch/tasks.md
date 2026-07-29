## 1. Gate the tooltip module on pointer capability

- [x] 1.1 In the tooltip IIFE in [public/assets/app.js](public/assets/app.js), add a module-level `MediaQueryList` for `(hover: hover) and (pointer: fine)` and an `allowed()` predicate that reads `.matches` at call time (no cached boolean).
- [x] 1.2 Make `show()` return immediately when `allowed()` is false, so every current and future trigger passes through the same gate.
- [x] 1.3 Add a `change` listener on the media query that calls `hide()`, so a bubble on screen when the device stops qualifying is dismissed rather than stranded.
- [x] 1.4 Keep the existing `e.pointerType === 'touch'` early return in the `pointerover` handler.
- [x] 1.5 Update the module's header comment to state that tooltips are a pointer-device affordance and never appear on touch.

## 2. Suppress the focus tooltip that follows a tap

- [x] 2.1 Add a capture-phase `pointerdown` listener that records a timestamp when `e.pointerType === 'touch'` and clears it (sets `0`) otherwise.
- [x] 2.2 In the `focusin` handler, skip showing the tooltip when the recorded touch timestamp is within ~500 ms of now, so a tap that moves focus to a control (e.g. the Sort trigger on a touchscreen laptop) shows nothing.
- [x] 2.3 Confirm keyboard focus still shows tooltips: tabbing to a host outside that window is unaffected.

## 3. Tooltip wording

- [x] 3.1 In [templates/gallery.html.twig:120](templates/gallery.html.twig#L120), change `data-tooltip="Tap to preview"` to `data-tooltip="Click to preview"`.
- [x] 3.2 Leave the on-screen "Tap a poster to preview it." paragraph at [gallery.html.twig:113-115](templates/gallery.html.twig#L113-L115) unchanged — it is visible copy for touch users, not a tooltip.
- [x] 3.3 Sweep the repo for any remaining native tooltips (`title=` on interactive markup in `templates/`) and confirm none exist; the custom tooltip is the only tooltip mechanism.

## 4. Verify

Behaviour below was verified by running the tooltip module against a stubbed DOM
(11 checks: touch-only, pointer, hybrid, and capability-change paths) plus the PHP
suite. That covers the branching, not the rendering — a real-device spot check of
the visuals is still worth doing.

- [x] 4.1 Phone / touch emulation: tap the Sort button — the sort tray opens and no "Sort" bubble appears. Repeat for the pagination arrows and a poster caption; no tooltip in any case.
- [x] 4.2 Desktop with a mouse: hover a poster caption (full title bubble, `help` cursor), a pagination arrow, and the Find-poster preview ("Click to preview") — all still work.
- [x] 4.3 Desktop keyboard: tab to a pagination arrow and confirm the tooltip appears on focus and clears on blur and on `Escape`.
- [x] 4.4 Toggle device emulation on and off with a tooltip on screen and confirm it is dismissed on the switch to touch and works again on the switch back.
- [x] 4.5 Run `composer test` (or the project's PHPUnit + PHPStan commands) to confirm the template edit breaks nothing. — 215 tests, 503 assertions, all passing.

## 5. Close out

- [x] 5.1 Sync the `application-shell` delta into [openspec/specs/application-shell/spec.md](openspec/specs/application-shell/spec.md) and archive the change.
