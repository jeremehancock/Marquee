## Why

On a phone, three surfaces inside the Import and Orphans trays are drawn wrong:
a shadow floats where no panel is visible, the orphan count bar sits on a
darker band that stops short of both tray edges, and on a reopen that same bar
punches through the "Checking Plex for orphans…" spinner unblurred. Each one
reads as a rendering fault rather than as design, which undermines confidence in
the tray at exactly the moment the user is waiting on it.

## What Changes

- The tray's panel reset drops the panel's elevation along with its surface.
  Today it strips the background, border and padding but leaves the `--elev-2`
  shadow behind, so the Import form and the "No orphaned posters found" empty
  state each cast a halo tracing a rectangle that is no longer drawn.
- The orphan count / "Delete all orphans" bar stops borrowing the gallery's
  pinned search-bar styling. It gets its own class and is plain layout — never
  pinned, no background of its own, no gutter bleed, no stacking order — on both
  the tray and the standalone Orphans page.
- Removing that positioning is also what fixes the spinner: an unpositioned bar
  cannot outrank the tray's progress overlay, so the overlay dims and blurs it
  like everything else in the tray.
- Two shape tripwires are added alongside the existing `tests/Unit/Asset` suite,
  one per root cause.

No behaviour changes. No JavaScript changes. The scan, the delete, and the
reopen-rescans-again rule all stay exactly as they are — a reopen still shows
the previous scan under the spinner, which now reads correctly as refreshing.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `visual-design`: adds a requirement that a surface adopted into a tray carries
  no leftover appearance from where it was authored — neither elevation without
  the surface that justified it, nor page-chrome pinning that outranks the
  tray's own overlays.

## Impact

- `public/assets/app.css` — complete the `.sheet__body .panel` reset; add the
  orphan bar's own rule; the phone `.toolbar` rule and its
  `StickyToolbarTest` guards are deliberately untouched.
- `templates/orphans/_results.html.twig` — one class rename on the count bar.
- `tests/Unit/Asset/` — one new shape test covering both tripwires.
- Not touched: `public/assets/gallery.js`, `templates/plex.html.twig`, and every
  server-side path. Nothing user-facing changes, so `README.md` and `docs/` stay
  as they are.
