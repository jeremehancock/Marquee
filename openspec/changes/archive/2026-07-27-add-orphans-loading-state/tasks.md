## 1. Template: shell + results fragment

- [x] 1.1 Extract the found / empty ("in sync") / error branches of `templates/orphans.html.twig` into a shared partial `templates/orphans/_results.html.twig` (grid, toolbar, and delete-all confirmation modal), rendering identically to today.
- [x] 1.2 Reduce `orphans.html.twig` to the shell: intro copy, flash, the not-configured panel (instant, no spinner), a results container that includes the partial only on a full render, and an import-style `.overlay`/`.overlay__box`/`.spinner` block shown while loading (Alpine `loading` state, visible on load when Plex is configured).
- [x] 1.3 Add a `<noscript>` fallback note in the shell so a JS-disabled client is not left on a permanent spinner.

## 2. Controller + route

- [x] 2.1 Change `OrphanController::show` to render the shell only (compute `isConfigured()`, pass flash and `back_url`; do NOT call `findOrphans()`).
- [x] 2.2 Add `OrphanController::results`: run `findOrphans()` (catch `PlexException` into `error`), and render the `_results.html.twig` partial as the response body.
- [x] 2.3 Register `GET /orphans/list` → `[OrphanController::class, 'results']` in `src/Routes.php`, above/near the existing `/orphans` routes.

## 3. Front-end wiring

- [x] 3.1 On the orphans page (when Plex is configured), fetch `GET /orphans/list` after the shell paints, using the same `X-Requested-With: fetch` / `credentials: same-origin` convention as `gallery.js`.
- [x] 3.2 On success, swap the returned fragment into the results container and set `loading = false` (hide the overlay). (Refinement: the delete-all confirmation modal stays in the shell as a page-level Alpine overlay; the swapped fragment is plain HTML whose "Delete all" button and count are wired to the component after injection — no Alpine re-init needed.)
- [x] 3.3 On fetch failure, set `loading = false` and render an inline error in the results container so the page never sticks on the spinner.

## 4. Verify

- [x] 4.1 Update/extend `tests/Functional/OrphanTest.php`: `GET /orphans` returns quickly with the shell + spinner and does not perform the scan; `GET /orphans/list` returns the results fragment (orphans, in-sync, and Plex-error cases); not-configured renders the Plex-required message with no spinner.
- [ ] 4.2 Manually confirm in a browser: opening `/orphans` paints immediately with the spinner, results replace it when the scan finishes, and delete-all still works and shows its flash message. (Not yet done — automated tests cover shell/fragment/not-configured; needs a live click-through.)
- [x] 4.3 Run `composer` checks (PHP-CS-Fixer, PHPStan max, PHPUnit) and ensure they pass.
