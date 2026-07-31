## 1. Implementation

- [x] 1.1 In `public/assets/app.css`, add a `@media (min-width: 641px)` block
      immediately after the base `.topbar` rule that aligns the header's
      contents with the content column —
      `padding-inline: max(24px, calc((100% - 960px) / 2 + 24px))` — and tints
      the bar with `background: var(--bg)` so it reads as page-coloured chrome
      under the existing bottom border.
- [x] 1.2 Comment the new block: what each term of the `max()` does, why the
      gutter is padding rather than an inner wrapper, and why it is a
      `min-width` query (desktop-only, the complement of the
      `@media (max-width: 640px)` block at the end of the file).
- [x] 1.3 Confirm the base `.topbar` rule and the trailing mobile block are
      otherwise unmodified, and that no template, PHP, or JavaScript file
      changed.

## 2. Verification

- [x] 2.1 Load the gallery, Plex import, orphans, and login pages at a desktop
      width and confirm the brand and navigation sit at the content column's
      left and right edges on each.
- [x] 2.2 Check a viewport between 641px and 959px and confirm the header's
      contents fall back to the same 24px edge spacing as the content column.
- [x] 2.3 Check a phone-width viewport (≤640px) and confirm the header is
      pixel-identical to before: full-bleed, flush to the top, on its existing
      surface with its bottom border; the menu button opens the tray as usual.
- [x] 2.4 Confirm the poster wall is unaffected (it renders standalone, without
      the shared layout).

## 3. Gates

- [x] 3.1 Run `composer test`, `composer stan`, and `composer cs`; all three
      must pass.
- [x] 3.2 Check `README.md`, `docs/`, and `CLAUDE.md` for staleness caused by
      this change and update in the same commit, or state explicitly that
      nothing user-facing documented there changed.
