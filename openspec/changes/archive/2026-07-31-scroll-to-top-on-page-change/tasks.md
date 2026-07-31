## 1. Implementation

- [x] 1.1 Add a `scrollToTopOfGallery()` helper in `public/assets/gallery.js`
      that calls `window.scrollTo({ top: 0, behavior: … })`, choosing `'auto'`
      when `window.matchMedia('(prefers-reduced-motion: reduce)').matches` and
      `'smooth'` otherwise, reading the media query on each call rather than
      caching it. Comment why it is a per-call `behavior` rather than a global
      `scroll-behavior: smooth` (the overlay scroll-lock restore must stay
      instant).
- [x] 1.2 Call the helper from the delegated `.pagination a` branch, before
      `load(pageLink.getAttribute('href'), true)`, so the scroll starts at click
      time and overlaps the fetch.
- [x] 1.3 Confirm nothing else was touched: the tab-switch handler keeps its
      instant `window.scrollTo(0, 0)`, the scroll-lock restore keeps its
      `window.scrollTo(0, scrollY)`, and no CSS `scroll-behavior` rule was added.

## 2. Tests

- [x] 2.1 Add `tests/Unit/Asset/PaginationScrollTest.php` as a source-shape
      tripwire in the style of `GalleryLoadingIndicationTest` — a docblock
      stating that browser scroll behaviour cannot be asserted without a JS test
      runner and is verified by hand against `:dev`.
- [x] 2.2 Assert the pagination branch scrolls to the top: the `.pagination a`
      handler reaches the helper before it calls `load(`.
- [x] 2.3 Assert reduced motion is honoured: the helper consults
      `prefers-reduced-motion: reduce` and can resolve to a non-smooth
      `behavior`.
- [x] 2.4 Assert the smooth scroll stays local to this interaction: no
      `scroll-behavior` declaration exists in `public/assets/*.css`, and
      `behavior: 'smooth'` appears in exactly one place in `gallery.js`.

## 3. Verification

- [x] 3.1 Run `composer test`, `composer stan`, and `composer cs` — all three
      must pass.
- [x] 3.2 Check `README.md`, `docs/`, and `CLAUDE.md` for staleness. This is an
      interaction detail with no user-facing documentation surface; if nothing
      needs editing, say so explicitly rather than inventing changes.
- [x] 3.3 Hand-verify on the `:dev` image at desktop width: page down a category
      with several pages, confirm each page change glides back to the first
      poster; repeat with the OS reduced-motion setting on and confirm the jump
      is instant; open and dismiss a tray and confirm the restored scroll
      position does not animate; confirm a narrow screen still appends by
      infinite scroll without the viewport moving.
