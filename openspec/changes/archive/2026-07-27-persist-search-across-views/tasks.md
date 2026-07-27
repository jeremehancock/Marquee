## 1. Preserve the query across view switches (client)

- [x] 1.1 In [public/assets/gallery.js](../../../public/assets/gallery.js), add a delegated click handler for `.tabs a`: prevent default, read the live query from the `input[name="q"]` value, build `<tab-base>` (from the link's href/pathname) + `?q=<encoded query>` (omit `?q=` when the query is empty), call `load(url, true)`, and update `.tab--active` to the clicked tab.
- [x] 1.2 Ensure the swapped-in `#results` and the existing sort links stay consistent after a tab switch (query preserved in sort hrefs is server-rendered per request, so verify no stale client state remains); confirm `popstate` still restores the correct view+query via the existing handler.

## 2. Progressive-enhancement fallback (template)

- [x] 2.1 In [templates/gallery.html.twig](../../../templates/gallery.html.twig), render each tab's `href` with the current query appended (`/library/<value>?q=<query>` when a query is active) so switching views works filtered even without JS. Keep the JS handler authoritative by reading the live input value.
- [x] 2.2 Preserve a non-default sort in tab hrefs the same way pagination already does, so switching views does not silently reset the chosen sort order.

## 3. Filtered-state indicator (template)

- [x] 3.1 In [templates/partials/gallery_results.html.twig](../../../templates/partials/gallery_results.html.twig), when `query` is not empty, upgrade the `.stats` summary to name the active query and the current view's match count (e.g. `N matches for "<query>" in <view.label>`), and render an obvious Clear control that returns to the unfiltered current view (`/library/<view.value>`).
- [x] 3.2 Update the zero-result branch so a filtered empty view is clearly "no matches for <query> in <view.label>" with the same Clear control, distinct from the un-filtered "no posters yet — import from Plex" message.
- [x] 3.3 Verify the summary and Clear control are inside `#results` so they refresh automatically on every keystroke and view switch via the existing `setResults()`; confirm the Clear control routes through the existing AJAX handlers (or degrades to a normal link without JS).

## 4. Tests & verification

- [x] 4.1 Extend [tests/Functional/GalleryTest.php](../../../tests/Functional/GalleryTest.php) to assert that requesting a specific view with `?q=` returns the filtered grid and that the results summary names the query and match count.
- [x] 4.2 Add a functional assertion that a filtered view with zero matches renders the filtered empty state (query named) rather than the "import from Plex" empty state.
- [x] 4.3 Manually verify in the browser: search in All, switch to Movies/TV Shows/etc. — the query persists, the grid is filtered, the box stays populated, the summary + Clear appear, the URL updates, and back/forward + Clear behave correctly.
- [x] 4.4 Run `composer` checks (PHP-CS-Fixer, PHPStan max level, PHPUnit) and confirm all pass.
