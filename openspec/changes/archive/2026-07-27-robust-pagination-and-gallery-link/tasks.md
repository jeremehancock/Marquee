## 1. Page window logic

- [x] 1.1 Add a `paginationWindow(int $edge = 1, int $around = 1): array` method
  to `src/Poster/Page.php` that returns an ordered list of tokens — integer
  page numbers plus an ellipsis marker for each collapsed gap — always
  including the first/last `edge` pages and `around` pages either side of the
  current page, and rendering the actual number (not an ellipsis) for gaps of
  exactly one page.
- [x] 1.2 Add unit tests in `tests/Unit/Poster/PageTest.php` covering: single
  page (no window), small totals with no ellipsis, current page at start, mid,
  and last, a large total (e.g. 82 pages) producing `1 2 3 … 82`, and a
  one-page gap rendering the number rather than an ellipsis.

## 2. Pagination markup

- [x] 2.1 Rework the `.pagination` block in
  `templates/partials/gallery_results.html.twig` to render go-to-first and
  go-to-last controls, previous/next steppers, and the windowed page-number run
  from `paginationWindow()`; render each number as a `.pagination a` link except
  the current page, which is an inert active marker, and each ellipsis token as
  a plain span.
- [x] 2.2 Ensure every generated link reuses the existing `q` string so the
  active search query and non-default sort order are preserved (matching the
  current Previous/Next links).
- [x] 2.3 Add CSS for the number list and the active current-page state in the
  gallery stylesheet under `public/assets/`.

## 3. Back-to-gallery label

- [x] 3.1 Change "Back to library" to "Back to gallery" in
  `templates/plex.html.twig` and `templates/orphans.html.twig`.
- [x] 3.2 Update any test or fixture asserting the old "Back to library" text.

## 4. Verification

- [x] 4.1 Run PHPStan (max level) and the PHPUnit suite; fix any failures.
- [x] 4.2 Build the Docker image and smoke-test the gallery: confirm the
  windowed pagination, first/last/prev/next navigation with search and sort
  active, and the renamed "Back to gallery" link on the Import and Orphans
  pages.
- [x] 4.3 Run `openspec validate --change "robust-pagination-and-gallery-link"`.
