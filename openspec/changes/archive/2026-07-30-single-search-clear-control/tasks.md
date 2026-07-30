## 1. Remove the redundant control

- [x] 1.1 Delete the `{% if query is not empty %}` block holding the toolbar
      `Clear` link from the search form in `templates/gallery.html.twig`,
      leaving the search input and the rest of the toolbar intact.
- [x] 1.2 Confirm nothing else is removed by mistake: the `.search__clear` rule
      in `public/assets/app.css` stays (it styles the retained control and the
      "Back to gallery" links), and the delegated `.search__clear` click handler
      in `public/assets/gallery.js` stays unchanged and un-narrowed (its tray
      loader queries the same class).

## 2. Tests

- [x] 2.1 In `tests/Functional/GalleryTest.php`, add a test asserting that a
      filtered view renders exactly one clear control — count the occurrences of
      the clear link on `/library/movies?q=solaris` and assert it is one.
- [x] 2.2 Extend the same test (or add a sibling) asserting an unfiltered view
      renders no clear control at all.
- [x] 2.3 Verify the existing `Clear search` assertions in
      `testSearchFiltersAndShowsFilteredState` and
      `testFilteredEmptyStateIsDistinctFromEmptyLibrary` still pass unchanged.

## 3. Verify

- [x] 3.1 Run `composer test`, `composer stan`, and `composer cs` — all three
      must pass.
- [x] 3.2 Check the docs gate: confirm no `README.md`, `docs/`, or `CLAUDE.md`
      content describes the removed toolbar link, and state that explicitly if
      nothing needs editing.
- [x] 3.3 Manually confirm in the running app: search, reload the page with the
      query in the address, and see a single clear control in the results
      summary; clear it and see the control disappear with the filtered state.
- [x] 3.4 Run `openspec validate single-search-clear-control --strict`.
