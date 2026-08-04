## 1. Sort order model

- [x] 1.1 Add `AlphabeticalDesc = 'alphabetical_desc'` and `DateAddedAsc = 'date_added_asc'` to `App\Poster\SortOrder`, leaving `alphabetical` and `date_added` spelled as they are so existing config, bookmarks, and sessions keep resolving
- [x] 1.2 Add a `SortField` (title, date added) and `SortDirection` (asc, desc) enum, plus `SortOrder::field()`, `SortOrder::direction()`, and `SortOrder::flipped()`
- [x] 1.3 Add `SortField::defaultOrder()` returning the field's default-direction `SortOrder` (title → ascending, date added → descending), and `SortField::order(SortDirection)` for building an order from a remembered direction
- [x] 1.4 Update `SortOrder::label()` so the title cases read `A–Z` and `Z–A` and both date cases keep one constant label, and add an `ariaLabel()` naming field and direction in words
- [x] 1.5 Confirm `SortOrder::fromSlug()` resolves all four slugs and still accepts the `alpha` shorthand

## 2. Sort state and session

- [x] 2.1 Add an `App\Support\SortState` value object exposing `current`, `toggled` (the active order flipped), and `alternate` (the other field at its remembered direction)
- [x] 2.2 Change `SortPreference::resolve()` to return `SortState`, reading and writing a per-field direction session key alongside the existing `sort_order` key
- [x] 2.3 Make a valid `?sort=` update both the active order and that field's remembered direction
- [x] 2.4 Resolve a missing or unreadable per-field direction key to that field's default direction, so a session holding only a legacy `sort_order` string upgrades silently

## 3. Shared comparator

- [x] 3.1 Add a comparator factory keyed by `SortOrder` that returns the `usort` callback for a listing, taking the `addedAt` map for the date field
- [x] 3.2 Reverse the primary field only — keep the existing tie-breaks (category order, then the digit-aware title key) ascending in both directions
- [x] 3.3 Replace the two hand-rolled `usort` callbacks in `PosterLibrary::paginate()` with the factory
- [x] 3.4 Preserve the file-modification-time fallback for posters with no Plex `added at` timestamp, in both directions

## 4. Search becomes a filter

- [x] 4.1 Reduce `PosterSearch::filter()` to a match test, dropping the match-position score, its `usort`, and the `NaturalOrder` tie-break
- [x] 4.2 Remove the `SortComparator` dependency from `PosterSearch` — ordering is no longer its concern
- [x] 4.3 Replace `PosterLibrary::paginate()`'s search-or-sort branch with filter-then-always-sort

## 5. Controller and view data

- [x] 5.1 Update `GalleryController` for `SortPreference::resolve()` returning `SortState`, and pass the state to the template alongside the current slug
- [x] 5.2 Ensure the date-added `addedAt` lookup runs whenever the active field is date added, in either direction

## 6. Icons and the sort control

- [x] 6.1 Add a `sort-title` glyph (bars of increasing length, no arrow) and a `sort-date` glyph (calendar) to `templates/partials/_icons.html.twig` in the house style, and extend the header comment's caller list
- [x] 6.2 Add a `arrow` glyph, rotated 180° by CSS for the ascending direction rather than drawn twice
- [x] 6.3 Add a Twig macro rendering the sort control from a `SortState`: one button per field with field glyph, label, and direction arrow
- [x] 6.4 Render the active button with its current order as the label and its flipped order as the href; render the inactive button with its remembered direction as both label and href
- [x] 6.5 Give each button an `aria-label` and `data-tooltip` naming field and direction in words, and keep `aria-current` on the active button
- [x] 6.6 Replace the duplicated markup in the desktop toolbar and the phone sort tray in `templates/gallery.html.twig` with the macro
- [x] 6.7 Add the button layout and arrow rotation rules to `public/assets/app.css`

## 7. URL carrying

- [x] 7.1 Replace the hardcoded `sort == 'date_added'` test in `templates/partials/gallery_results.html.twig` so pagination links carry whatever the current order is
- [x] 7.2 Replace the same test for tab links in `templates/gallery.html.twig`
- [x] 7.3 Confirm `public/assets/gallery.js` needs no change — the target slug is rendered into `data-sort` and the existing handler navigates to it

## 8. Tests

- [x] 8.1 Extend `tests/Unit/Poster/SortOrderTest.php` for the four slugs, `field()`, `direction()`, `flipped()`, labels, and the `alpha` shorthand
- [x] 8.2 Add tests for `SortState` and the updated `SortPreference`: toggling, per-field direction memory, a legacy session value, and `?sort=` updating the remembered direction
- [x] 8.3 Extend `tests/Unit/Poster/PosterLibraryTest.php` for Z–A and oldest-first listings, the aggregate view, stable tie-breaks under reversal, and the modification-time fallback in both directions
- [x] 8.4 Rework `tests/Unit/Poster/PosterSearchTest.php` for filter-only behaviour: where a term matches carries no weight, and matches come back in the order they were given
- [x] 8.8 Cover the reported defect in `tests/Unit/Poster/PosterLibraryTest.php` — sorting by date added while searching orders every match, including one whose title contains the query mid-string
- [x] 8.5 Confirm `tests/Unit/Config/PosterConfigTest.php` still covers `DEFAULT_SORT` mapping `alphabetical` to A–Z and `date_added` to newest first
- [x] 8.6 Add functional coverage in `tests/Functional/GalleryTest.php` for the rendered control: active button label and flipped href, inactive button label matching its href, and sort carried in pagination and tab links
- [x] 8.7 Check `tests/Unit/Asset/StickyToolbarTest.php` and any other asset tests asserting on toolbar markup, and update them for the macro output

## 9. Verification

- [x] 9.1 Run `composer test`, `composer stan`, and `composer cs` and fix anything they report
- [x] 9.2 Check the desktop toolbar's button density with glyph, label, and arrow rendered; if the row is tight, drop the field glyph on the desktop control only
- [x] 9.3 Exercise the four orders in a browser, including toggling, switching fields and back, paging, tab switching, the phone tray, and sorting while a search is active
- [x] 9.4 Check whether `README.md` or `docs/` describe sort behavior and update them in the same commit; `DEFAULT_SORT`'s accepted values are unchanged, so state explicitly if nothing needs editing
