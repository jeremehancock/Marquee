## Why

On a phone, tapping the Sort button leaves a "Sort" tooltip stranded on screen —
a hover hint appearing where there is no hover. Tooltips exist to reveal
information a pointer user can seek out without committing to a tap; on a touch
device they only cover content and add noise. The tooltip layer should simply not
exist on touch, and the guarantee should be device-wide rather than patched one
control at a time.

## What Changes

- Tooltips are shown only on devices that have a true hovering, fine pointer
  (mouse/trackpad). On touch-only devices no tooltip ever appears, from any
  trigger — hover, tap, or the focus that follows a tap.
- The gate is evaluated at trigger time (not once at load), so a hybrid device —
  a laptop with a touchscreen, or a tablet with a mouse attached — behaves
  correctly as its input situation changes, and a tooltip already on screen is
  dismissed if the device stops qualifying.
- Existing hover and keyboard-focus tooltip behaviour on pointer devices is
  unchanged; every current tooltip host (poster captions, pagination steps, the
  Sort trigger, the Find-poster preview) keeps working exactly as it does today
  on desktop.
- The Find-poster preview hint is reworded from "Tap to preview" to "Click to
  preview", since after this change that tooltip is only ever read by a pointer
  user. The touch instruction remains available where it belongs — the on-screen
  "Tap a poster to preview it." line, which is unaffected.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the "Consistent custom tooltips" requirement gains a
  device-capability condition — tooltips are a pointer-device affordance and
  SHALL be suppressed entirely on touch-only devices, including on focus.

## Impact

- [public/assets/app.js](public/assets/app.js) — the shared tooltip module: add a
  capability gate consulted by the show path, and dismiss on capability loss.
- [templates/gallery.html.twig](templates/gallery.html.twig) — reword the
  Find-poster preview tooltip.
- [openspec/specs/application-shell/spec.md](openspec/specs/application-shell/spec.md)
  — updated requirement text and scenarios.
- No PHP, routing, styling, or data changes. No effect on accessible names: every
  tooltip host that needs one already carries an `aria-label`, which screen
  readers use on every device regardless of this gate.
