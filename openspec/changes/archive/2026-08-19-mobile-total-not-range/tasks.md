## 1. The count line

- [x] 1.1 In `templates/partials/gallery_results.html.twig`, split the non-search
      count line into two spans inside the existing `<p class="stats">`: a
      `.stats__range` carrying today's "Showing X–Y of N", and a `.stats__total`
      carrying "Total: N".
- [x] 1.2 Comment the pair in the file's established voice: the range describes a
      page and belongs with the pager, so a screen with no pager reports the
      category total instead; the choice is made in CSS because the server does
      not know the viewport.
- [x] 1.3 Leave the search branch and the empty-state branch untouched. Confirm
      neither gains a `Total:` line — the search branch already reports a count,
      and it stays true while scrolling.

## 2. Choosing which one is shown

- [x] 2.1 Add `.stats__total { display: none }` to the base rules beside `.stats`
      in `public/assets/app.css`.
- [x] 2.2 Inside the existing `@media (max-width: 640px)` block — the one that
      already hides `.pagination` — hide `.stats__range` and reveal
      `.stats__total`. Do not add a new media query; the threshold is already
      written twice and this must not make it three.
- [x] 2.3 Comment the rules with why `display: none` is the right mechanism: it
      removes the hidden sentence from the accessibility tree as well as the
      page, so a screen reader hears exactly one report. `visibility` or
      `opacity` would leave both.

## 3. Tests

- [x] 3.1 Extend `tests/Functional/GalleryTest.php`: a paginated listing renders
      both the range sentence and the `Total:` sentence; a single-page category
      renders both as well.
- [x] 3.2 In the same test, assert an active search renders neither — its own
      match count is unchanged. Also covered: an empty library reports neither.
- [x] 3.3 Add a `tests/Unit/Asset/` CSS-reading test in the pattern of the
      existing sixteen: `.stats__total` is hidden by default, and the
      `max-width: 640px` block hides `.stats__range` while revealing
      `.stats__total`. Document in the test that this is the guard against the
      two rules drifting apart and leaving a phone showing both sentences or
      neither. Added as `GalleryCountReportTest`, which also pins that the
      hiding uses `display` rather than `visibility` or `opacity`.
- [x] 3.4 Run `composer test`, `composer stan`, and `composer cs`. All three must
      pass. 1021 tests / 3384 assertions green; PHPStan clean; CS clean.

## 4. Documentation and validation

- [x] 4.1 Check whether `README.md`, `docs/`, or `CLAUDE.md` describe the count
      line or the mobile gallery. If nothing user-facing is made stale, say so
      explicitly in the commit rather than inventing edits. **Nothing is stale.**
      No page describes the count line or mobile scrolling. `README.md:168`
      mentions "posters per page" only as a settings field, and that field and
      its hint are untouched.
- [x] 4.2 Run `openspec validate mobile-total-not-range --strict`.
- [x] 4.3 Build the Docker image and check the `:dev` image at a phone width:
      the line reads `Total: N` on load, still reads `Total: N` after scrolling
      through several batches, and reads the range again when the window is
      widened past 640px without a reload.
