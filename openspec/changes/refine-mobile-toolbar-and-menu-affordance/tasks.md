## 1. Menu trigger glyph

- [x] 1.1 In `templates/layout.html.twig`, replace the hamburger `<path>` in the
  `.menu-btn` SVG with a horizontal-ellipsis (overflow) glyph — three filled
  circles on the horizontal centre line, sized to match the existing 24×24
  viewBox and the surrounding icon weight.
- [x] 1.2 Confirm the button keeps `aria-label="Menu"`, `aria-haspopup="true"`,
  its `.menu-btn` class, and `@click="menuOpen = true"`. Nothing about the tray,
  the link macros, or the mobile/desktop visibility rules changes.

## 2. Sticky mobile toolbar

- [x] 2.1 In the `@media (max-width: 640px)` block of `public/assets/app.css`,
  pin `.toolbar` with `position: sticky; top: 0` and `z-index: 30`.
- [x] 2.2 Give the pinned toolbar an opaque `background: var(--bg)` so posters
  are hidden as they scroll behind it.
- [x] 2.3 Make it full-bleed past `.container`'s 14px mobile side padding
  (`margin: 0 -14px 8px; padding: 8px 14px`) so no poster shows through at the
  left or right edge, while its contents stay on the content grid.
- [x] 2.4 Update the overlay layering comment above `.modal` in
  `public/assets/app.css` so the documented scale includes the pinned toolbar's
  tier (30) below the bottom tab bar (40).

## 3. Tests

- [x] 3.1 In `tests/Functional/ApplicationShellTest.php`, assert the rendered
  topbar menu trigger presents an overflow glyph and no longer draws the
  hamburger's three horizontal rules, while keeping its accessible name.
- [x] 3.2 Add `tests/Unit/Asset/StickyToolbarTest.php` as a stylesheet shape
  tripwire, following the approach in `tests/Unit/Asset/TrayDismissalTest.php`:
  assert the mobile `.toolbar` declares `position: sticky`, an opaque
  background, and negative side margins matched by equal padding.
- [x] 3.3 In the same test, assert the toolbar's `z-index` is below `.tabs` and
  below `.sheet`, so the bottom tab bar and every overlay still cover it.
- [x] 3.4 Assert the desktop `.toolbar` rule declares no `position: sticky`, so
  the pinning stays phone-only.

## 4. Return to the top of the results on search

Found while validating the `:dev` image. The pinned toolbar made searching from
part-way down the gallery possible for the first time, which exposed that live
search — unlike pagination and category switching — never resets the scroll.

- [x] 4.1 In `public/assets/gallery.js`, have the live-search handler call
  `scrollToTopOfGallery()` before it loads, matching how the pagination handler
  already does it.
- [x] 4.2 Confirm the existing reduced-motion branch in `scrollToTopOfGallery()`
  covers the new caller, so no second implementation is introduced.
- [x] 4.3 Add a test asserting live search resets the scroll through the shared
  helper rather than its own `scrollTo`.

## 5. Keep the toolbar pinned when the keyboard opens

Found on Android Chrome while validating `:dev`. A keyboard shrinks only the
visual viewport by default, while `position: sticky` resolves against the layout
viewport, so the pinned toolbar ends up anchored above the visible area.

- [x] 5.1 Add `interactive-widget=resizes-content` to the viewport meta in
  `templates/layout.html.twig`, so the keyboard resizes the layout viewport.
- [x] 5.2 Assert the keyword in `tests/Functional/ApplicationShellTest.php` — it
  reads as boilerplate and is easy to drop while tidying.
- [x] 5.3 Drop the reduced-motion test duplicated into `StickyToolbarTest`;
  `PaginationScrollTest` already owns that assertion, and update its now-stale
  "only paging animates" comment to name the shared helper instead.

## 6. Docs and gates

- [x] 6.1 Check whether `README.md`, `docs/`, or `CLAUDE.md` describe the menu
  glyph or the toolbar's scroll behavior; update in the same commit if so, and
  state explicitly that nothing was stale if not.
- [x] 6.2 Run `composer test`, `composer stan`, and `composer cs` — all three
  must pass before committing.
- [ ] 6.3 Verify by hand on a phone-width viewport against the `:dev` image:
  the trigger reads as an overflow menu and still opens the tray; the toolbar
  pins while scrolling with no posters visible behind or beside it; opening any
  tray still covers the toolbar; searching from part-way down returns to the
  first match smoothly; a search with no matches settles cleanly; the desktop
  layout is unchanged.
