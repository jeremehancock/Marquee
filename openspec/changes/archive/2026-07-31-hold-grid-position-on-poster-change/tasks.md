## 1. Declare intent in the markup

- [x] 1.1 In `templates/partials/gallery_results.html.twig`, mark the
  `fetch-from-plex` form as a card-local refresh, carrying the poster's category
  so the card can be found without parsing the form action. (The filename is
  taken from what the form posts rather than duplicated into an attribute.)
- [x] 1.2 In the same template, mark the `send-to-plex` form as needing no
  refresh, and leave the `delete` form unmarked so it keeps the full-grid
  refresh by default.
- [x] 1.3 In `templates/gallery.html.twig`, mark the change tray's
  `change/upload` and `change/url` forms as card-local refreshes, bound to
  `change.category`.

## 2. Card-local update in the gallery script

- [x] 2.1 In `public/assets/gallery.js`, add a lookup that resolves a
  `(category, filename)` pair to its `.card` via the card's
  `[data-action="change"]` button, returning null when the card is not in the
  DOM.
- [x] 2.2 Add the card update itself: rebuild the poster URL from the current
  `src` with a fresh cache-busting parameter, and write it to the `<img>`, the
  Download link, and the `data-url` of the Copy URL and Full screen actions.
- [x] 2.3 Clear the card image's loaded state and re-apply `markLoaded` so the
  replacement image reveals on decode rather than appearing early.
- [x] 2.4 Teach `submitForm()` to read the refresh intent from the form: update
  the card, do nothing, or fall back to the existing `load(currentUrl(), false)`
  when unmarked or when the card cannot be found. Keep the flash toast and the
  busy counter behaving as they do now.
- [x] 2.5 Replace `applyFinderSelection()`'s `dispatch('gallery:refresh')` with
  the same card update, addressed by `change.category` / `change.filename`, with
  the same full-refresh fallback.
- [x] 2.6 Leave the `gallery:refresh` listener in place — import and orphan
  deletion still use it — and confirm no poster-change path dispatches it any
  more except as the fallback.
- [x] 2.7 Verify a failed change leaves the card untouched: the update must run
  only on a successful response, alongside the existing `r.ok` guard.

## 3. Tests and gates

- [x] 3.1 In `tests/Functional/GalleryTest.php`, assert a poster card renders the
  identity attributes and the per-action URL carriers the card update depends on
  (`data-filename`, `data-category`, the download `href`, and the `data-url` on
  copy/view), so the markup contract cannot rot silently.
- [x] 3.2 Assert the refresh-intent markers render on the poster-mutating forms
  as expected — card-local for the image-replacing operations, none for
  `send-to-plex`, absent for `delete`. Kept in `GalleryTest.php` next to 3.1
  rather than `ChangePosterTest.php`: the gallery renders this markup, and its
  sibling contract assertions already live there.
- [x] 3.3 Run `composer test`, `composer stan`, and `composer cs`; fix anything
  they report.

## 4. Docs

- [x] 4.1 Remove the personal-name attribution from the Posteria acknowledgement
  in `README.md`, keeping the link to the project.
- [x] 4.2 Confirm no other user-facing surface (templates, `docs/`, in-app
  strings) names an individual as the author of Posteria or posteria.app; state
  the result rather than inventing edits. Result: none. The only remaining
  occurrences of the name are the two repository slugs in URLs — the Posteria
  link itself and `UPDATE_REPO`'s default — which identify repos, not authorship.
- [x] 4.3 Check whether `README.md` or `docs/` describe the post-change gallery
  behaviour and update them if so; if nothing user-facing changed, say so.
  Result: no edits needed. The only claim in this area is the feature list's
  "background updates without full page reloads", which stays true and is now
  more true than it was.

## 5. Validation

- [x] 5.1 Build and run the `:dev` image and, at phone width, scroll several
  pages into the grid, change a poster, and confirm the view does not move, no
  other card re-renders, and the changed card shows the new image.
- [x] 5.2 At desktop width, repeat on a page other than the first and confirm the
  page, scroll offset, and pagination are unchanged.
- [x] 5.3 Confirm Send to Plex reports its result without touching the grid, that
  a failed change leaves the old image in place, and that delete and import still
  refresh the grid as before.
