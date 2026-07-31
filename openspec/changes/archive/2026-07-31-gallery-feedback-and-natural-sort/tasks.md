## 1. Digit-aware sort key

- [x] 1.1 Add digit-aware padding to `Poster::sortKey()` in `src/Poster/Poster.php`: pad every run of digits to a fixed width of 12, applied after the existing lowercase and article-stripping steps. Leave a run longer than the pad untouched so it falls back to today's lexicographic comparison. Document in the docblock that this is a sort key only — never displayed, never written to disk — and why the width is fixed.
- [x] 1.2 Add a deterministic final tie-break so titles that differ only by leading zeros ("Season 01" vs "Season 1") do not rely on `usort()` ordering. Keep the existing `[sortKey, category]` array comparison in `src/Poster/PosterLibrary.php` intact — extend the tuple rather than replacing it with a comparator.
- [x] 1.3 Confirm `src/Poster/PosterLibrary.php` needs no other change: the date-added branch is timestamp-based and must stay untouched, and the category tie-break for the All view must still hold.

## 2. Digit-aware search tie-break

- [x] 2.1 Apply the same digit padding to the tie-break key in `PosterSearch::filter()` in `src/Poster/Search/PosterSearch.php`. It runs on `normalize()`d titles (accent-folded, punctuation flattened), so pad after that normalization — the padding step is what is shared, not the normalization.
- [x] 2.2 Verify the relevance score still leads and the digit comparison only separates results that match equally early.

## 3. Sorting tests

- [x] 3.1 Extend `tests/Unit/Poster/PosterTest.php` with `sortKey()` cases: Season 2 before Season 10, "Ocean's 8" before "Ocean's 11", a digit run longer than the pad falling back to lexicographic order, and composition with `IGNORE_ARTICLES_IN_SORT` both enabled and disabled.
- [x] 3.2 Extend `tests/Unit/Poster/PosterLibraryTest.php` with an ordering case covering a show's Season 1 through Season 10 or beyond, the category tie-break in the All view still holding, the leading-zero tie being deterministic across repeated sorts, and date-added ordering being unchanged.
- [x] 3.3 Extend `tests/Unit/Poster/PosterSearchTest.php` with a tie-break case proving equally relevant seasons list 1, 2, 3 … 10, and an existing-behaviour case proving match position still outranks the digit comparison.
- [x] 3.4 Add or extend a case in `tests/Functional/GalleryTest.php` asserting the rendered A–Z gallery lists seasons in numeric order.

## 4. Find Posters apply feedback

- [x] 4.1 Add an `applying` flag to the `finder` state object in `public/assets/gallery.js` — it is reset in several places (`openChange`, `findPosters`, the catch branch), so add it to every literal that rebuilds `finder` rather than only the initial one.
- [x] 4.2 In `applyFinderSelection()`, guard re-entry by returning early when a change is already in flight, then set the flag before the `fetch()`.
- [x] 4.3 Add a `finally` that clears the flag on both the success and failure paths, so a failure leaves the preview usable rather than stranded behind the overlay.
- [x] 4.4 Add a response-status check so an unsuccessful response is reported as a failure instead of being parsed as a success page. *(Flagged as strikeable in the proposal — drop this task and its test if not wanted.)*
- [x] 4.5 Add the progress overlay to the Find Posters preview in `templates/gallery.html.twig`, reusing the existing `.overlay` / `.overlay__box` / `.spinner` markup exactly as `templates/plex.html.twig` and `templates/orphans.html.twig` do, with `x-cloak` and `style="display:none"`. Write copy in the same voice as those overlays.
- [x] 4.6 Bind the confirm button's `disabled` to the flag, mirroring the `:disabled="… || importing"` pattern in `templates/plex.html.twig`.
- [x] 4.7 Confirm no CSS change is needed: `.overlay` is `z-index: 100` and `.viewer` is `z-index: 60`, and the reduced-motion opt-out already covers `.spinner`. Check the overlay is not caught by the `.sheet .overlay` containment rule, since the preview is not inside a tray. Only add CSS if this check actually fails.

## 5. Find Posters feedback tests

- [x] 5.1 Add a shape tripwire test under `tests/Unit/Asset/`, following the pattern and the explanatory docblock style of `tests/Unit/Asset/GalleryLoadingIndicationTest.php`: assert `applyFinderSelection()` carries a re-entrancy guard, sets the flag before the fetch, clears it in a `finally`, and checks the response status. Say plainly in the docblock that this is a source-shape check, not a behaviour test, and that the real verification is by hand against the `:dev` image.
- [x] 5.2 Assert in the same test, or a template-level test, that the confirm button is bound to the disabled state and that the overlay markup is present in `templates/gallery.html.twig`.

## 6. Gates and documentation

- [x] 6.1 Run `composer test`, `composer stan`, and `composer cs` — all three must pass. Use `composer cs:fix` for formatting rather than hand-editing.
- [x] 6.2 Check whether `README.md`, `docs/`, or `CLAUDE.md` are made stale by this change. Neither part alters configuration, the Docker image, or a documented workflow, so the expected outcome is no edit — record that explicitly rather than inventing one.
- [x] 6.3 Run `openspec validate gallery-feedback-and-natural-sort --strict` and fix anything it reports.

## 7. Validation on the `:dev` image

- [x] 7.1 Verify by hand on desktop: apply a poster through Find Posters, confirm the overlay appears immediately, the confirm button is disabled, a second click does nothing, and the overlay clears on both success and failure.
- [x] 7.2 Verify the same on a phone, where the change-poster dialog is a sheet — confirm the overlay covers the full-screen preview rather than being confined to the panel.
- [x] 7.3 Verify A–Z ordering in the TV Seasons tab, in the All view, and in a search for a show with ten or more seasons.
- [x] 7.4 Do not archive until this validation has been done — archiving rewrites `openspec/specs/`, the source of truth.
