## Context

The shared tooltip lives in one IIFE at the bottom of
[public/assets/app.js:31-107](public/assets/app.js#L31-L107). It creates a single
floating `.tooltip` bubble and delegates from `document`, so it covers every
`[data-tooltip]` host — poster captions, pagination steps, the phone Sort
trigger, the Find-poster preview images — including markup Alpine or AJAX adds
later. There is no per-control tooltip code to patch, which is why a single gate
in this module can carry the whole guarantee.

The module already tries to avoid touch, at
[app.js:91](public/assets/app.js#L91): the `pointerover` handler returns early
when `e.pointerType === 'touch'`. That guard is real but incomplete, and it is
why only one instance is visible in practice. Two paths get around it:

1. **`focusin`** ([app.js:100-103](public/assets/app.js#L100-L103)) shows the
   tooltip with no pointer-type check at all. A tap on a `<button>` moves focus
   to it, and `FocusEvent` carries no `pointerType` to inspect. This is the
   reported Sort-button case: `.sort-trigger` is a `<button>` that opens the sort
   tray, so tapping it both focuses it and leaves "Sort" floating.
2. **Non-touch pointer events from touch devices** — a stylus reports
   `pointerType === 'pen'`, and some mobile browsers synthesise a `mouse`
   `pointerover` after a tap. Neither is caught by an equality check against
   `'touch'`.

So the defect is not one control; it is that the module decides per event rather
than per device. Fixing the Sort button alone (dropping its `data-tooltip`) would
leave the pagination steps and Find-poster preview to resurface the same bug the
next time a phone user taps one.

## Goals / Non-Goals

**Goals:**

- No tooltip is ever shown on a touch-only device, from any trigger.
- One gate, enforced centrally, so a future `data-tooltip` host inherits the
  behaviour without its author thinking about it.
- Desktop hover and keyboard-focus tooltips keep working exactly as they do now.
- Hybrid devices (touchscreen laptop, tablet with a mouse) behave by their
  current input, not by a snapshot taken at page load.

**Non-Goals:**

- Replacing tooltips with a touch equivalent (long-press bubble, toast). Nothing
  a tooltip says on this app is information a touch user lacks: the poster
  caption tooltip repeats a title that is truncated but tappable, the pagination
  arrows carry `aria-label`s, and the Find-poster grid already prints "Tap a
  poster to preview it." on screen.
- Changing which elements carry `data-tooltip`, or the styling of `.tooltip`.
- Introducing a JS test runner. This repo has no Node build step or JS test
  infrastructure; verification is by device/emulation check plus the existing
  PHP suite for the template edit.

## Decisions

### Gate on `(hover: hover) and (pointer: fine)`, evaluated per trigger

Add a single predicate to the tooltip module:

```js
var pointerDevice = window.matchMedia('(hover: hover) and (pointer: fine)');

function allowed() {
    return pointerDevice.matches;
}
```

`show()` returns immediately unless `allowed()` — one choke point every trigger
(`pointerover`, `focusin`, and anything added later) already funnels through.
Both halves of the query earn their place: `hover: hover` excludes devices whose
primary input cannot hover, and `pointer: fine` excludes a coarse pointer that
technically reports hover (some TV/console browsers), which would otherwise show
bubbles nobody can dismiss.

The predicate is a live `MediaQueryList` read at call time, not a boolean
captured at load, so a laptop that switches between trackpad and touchscreen is
judged by its current state. Paired with a `change` listener that calls `hide()`,
a bubble on screen when the device stops qualifying is dismissed rather than
stranded — the exact failure mode this change exists to remove.

*Alternatives considered:*

- **`any-hover` / `any-pointer`** — these match if *any* available input
  qualifies, so a phone with a stylus paired would still show tooltips. The
  question here is what the user is actually driving the UI with, which is what
  the primary-input queries answer.
- **Drop `data-tooltip` from the Sort button** — treats the symptom. The other
  four hosts stay exposed and the next author reintroduces the bug.
- **CSS-only suppression (`@media (hover: none) { .tooltip { display: none } }`)**
  — the bubble would still be built, positioned, and marked visible, and would
  reappear on any device the query misjudges. A gate that never shows is
  cheaper and honest about intent.
- **Feature-detect via `'ontouchstart' in window`** — true on touchscreen
  laptops that should keep tooltips, and it is a static check that cannot react
  to a change. Media queries are the supported way to ask this question.

### Suppress the focus tooltip that follows a touch tap

On a hybrid device `allowed()` is `true`, so a tap on the Sort button would still
raise a focus tooltip — the same bug, on a narrower set of hardware. Record touch
input and let the immediately-following focus event skip the tooltip:

```js
document.addEventListener('pointerdown', function (e) {
    touchedAt = e.pointerType === 'touch' ? Date.now() : 0;
}, true);
```

`focusin` then shows nothing if `Date.now() - touchedAt` is under a short window
(~500 ms). The tap-then-focus sequence is same-task and lands within a couple of
frames, so the window is generous; a keyboard user who tabs to a control later is
well outside it and keeps their tooltip. A timestamp beats a boolean flag because
there is no reliable "clear it" event — a tap that moves focus nowhere would
leave a plain flag stuck on, silently killing keyboard tooltips for the rest of
the page's life.

Capture phase (`true`) is used so the flag is set before any handler that might
stop propagation. This is belt-and-braces on top of the media query: on a
touch-only phone `allowed()` is already `false` and the tap never reaches here.

### Keep the existing `pointerType === 'touch'` early return

It stays in `pointerover`. It is now redundant on touch-only devices but remains
the cheapest way to ignore touch-driven pointer traffic on a hybrid, and removing
it would widen the diff for no gain.

### Reword the Find-poster preview tooltip

[templates/gallery.html.twig:120](templates/gallery.html.twig#L120) reads
`data-tooltip="Tap to preview"`. After this change that string is only ever shown
to someone with a mouse, so it instructs the one audience that cannot follow it.
Change to "Click to preview". The on-screen line at
[gallery.html.twig:113-115](templates/gallery.html.twig#L113-L115) — "Tap a
poster to preview it." — is a visible paragraph, not a tooltip, and stays as is
for the touch users it addresses.

### No cache-busting work

`asset()` versions asset URLs by file mtime
([src/bootstrap.php:128](src/bootstrap.php#L128)), and the service worker relies
on that ([public/sw.js:1-5](public/sw.js#L1-L5)). Editing `app.js` mints a new
URL on its own; `CACHE` in `sw.js` does not need bumping.

## Risks / Trade-offs

- **A hover-capable device that misreports its media queries loses tooltips
  entirely** → Tooltips are supplementary here by design: every icon-only host
  carries an `aria-label`, captions duplicate text already on screen, and no
  tooltip is the sole carrier of any instruction. Worst case is a lost
  convenience, not a lost capability. `(hover: hover) and (pointer: fine)` is
  also the same pair the stylesheet already trusts for the card hover overlay
  ([app.css:559](public/assets/app.css#L559)), so this change does not introduce
  a new class of assumption.
- **The 500 ms touch window could swallow a legitimate keyboard tooltip** →
  Only if a user taps the screen and within half a second tabs to a tooltip host.
  The cost is one missing tooltip until they focus it again; the alternative
  (no window) leaves the reported bug live on touchscreen laptops.
- **No automated regression test for JS behaviour** → The gate lives in one
  function reached by every trigger, which keeps it reviewable by reading. The
  manual verification steps in tasks.md cover phone, desktop hover, keyboard, and
  emulated device toggling.
- **A future tooltip host could bypass the module** (e.g. reintroducing a native
  `title=`) → The spec already forbids native tooltips, and tasks.md includes a
  repo sweep confirming no `title=` attribute remains on interactive markup.

## Migration Plan

No data, config, or deployment change. Ship with the normal build; users pick up
the new `app.js` URL on their next page load. Rollback is a revert of the two
edited files.
