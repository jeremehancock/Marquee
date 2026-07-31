## 1. Truncation-conditional caption tooltips

- [x] 1.1 In `public/assets/app.js`, add a truncation gate to the tooltip
      module: a helper that reports whether a host opts into the gate (a
      `data-tooltip-truncated` attribute) and, if so, whether it is currently
      truncated (`scrollWidth > clientWidth`).
- [x] 1.2 Call the gate from `show()`, alongside the existing `allowed()` check,
      so an opted-in host whose text fits shows no bubble. Hosts without the
      attribute keep showing unconditionally.
- [x] 1.3 Toggle the cursor state class (`is-truncated`) on the host from the
      same measurement on `pointerover`, so the `help` cursor and the tooltip
      never disagree.
- [x] 1.4 In `public/assets/app.css`, move `cursor: help` from `.card__caption`
      onto `.card__caption.is-truncated`, updating the existing comment to
      explain the condition.
- [x] 1.5 Add `data-tooltip-truncated` to the caption in
      `templates/partials/gallery_results.html.twig` (line ~75) and
      `templates/orphans/_results.html.twig` (line ~35). Leave every other
      `data-tooltip` host untouched.

## 2. No-artwork message wording

- [x] 2.1 In `src/Controller/ChangePosterController.php`, change the
      `PosterSearchOutcome::NoArtwork` message to "This title was found, but no
      posters are available."

## 3. Standard web-app-capable meta tag

- [x] 3.1 In `templates/layout.html.twig`, add
      `<meta name="mobile-web-app-capable" content="yes">` next to the existing
      `apple-mobile-web-app-capable` tag, keeping both.

## 4. Stop documenting POSTER_SOURCE_URL

- [x] 4.1 Remove the `POSTER_SOURCE_URL` row from the README configuration
      table. Leave `src/bootstrap.php` and every other posteria.app mention in
      the README unchanged.

## 5. Tests

- [x] 5.1 In `tests/Functional/PwaTest.php`, assert a rendered page carries both
      `mobile-web-app-capable` and `apple-mobile-web-app-capable`.
- [x] 5.2 In `tests/Functional/GalleryTest.php`, assert the poster caption
      carries `data-tooltip-truncated` while pagination-step tooltips do not.
- [x] 5.3 Add or update an orphans test asserting the orphan caption carries
      `data-tooltip-truncated`.
- [x] 5.4 Update any test asserting the old no-artwork wording, and add one
      covering the new string if none exists.

## 6. Verification

- [x] 6.1 Run `composer test`, `composer stan`, and `composer cs` — all three
      must pass.
- [x] 6.2 Confirm docs are current: README's configuration table is the only
      user-facing doc affected; note explicitly that `docs/` and `CLAUDE.md`
      need no edits.
- [ ] 6.3 Manual check on the `:dev` image: hover a short poster title (no
      tooltip, no `help` cursor) and a long one (tooltip with the full title,
      `help` cursor); narrow the window until a fitting title truncates and
      confirm the tooltip starts appearing; confirm the Chrome console shows no
      `apple-mobile-web-app-capable` deprecation warning.
