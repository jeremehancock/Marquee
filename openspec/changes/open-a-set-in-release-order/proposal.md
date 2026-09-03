## Why

Related posters now opens the **set** a poster belongs to — a show with its
seasons, a film with the rest of its Plex collection. Opening a set is a
different act from filtering a list, and the gallery's list behaviours do not
suit it:

- **A set reads in the wrong order.** A show's seasons sort *before* the show
  itself — the posters are stored with their library appended, so "Breaking Bad -
  Season 1 TV" is compared against "Breaking Bad TV" and `-` sorts below `T`. And
  a series runs out of order wherever its titles do not happen to agree with its
  release dates: A–Z puts *The Matrix Resurrections* (2021) ahead of *The Matrix
  Revolutions* (2003). A set has a natural order — the order it was released —
  and every fact needed to produce it is already recorded.
- **A film in two collections links to only one.** *Godzilla vs. Kong* is in both
  King Kong and MonsterVerse. The set *view* is already correct — it matches on
  membership — but the card's link takes the first key and gives no sign the
  other exists.
- **An incomplete collection is silently narrow.** A user who forgot to add
  *Jackass 2.5* sees eight films and nothing suggesting a ninth. The
  broader-search offer already solves this shape for a typed search; a set is
  never offered one.
- **The feature behaves differently depending on what it finds.** When Related
  posters finds a set, changing tab or sort *loses* it; when it falls back to a
  title search, the search survives both. One action, two behaviours, decided by
  something the user cannot see.

Underneath, rendering a gallery does five full-table reads per category — twenty
queries in the All view, ~159 ms at ~3900 posters. Two of the five arrived with
Related posters, so this is the change that owes the cleanup.

## What Changes

- **A new gallery sort field, Release**, in both directions, ordering by the
  release year already recorded on every row and by season number within a year,
  so a show precedes its seasons and its seasons read 1, 2, 3.
- **A set opens in release order by default**, without recording that as the
  user's sort preference. The sort control stays live inside a set: it now says
  *Release*, and choosing another field re-orders the set rather than dropping
  out of it.
- **A set survives a view switch and a sort change**, exactly as an active search
  does, so Related posters behaves the same whether it finds a set or falls back
  to a search. **BREAKING (behavioural):** changing sort or tab inside a set no
  longer clears it.
- **The set a poster was opened from is carried in the address**, so a set view
  can name the *other* sets that poster belongs to — "Godzilla vs. Kong is also
  in MonsterVerse", as a link.
- **Set names are recorded from Plex** in their own small table, so a set can be
  named on screen even when the collection's or show's own poster was never
  imported. Today such a set reads "in this set".
- **A set may be offered a broader search**, on the same terms as a narrow
  typed search: only when a shorter query would find *more* posters than the set
  holds, and only as an offer with its count. **MODIFIED:** the existing rule
  that an exact set is offered nothing is replaced — membership being exact does
  not make a collection complete.
- **One read per category replaces five.** The title, year, season number,
  related title, set keys, and added-at timestamp for a category come back from a
  single query, and the gallery's read path is measured before and after rather
  than assumed improved.

## Capabilities

### New Capabilities

None. Every behaviour here belongs to a capability that already exists.

### Modified Capabilities

- `poster-library`: a Release sort field alongside Alphabetical and Date added;
  a set's default order and the fact that it is a default rather than an
  override; a set surviving a view switch and a sort change; a set naming the
  other sets its origin poster belongs to; the gallery reading each category's
  recorded facts once per render.
- `search`: whether "Search filters without reordering" governs a set (it does
  not, and the spec now says so); a set may be offered a broader search, replacing
  the rule that an exact set is offered nothing.
- `plex-import`: recording each set's name as its membership is walked, so a set
  can be named without its own poster having been imported.

## Impact

**Code**

- `src/Poster/SortField.php`, `SortOrder.php`, `SortComparator.php` — a third
  field, its directions, glyph, phrasing, and comparison. `SortField::other()`
  becomes a list, so `src/Support/SortState.php` and `SortPreference.php` carry a
  remembered direction per field rather than one alternate.
- `src/Database/PlexItemRepository.php` — one combined per-category read replacing
  five; a `plex_sets` name lookup. `src/Database/Database.php` — one new table
  through the existing idempotent migration.
- `src/Poster/PosterLibrary.php` — takes the recorded facts rather than reading
  them again inside `paginate()`; the broader-search offer extended to a set.
- `src/Controller/GalleryController.php` — reads facts once, resolves the set's
  default sort, passes the origin poster through.
- `src/Plex/Import/ImportService.php` — records a set's name beside the
  membership it already walks.
- `templates/partials/gallery_results.html.twig`, `_sort.html.twig`,
  `gallery.html.twig` — the set summary's "also in" links and broader offer, a
  third sort button, the set carried on the no-script sort and tab links.
- `public/assets/gallery.js` — `categoryUrl()` carries the active set; the sort
  branch carries it too; the neighbour cache's staleness comparison gains it.
- `public/assets/app.css` — room for a third sort button.
- `bin/` — a read-only benchmark script for the gallery read path, and the
  existing `diagnose-sets.php` extended to report the library shapes this design
  depends on.

**Docs**

- `docs/configuration.md` — `DEFAULT_SORT` gains two accepted values.
- `README.md` — the sort orders offered, if listed.

**Data**

- One new table, additive, created by the existing migration. Set names fill in
  on the next ordinary import through the walk that already runs; until then a
  set is named exactly as well as it is today. Rollback to the previous image is
  unaffected — the table is inert to older code.

**Tests**

- `SortPreferenceTest` asserts two sort buttons and `SortOrderTest` asserts four
  orders; both move. Every new rule is asserted in **both** directions — a set
  that gains a member and one that loses it, a poster whose other set appears and
  one where it must not, an offer that is made and one that is suppressed.
