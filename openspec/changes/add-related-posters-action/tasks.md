## 1. Record a season's show title

- [x] 1.1 Add the `parent_title` column in `Database::migrate()` via
      `ensureColumn($pdo, 'plex_items', 'parent_title', "TEXT NOT NULL DEFAULT ''")`,
      with a comment in the style of the `season_number` and `tmdb_id` entries
      saying what it holds and why the display title cannot be split back apart.
- [x] 1.2 Add a `parentTitle` property to `PlexItemRecord`, defaulted `''`, and
      read it in `fromRow()`.
- [x] 1.3 Persist it in `PlexItemRepository::upsert()` — the column list, the
      `VALUES` list, the `ON CONFLICT DO UPDATE SET` list, and the bound
      parameters.
- [x] 1.4 Record it in `ImportService`: `$item->parentTitle ?? ''` on the import
      path, and include it in `reconcileFacts()`'s difference check and its
      upsert so the skip path backfills existing rows. Keep the existing rule
      that a recorded fact is never replaced by an unknown one.
- [x] 1.5 Add a repository read for the related-title map, keyed by filename
      within a category, following the shape of `titlesForCategory()`. It returns
      the show title for a season and the item's own title otherwise, so callers
      never have to know which is which.

## 2. Search matches the shown title

- [x] 2.1 Change `PosterSearch::filter()` to accept a map of titles keyed by
      category value then filename, and match each poster against its recorded
      title where the map has one, falling back to `Poster::title()` otherwise.
      Match exactly one haystack per poster — never both.
- [x] 2.2 Build that map in `PosterLibrary::paginate()` from the injected
      `PlexItemRepository`. `paginate()` is the single call site and already has
      the repository, so no controller signature changes. Cover every category
      the listing spans, since `browseAll()` merges all four.
- [x] 2.3 Confirm `Poster::sortKey()` and `SortComparator` are untouched, so
      ordering is unchanged and "Search filters without reordering" still holds.

## 3. The Related posters action

- [x] 3.1 Add a glyph for the action to `templates/partials/_icons.html.twig`,
      reading as "a set of these" and visually distinct from the `collections`
      glyph.
- [x] 3.2 Pass a per-poster related-query map from `GalleryController` to the
      gallery template, alongside the `plex_titles` and `plex_years` maps it
      already passes and keyed the same way.
- [x] 3.3 In `templates/partials/gallery_results.html.twig`, replace the Copy URL
      control with a Related posters anchor: `href="/library/all?q=<related>"`,
      labelled "Related posters", rendered through the existing `action_body()`
      macro, and carrying a data attribute for the click handler. Fall back to the
      poster's own `title()` when no related query is known. Do not append the
      release year.
- [x] 3.4 In `public/assets/gallery.js`, intercept the action in the delegated
      click handler: set the search input's value to the query from the link, then
      call `switchCategory('/library/all')`. Do not add a second way to change
      category, and do not dispatch a synthetic `input` event.
- [x] 3.5 Make the action close the phone action sheet when activated from it,
      the way the `[data-action]` branch and the `.sheet__body a[download]` branch
      already do.
- [x] 3.6 Remove the `copy` branch from the click handler and the clipboard
      handling it dispatches to, including the `gallery:copy` listener and any
      now-unused toast wiring. Leave `Download` alone.
- [x] 3.7 Confirm the stack still renders seven controls for a linked poster and
      five otherwise, and that no CSS change to the grid's minimum column width is
      needed.

## 4. Tests

- [x] 4.1 Rewrite the accent case in `tests/Unit/Poster/PosterSearchTest.php`: it
      currently builds a poster literally named `Amélie.png`, which `plex-import`
      cannot produce. Use a sanitised filename (`Am_lie_2001_Movies.jpg`) plus a
      recorded Plex title, and assert the query "Amélie" matches it.
- [x] 4.2 Add search tests for the fallback (no Plex record → filename-derived
      title), and for the source library no longer being matchable.
- [x] 4.3 Add a test asserting search and sort are decided independently — the
      same posters, matched on recorded titles, still come back in the order the
      active sort dictates.
- [x] 4.4 Add import tests: a season records its show's title; a movie, show and
      collection record none; a skipped season gains a missing show title without
      a download and is still counted as skipped; a season whose show title
      already matches causes no write.
- [x] 4.5 Add a functional gallery test that the Related posters control renders
      for every category, carries the expected `href` with no year in the query,
      and that a season falls back to its own title when no show title is recorded.
- [x] 4.6 Update `tests/Functional/GalleryTest.php`, which pins Copy URL twice:
      the `data-action="copy"` markup assertion at line 599, and the action-label
      list at line 619. Replace both with the new control, and assert Copy URL is
      absent so the removal stays pinned.
- [x] 4.7 Fix `tests/Unit/Asset/PreviewApplyProgressTest.php:55`, which uses the
      literal `'copyUrl: function ('` as the *end boundary* when slicing
      `applyPreview()` out of `gallery.js`. It does not test Copy URL, so deleting
      `copyUrl()` breaks it for an unrelated reason — pick whichever function
      follows `applyPreview()` after the removal, or anchor the slice on something
      that is not about to be deleted.
- [x] 4.8 Check whether `DisabledStateTest` and the action-stack tests need
      updating for the changed control set, and keep the assertion that the stack
      count is unchanged.

## 5. Docs and gates

- [x] 5.1 Update `README.md:29`, which names **Copy URL** in the poster actions
      list, to name Related posters instead.
- [x] 5.2 State explicitly whether `docs/configuration.md`,
      `docs/development-workflow.md`, `docs/docker.md` and `docs/testing.md` are
      affected. No setting, environment variable, or toolchain step changes, so
      the expected answer is no — but the check is made, not assumed.
- [x] 5.3 Run `composer test`, `composer stan` and `composer cs`; all three must
      pass before any commit.
- [ ] 5.4 Verify by hand in the `:dev` image: Related posters from a season
      gathers the show and its siblings; from a movie it gathers the trilogy and
      the collection poster; an accented title is findable by name; the action
      works with JavaScript disabled; and the phone action sheet shows the new
      control and closes on activation.
