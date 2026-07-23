## 1. Domain: aggregate listing and tab model

- [x] 1.1 Add an aggregate browse path to `PosterLibrary` (e.g. `browseAll()`) that lists all four category directories, merges the `Poster` objects, applies the article-aware sort with a category tiebreak (Movies → TV Shows → TV Seasons → Collections), then paginates with the same `perPage`/clamping logic as `browse()`.
- [x] 1.2 Route search through the aggregate path: when a query is present, filter the merged set the same way `browse()` filters a single category.
- [x] 1.3 Add a small tab view-model (ordered `{ value, label, active }` descriptors: All first, then the four categories) so the template no longer iterates `PosterCategory::all()` directly. Keep `PosterCategory` at its four directory-backed cases.

## 2. Routing and controller

- [x] 2.1 Change `GalleryController::home()` to redirect `/` to `/library/all`.
- [x] 2.2 In `GalleryController::show()`, branch on the `all` slug: use the aggregate browse path, build the tab view-model, and pass an `is_all_view` flag to the template. Keep the four real slugs resolving via `PosterCategory::fromSlug()` and unknown slugs returning 404.
- [x] 2.3 Make the linked-poster lookup category-aware for the All view: gather linked filenames per category (a map keyed by category) instead of `filenamesForCategory(one)`, so "is this poster linked?" is answered by (category, filename). Single-category views keep their current single lookup.
- [x] 2.4 Record the `all` view in `LastCategory` so returning from Orphans/Import lands back on All.

## 3. Templates: tabs, per-poster category, badge

- [x] 3.1 Render the tab strip from the tab view-model (All tab first), replacing the direct `PosterCategory::all()` loop, with active-state matching on the tab value.
- [x] 3.2 In `gallery_results.html.twig`, build each card's action URLs from the poster's own `category.value` instead of the page-level `base` (delete, send-to-plex, fetch-from-plex forms). Verify this equals the page base in single-category views (no behavior change there).
- [x] 3.3 Add `data-category` to the change trigger so the change event can carry the poster's category. Use the category-aware linked map to decide whether to show Plex actions.
- [x] 3.4 Render a type badge inside `.card__frame`, only when `is_all_view` is set, labeling the poster Movie / TV Show / TV Season / Collection. No badge in single-category views.

## 4. Front-end: change modal category, badge CSS, mobile tabs

- [x] 4.1 Carry the poster's category through the `gallery:change` event in `gallery.js`, and have the Alpine change-modal build its post base from the event's category instead of the page-level `this.base` (upload, from-URL, and find-posters actions).
- [x] 4.2 Add badge styling in `app.css`; inside `@media (hover: hover)` add `.card__frame:hover .card__badge { opacity: 0 }` mirroring the overlay reveal, so the badge hides on hover on pointer devices and persists on touch.
- [x] 4.3 Rework the mobile (`max-width: 640px`) tab strip into a single horizontal scroll-snap row instead of the wrapping grid, so five tabs never overflow; scroll the active tab into view and add an edge fade hinting the row scrolls.
- [x] 4.4 Verify the mobile action sheet still receives category-correct forms (it clones `.card__actions`, so per-card category carries through automatically).

## 5. Tests and verification

- [x] 5.1 Add tests for the aggregate browse: merged listing, mixed-alphabetical order, category tiebreak on equal titles, and pagination of the combined set.
- [x] 5.2 Add tests that the `all` slug is browsable, `/` redirects to `/library/all`, and an unknown slug still returns 404.
- [x] 5.3 Add a test that an action on an All-view poster targets its own category even when another category has a poster with the same filename (category-keyed linked lookup and action URL).
- [x] 5.4 Manually verify: badge shows only in All, hides on hover (desktop) and persists on touch; five tabs fit without overflow on a phone; change/send/fetch/delete work from the All view for each type; PHPStan and PHPUnit pass.
