## 1. Make the image reveal page-agnostic

- [x] 1.1 In `public/assets/gallery.js`, move `markLoaded()` and `initImages()` out of the `DOMContentLoaded` handler up to the IIFE scope.
- [x] 1.2 Call `initImages(document)` inside the `DOMContentLoaded` handler *before* the `document.querySelector('[data-gallery]')` early return, so pages without a gallery root still reveal their poster cards.
- [x] 1.3 Confirm the gallery block still calls `initImages(results)` from `setResults()` so cards swapped in by search and pagination fetches keep fading in.
- [x] 1.4 Update the comment block at the top of `gallery.js` to note that the lazy-load fade-in applies to every page, not just the gallery.
- [x] 1.5 Widen `markLoaded()`'s fast path from `img.complete && img.naturalWidth > 0` to `img.complete`. Added during implementation: a fetch that already failed is complete with zero naturalWidth, so it was waiting on an `error` event that had already fired and stayed transparent forever. See design decision 3.

## 2. Stop the shimmer once an image resolves

- [x] 2.1 In `public/assets/app.css`, add a rule near `.card__frame::before` that cancels the shimmer when the frame contains a loaded image (`.card__frame:has(.card__image.is-loaded)::before { content: none }`).
- [x] 2.2 Add a short comment explaining that browsers without `:has()` simply keep the (hidden) shimmer running behind the opaque image, so the degradation is invisible.

## 3. Verify

- [x] 3.1 Add a functional assertion in `tests/Functional/OrphanTest.php` that an orphan renders with the shared card markup (`card__frame` wrapping a `card__image`), locking in the contract the shared JS depends on.
- [x] 3.2 Run `composer` PHPUnit and PHPStan (max level) and confirm both pass. 124 tests / 274 assertions OK; PHPStan clean; PHP-CS-Fixer clean.
- [x] 3.3 Verify the orphans page in a headless browser against the real app (real routes and templates, `FakePlexClient` supplying the orphan). Pre-fix: `revealed=0 STUCK=Gone.jpg`, screenshot shows an empty placeholder. Post-fix: `revealed=1`, screenshot shows the poster.
- [x] 3.4 Verify no gallery regression by driving the real app's live search and pagination in a headless browser, comparing pre-fix and post-fix assets. Both identical: initial `cards=24 revealed=24`, live search `cards=12 revealed=12`, page 2 `cards=7 revealed=7`, `stuck=0` throughout.
- [x] 3.5 Verify a poster image that 404s resolves to a broken image with the shimmer stopped. Note: the original framing ("an orphan whose file is missing") cannot occur — `OrphanService::findOrphans()` skips records with no file on disk — so the failed-load path was exercised directly with a card pointing at a 404. This is what surfaced task 1.5.
