## 1. Implementation

- [x] 1.1 In `public/assets/app.css`, add a `@media (min-width: 641px)` block
      immediately after the base `.topbar` rule that boxes the header on
      desktop: `max-width: 960px`, `margin: 24px auto 0`, a full `border`
      (replacing the base rule's bottom-only border for this width),
      `border-radius`, keeping the existing `padding: 14px 24px` so the brand
      and navigation align with `.container`'s content edges.
- [x] 1.2 Comment the new block to explain why it is a `min-width` query
      (desktop-only, so the base `.topbar` rule and the phone presentation are
      left untouched) and that it is the complement of the
      `@media (max-width: 640px)` block at the end of the file.
- [x] 1.3 Confirm the base `.topbar` rule and the trailing mobile block are
      otherwise unmodified, and that no template, PHP, or JavaScript file
      changed.

## 2. Verification

- [x] 2.1 Load the gallery, Plex import, orphans, and login pages at a
      desktop width and confirm the header is centred, boxed, and edge-aligned
      with the content below on each.
- [x] 2.2 Check a viewport between 641px and 959px and confirm the header still
      spans the available width and agrees with the content column.
- [x] 2.3 Check a phone-width viewport (≤640px) and confirm the header is
      pixel-identical to before: full-bleed, flush to the top, bottom border
      only, square corners; the menu button opens the tray as usual.
- [x] 2.4 Confirm the poster wall is unaffected (it renders standalone, without
      the shared layout).

## 3. Gates

- [x] 3.1 Run `composer test`, `composer stan`, and `composer cs`; all three
      must pass.
- [x] 3.2 Check `README.md`, `docs/`, and `CLAUDE.md` for staleness caused by
      this change and update in the same commit, or state explicitly that
      nothing user-facing documented there changed.
