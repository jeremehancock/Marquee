## 1. Capture Plex "added at"

- [x] 1.1 Add an `added_at` column to `plex_items` via `Database::migrate()` using `ensureColumn` (`INTEGER NOT NULL DEFAULT 0`).
- [x] 1.2 Add a nullable `addedAt` (int) field to `PlexItem` and parse the `addedAt` XML attribute in `HttpPlexClient::item()` and `seasons()`.
- [x] 1.3 Add an `addedAt` (int, default 0) field to `PlexItemRecord`, read it in `fromRow()`, and include `added_at` in `PlexItemRepository::upsert()` (INSERT and `ON CONFLICT` update).
- [x] 1.4 In `ImportService::importItem()`, pass the item's `addedAt` (or 0) into the `PlexItemRecord` upsert.

## 2. Sort order model and config

- [x] 2.1 Add a `SortOrder` enum (`Alphabetical`, `DateAdded`) with a URL/session slug (`alpha` / `date_added`), a `label()`, and a `fromSlug()`/`tryFromSlug()` helper.
- [x] 2.2 Add `defaultSort` (SortOrder) to `PosterConfig`, read from `DEFAULT_SORT` in `fromEnv()`, falling back to `Alphabetical` for unset/empty/unrecognized values.
- [x] 2.3 Add a `PlexItemRepository::addedAtForCategory(string $category): array<string,int>` returning a `filename => added_at` map for the category.

## 3. Ordering in the library

- [x] 3.1 Extend `PosterLibrary::browse()`/`browseAll()` to accept a `SortOrder` and, for date-added, an `(category => filename => added_at)` lookup.
- [x] 3.2 In `paginate()`, when the order is Date added, sort by `lookup[category][filename] ?? poster.modifiedAt` descending (category order as a stable tiebreak); otherwise keep the existing article-aware title sort. Search results remain untouched.

## 4. Controller wiring

- [x] 4.1 In `GalleryController::show()`, resolve the effective `SortOrder`: a valid `?sort=` query param (persisted to the session) wins, else the session value, else `PosterConfig::defaultSort`.
- [x] 4.2 When the effective order is Date added, build the `added_at` lookup from `PlexItemRepository` for the view's categories and pass it plus the order into `browse()`/`browseAll()`.
- [x] 4.3 Pass the active `SortOrder` (and its slug) to the template for the toggle and pagination links.

## 5. UI

- [x] 5.1 Add a sort toggle to the `.toolbar` in `gallery.html.twig` (Alphabetical / Date added) linking to `{{ base }}?sort=…`, preserving the current `q`, and marking the active order.
- [x] 5.2 Update pagination links in `partials/gallery_results.html.twig` to carry the active `sort` param alongside `q`.

## 6. Docs and tests

- [x] 6.1 Document `DEFAULT_SORT` (accepted values, default) in the README env section and the docker-compose example.
- [x] 6.2 Unit-test `SortOrder` parsing/defaulting and `PosterConfig` reading `DEFAULT_SORT`.
- [x] 6.3 Unit-test `PosterLibrary` date-added ordering, including the file-mtime fallback for posters with no stored `added_at`.
- [x] 6.4 Test that `HttpPlexClient` parses `addedAt` and that `ImportService`/repository persist it (round-trip via `findByRatingKey`).
- [x] 6.5 Run `composer` checks (PHP-CS-Fixer, PHPStan max, PHPUnit) and fix any failures.
