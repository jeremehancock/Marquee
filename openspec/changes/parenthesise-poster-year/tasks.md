## 1. Title rendering

- [x] 1.1 In `src/Poster/Poster.php`, add a private helper that strips a trailing `' ' . $year` from a title when `$year` is non-null and the title ends with it, mirroring `trailingLibraryToken()`'s known-value comparison. Never pattern-match digits.
- [x] 1.2 Change `captionTitle()` to `captionTitle(?string $libraryTitle = null, ?int $year = null)`: strip the library token, then — when a year is known — strip a trailing year token from what remains and append `' (' . $year . ')'`. A known year is appended whether or not it was present in the title; an unknown year leaves the title untouched.
- [x] 1.3 Delete `sheetTitle()` and update the class docblocks so `captionTitle()` is described as the one title every surface shows (caption, tooltip, action tray, change-poster dialog).

## 2. Getting the year to the template

- [x] 2.1 Add `PlexItemRepository::yearsForCategory(string $category): array<string, int>` next to `librariesForCategory()`, selecting `filename, year` for the category where `year IS NOT NULL`, keyed by filename.
- [x] 2.2 In `src/Controller/GalleryController.php`, build `$plexYears` alongside `$plexLibraries` over `$view->categories()` and pass it to the template as `plex_years`. Update the neighbouring comment to cover both maps.

## 3. Templates and front-end

- [x] 3.1 In `templates/partials/gallery_results.html.twig`, set `poster_year` from `plex_years` the way `poster_library` is set, and pass both to `poster.captionTitle(...)`.
- [x] 3.2 Use that one `caption_title` for the figcaption text, its `data-tooltip`, and the Change-poster button's `data-title`; remove the now-redundant `data-sheet-title` attribute.
- [x] 3.3 In `public/assets/gallery.js`, drop the `data-sheet-title` lookup in the tray opener so it reads the caption's own text, and update the comment above it — the caption is now the sheet title rather than a library-stripped variant of it.

## 4. Tests

- [x] 4.1 Update `tests/Unit/Poster/PosterTest.php`: the two `sheetTitle()` cases become `captionTitle()` cases with no library appended, and existing `captionTitle()` cases pass a year where one applies.
- [x] 4.2 Add `Poster` unit cases for the year rules: a year already ending the title moves into parens and is not duplicated ("Louis and the Nazis 2003" + 2003 → "Louis and the Nazis (2003)"); a known year absent from the title is appended ("Breaking Bad" + 2008, "Breaking Bad - Season 1" + 2008); digits that are not the year survive ("1883" + 2021 → "1883 (2021)", "Blade Runner 2049 2017" + 2017 → "Blade Runner 2049 (2017)"); no known year leaves the title alone; library and year handled together.
- [x] 4.3 Add a `PlexItemRepository` test for `yearsForCategory()`: returns filename→year for the category and omits rows with a null year.
- [x] 4.4 Add or extend a gallery feature test asserting the rendered caption carries the parenthesised year and that no `data-sheet-title` attribute remains.
- [x] 4.5 Assert the read-only guarantee where it is cheapest to prove: a gallery render leaves poster filenames on disk and `plex_items` rows byte-identical.

## 5. Verification

- [x] 5.1 Grep for `sheetTitle` and `data-sheet-title` across `src/`, `templates/`, `public/`, and `tests/` to confirm no caller survives.
- [x] 5.2 Run `composer test`, `composer stan`, and `composer cs` — all three must pass.
- [x] 5.3 Check `README.md`, `docs/`, and `CLAUDE.md` for anything describing the caption or sheet title; update in the same commit, or state explicitly that nothing user-facing is documented there.
- [x] 5.4 Build the image and eyeball the gallery, the mobile action tray, and the change-poster dialog against a real library: a movie (year moved into parens), a TV show and a TV season (year added), a collection (unchanged), and a title ending in digits such as "1883" (digits kept, real year appended).

## 6. Revision: take the title from Plex, not from the filename

`:dev` validation found a season showing its year twice — "Lucky 2026 - Season 1
(2026)" — because a show Plex names "Lucky (2026)" reaches the filename with its
parentheses flattened, so the embedded year is invisible to a trailing-token rule
and the real year gets appended after it. No rule over the filename can fix this:
"Lucky 2026 - Season 1" and "Class of 2026 - Season 1" are the same shape. The
recorded title (`plex_items.title`, unsanitised) has the parentheses intact, so
the fix is to stop reconstructing and start reading.

- [x] 6.1 Rewrite `Poster::captionTitle()` to `captionTitle(?string $plexTitle = null, ?int $year = null)`: use `$plexTitle` when non-empty, else `title()`; append `' (' . $year . ')'` only when a year is known and the title does not already contain `'(' . $year . ')'`.
- [x] 6.2 Delete `trailingLibraryToken()` and the trailing-year helper. The recorded title never carried a library and never had its year flattened, so neither has anything left to do.
- [x] 6.3 Add `PlexItemRepository::titlesForCategory(string $category): array<string, string>` selecting `filename, title` where the title is non-empty.
- [x] 6.4 Delete `PlexItemRepository::librariesForCategory()` — the gallery was its only caller — and any test that covers it.
- [x] 6.5 In `GalleryController`, replace `$plexLibraries`/`plex_libraries` with `$plexTitles`/`plex_titles`, keeping `plex_years`.
- [x] 6.6 In `gallery_results.html.twig`, set `poster_title` from `plex_titles` and pass it to `captionTitle()`. Point the image `alt` and the delete confirmation at `caption_title` too — they still name the raw filename-derived title, library token and all.
- [x] 6.7 Rework the `Poster` unit tests for the new rule: title taken from the record; punctuation preserved; year appended; year already parenthesised not repeated ("Lucky (2026)", "Lucky (2026) - Season 1"); bare digits not treated as a present year ("Class of 2026" → "Class of 2026 (2026)"); "1883" and "Blade Runner 2049" keep their digits; empty or absent record falls back to `title()`.
- [x] 6.8 Add a `titlesForCategory()` repository test (maps filename→title, skips empty titles, ignores other categories) and drop the `librariesForCategory()` coverage.
- [x] 6.9 Update the gallery feature tests: seed a record whose title carries its own year and assert the caption names it once; assert punctuation survives; assert the delete confirmation and `alt` use the caption title.
- [x] 6.10 Re-run `composer test`, `composer stan`, `composer cs`; re-grep for `sheetTitle`, `data-sheet-title`, `librariesForCategory`, and `plex_libraries`; re-check docs.
- [ ] 6.11 Re-validate on `:dev` — the season that prompted this, plus a title with an apostrophe or ampersand, since punctuation now renders differently across the whole library.
