## 1. Look at the real library first

- [x] 1.1 Extend `bin/diagnose-sets.php` (read-only) to report the shapes this
      design rests on, against the live library: how many rows in each category
      have no `year`; whether collections carry a `year` at all; how many seasons
      have no `season_number`; the distribution of collections-per-film (how many
      films are in 2, 3, 4+); and how many sets have no naming row in
      `plex_items`. Run it and record the output in the change notes.
- [x] 1.2 Confirm against 1.1 that unknown-year-first and the origin-poster-only
      "also in" list still hold. If a shape contradicts either, adjust the
      relevant spec requirement before writing code rather than working around it.
- [x] 1.3 Add `bin/bench-gallery.php` (read-only, in the shape of
      `diagnose-sets.php`): times and counts the per-category recorded-fact reads
      and a full `browseAll()` render for the unfiltered All view, a filtered All
      view, and a set view, over several iterations. Run it on the current code
      and record the baseline numbers.

## 2. One read per category

- [x] 2.1 Add a per-poster facts value object (recorded title, year, season
      number, related title, set keys, added-at, whether the row exists) and a
      per-view index keyed by category then filename, with accessors the template
      can call.
- [x] 2.2 Add one `PlexItemRepository` method returning that index for a category
      in a single query, encoding absence in the value object rather than by
      omitting map keys.
- [x] 2.3 Have `GalleryController` build the index once per render and pass it to
      `PosterLibrary`, replacing the `array $addedAt` parameter on `browse()`,
      `browseAll()` and `paginate()` and removing the two lazy repository reads
      inside `paginate()`.
- [x] 2.4 Move the template off `plex_titles` / `plex_years` / `related_titles` /
      `set_keys` / `linked` onto the single index, keeping the Plex-not-configured
      gate so every poster still reads as unlinked then.
- [x] 2.5 Delete `titlesForCategory`, `yearsForCategory`, `filenamesForCategory`,
      `relatedTitlesForCategory`, `setKeysForCategory` and `addedAtForCategory`
      once nothing calls them, or keep only those with callers outside the gallery
      render.
- [x] 2.6 Tests: assert the read count per render for the All view, for a filtered
      All view, for a set view and under a date sort. Assert **both directions**
      of each fallback — a poster with and without a recorded title, with and
      without a year, with and without a set, with and without an added-at
      timestamp, and with Plex configured and not.
- [x] 2.7 Re-run `bin/bench-gallery.php` and record the after numbers beside the
      baseline. If the combined read is not faster, say so in the change notes and
      keep the change for the read-count and drift-proofing reasons.

## 3. The Release sort field

- [x] 3.1 Add `SortField::Release` with its default direction (ascending), phrase,
      glyph name and label, and `SortOrder::Release` / `ReleaseDesc` with slugs
      `release` and `release_desc`, direction phrases that cannot be mistaken for
      the date field's ("earliest first" / "latest first"), and their labels.
- [x] 3.2 Replace `SortField::other()` with a list of the other fields, and change
      `SortState` to hold a remembered order per field rather than one
      `alternate`; `buttons()` renders one button per field in a fixed order.
- [x] 3.3 Update `SortPreference::resolve()` for a per-field remembered direction
      across three fields.
- [x] 3.4 Add the release comparison to `SortComparator`: year (unknown first),
      then season number (none first), then category order, then the
      article-aware sort key; direction reverses the year alone.
- [x] 3.5 Draw the release field's glyph, distinct from `sort-title` and
      `sort-date`.
- [x] 3.6 Fit a third button into the desktop sort group at the narrow end of the
      pointer breakpoint, and check the phone sort tray's third row.
- [x] 3.7 Tests: the six orders and their labels; unknown year first in both
      directions; a show ordered before its seasons and seasons in number order
      under both directions; per-field remembered directions across three fields;
      `DEFAULT_SORT` accepting `release`; the settings screen offering it.
      Update `SortPreferenceTest`'s two-button assertion and `SortOrderTest`'s
      four-order assertions.

## 4. A set is ordered by the active sort

Built first as "a set opens in release order by default", and withdrawn during
validation: the sort control is global, and a view that reinterprets it makes the
toolbar change on its own. Both rungs below were removed again.

- [x] 4.1 ~~Resolve the effective sort with the set's rung~~ — withdrawn. A set
      resolves the sort exactly as every other view does.
- [x] 4.2 ~~Suppress the session write while a set is being shown~~ — withdrawn.
      One rule for the control everywhere; an order chosen inside a set is
      remembered like any other.
- [x] 4.3 Tests: a set follows the active order in both directions; the sort
      control's displayed state is byte-identical either side of opening a set
      (hrefs excluded — those carry the set on purpose); an order chosen inside a
      set is remembered afterwards.

## 5. A set persists like a query

- [x] 5.1 Make `categoryUrl()` in `gallery.js` read the active set from the
      current address rather than from a passed argument, so the tab tap, the
      swipe commit and pagination all carry it.
- [x] 5.2 Carry the set on the `a[data-sort]` branch, omitting `q` (a set and a
      query are never both applied).
- [x] 5.3 Drop the set when a query is typed, and leave the clear control's
      behaviour as it is (a bare pathname already clears).
- [x] 5.4 Stamp the active set into the neighbour cache alongside the query, and
      compare it in `cachedView()`.
- [x] 5.5 Carry the set on the no-script paths: the sort links and the tab links
      rendered in the templates.
- [x] 5.6 Tests: a set survives a tab switch, a sort change and paging; a set is
      dropped by typing and by clearing; a held neighbour copy fetched without the
      set is not shown once a set is active, and — the other direction — one
      fetched under the same set still is; a view holding none of the set renders
      the filtered empty state.

## 6. Naming a set, including the others

- [x] 6.1 Add the `plex_sets` table (rating key, title) through the existing
      idempotent migration, with upsert and lookup on the repository.
- [x] 6.2 Record the name in `ImportService`: beside the existing
      `fillMissingSetKey()` call in the collection walk, and for each show in the
      season branch. No new request.
- [x] 6.3 Resolve a set's display name from `plex_items` first, then `plex_sets`,
      then describe it without a name.
- [x] 6.4 Add the origin poster to the Related posters link and to the set
      address, as an optional parameter that decides nothing about membership.
- [x] 6.5 Render the "also in" line on the set view: the origin poster's other
      sets, each a link carrying the same origin poster, named where known and
      described where not.
- [x] 6.6 Tests, both directions each: a film in two collections names the other
      and a film in one names nothing; following the link names the first back; a
      collection with no imported poster is named from `plex_sets` and one with no
      recorded name is described; an origin poster that no longer exists changes
      neither the members nor the absence of an error; a renamed collection is
      corrected on import and an unreadable name does not fail it.

## 7. A set may be offered a broader search

- [x] 7.1 Extend the broader-search offer to a set: candidates from the origin
      poster's related title and its shorter forms, run over the unfiltered
      listing, offered only when the best count exceeds the set's own total.
- [x] 7.2 Render the set's offer with wording of its own — what the collection may
      be missing — carrying its count, never applied on its own.
- [x] 7.3 Tests: an incomplete "Jackass" collection is offered its series with the
      count; a complete set, a show's set, a franchise collection whose members
      share no words, and a set opened with no origin poster are each offered
      nothing; the set shown is unchanged in every case.

## 8. Docs and gates

- [x] 8.1 Update `docs/configuration.md` for the `DEFAULT_SORT` values, and check
      `README.md` and `CLAUDE.md` for anything the change makes stale — including
      the Related posters notes. If nothing user-facing changed in a file, say so
      rather than inventing an edit.
- [x] 8.2 Run `composer test`, `composer stan`, `composer cs` and fix everything
      they report.
- [ ] 8.3 Validate by hand against the real library: a trilogy and a show's set in
      release order; a film in two collections naming the other and hopping
      between them; a tab switch and a sort change inside a set; an incomplete
      collection being offered its series; and the before/after benchmark numbers
      from 1.3 and 2.7.
