## Why

A keyboard user cannot use this application's dialogs. Opening one leaves focus
on the page behind it, under the backdrop, with nothing announced — so reaching
the dialog's first control means tabbing through every poster, every nav item
and the footer first, blind, and tabbing again leaves the dialog by the far
side. Closing one drops the user wherever that walk ended rather than back at
the control they pressed.

Eleven overlays behave this way, which is all of them. It is the largest
accessibility gap left in the app, and it is the gap underneath
`draw-the-disabled-state` (archived 2026-08-18): that change made five
switched-off controls reachable by keyboard, but three of them live inside
overlays focus never enters, so their fix is correct in principle and
unobservable in practice.

## What Changes

- **Focus moves into an overlay when it opens.** The panel itself receives
  focus, so its accessible name is announced and the user tabs forward through
  its contents in order.
- **Focus stays inside an overlay while it is open.** Tab and Shift+Tab cycle
  within the topmost overlay instead of walking out into the page behind it.
- **Focus returns to where it came from when an overlay closes** — by Escape,
  backdrop, close button, swipe-to-dismiss, or a completed action alike — with a
  defined fallback for when that element no longer exists.
- **Overlays nest.** The change dialog raises a preview, which raises a
  confirmation; each takes focus in turn and hands it back as it closes, matching
  the layering the Escape guards already encode by hand.
- **The full-screen preview is declared a dialog.** It behaves as one — backdrop,
  Escape, its own controls — but carries no `role`, no `aria-modal` and no
  accessible name today. Two of the three stranded switched-off controls live on
  it.
- The `visual-design` entry in `openspec/config.yaml` is amended: its "how things
  look, not what they do" gloss stopped being true when reachability landed there
  on 2026-08-18, and this change adds more of the same.
- **Not in scope:** `inert` on the page behind an open overlay, and the
  full-screen poster viewer's own lack of a close affordance. Both are argued in
  `design.md` and left for a later change.

## Capabilities

### New Capabilities

None. Focus belongs with the capability that already owns keyboard reachability.

### Modified Capabilities

- `visual-design`: adds a requirement that an overlay takes focus on open, keeps
  it while open, and returns it on close, with nesting defined. Extends the
  existing "An unavailable control stays reachable and reports its state" from
  reachability on a page to reachability inside a dialog, which is where three of
  the controls that requirement was written for actually live.

## Impact

- `public/assets/gallery.js` — one new document-level focus manager, alongside
  the existing scroll lock and drag-to-dismiss blocks that already work this way.
- `templates/gallery.html.twig`, `templates/orphans.html.twig`,
  `templates/partials/_menu.html.twig`, `templates/partials/_overlays.html.twig`
  — each overlay panel gains the attributes the manager needs; the preview gains
  its dialog role and name.
- `openspec/config.yaml` — one line of the capability map.
- `tests/Unit/Asset/` — a new source-shape tripwire, in the manner of
  `DisabledStateTest`.
- No PHP, no routes, no settings, no dependencies. No new vendored script.
- Behaviour cannot be proven by the test suite: there is no JS runner in this
  repo. The tests pin the arrangement; the behaviour needs a keyboard pass
  against the `:dev` image before release.
