## Why

The gallery offers two sort orders but only one direction each: titles always
run A–Z and date added always runs newest-first. There is no way to see the
oldest imports or to scan titles from the end, and the sort buttons give no
visual cue about which way the list is running. Users also expect the sort
control to keep working while a search is active, where today it is silently
ignored.

## What Changes

- Each sort button becomes a toggle. Tapping the active button reverses its
  direction; tapping the inactive one switches to it.
- Four effective orders instead of two: A–Z, Z–A, date added newest-first, and
  date added oldest-first.
- Each button shows its direction — the title button's label swaps between
  `A–Z` and `Z–A`, and both buttons carry an arrow that points down for
  descending and up for ascending.
- Both buttons gain a leading glyph identifying the field: bars for title
  order, a calendar for date added.
- Direction is remembered per field for the session. Switching to date added
  and back returns to the title direction last used, not to A–Z.
- Search results honour the selected sort. Match position still leads the
  ranking, but the chosen order now breaks ties instead of a hardcoded
  ascending title key.
- `DEFAULT_SORT` is unchanged: `alphabetical` still means A–Z and `date_added`
  still means newest-first. Existing configuration, bookmarks, and live
  sessions keep working without migration.

## Capabilities

### New Capabilities

None. This extends existing gallery and search behavior.

### Modified Capabilities

- `poster-library`: the two sort orders become four, gaining a direction that
  the user can toggle and that persists per field for the session; the sort
  control gains per-field glyphs and a direction indicator.
- `search`: match-position ranking gains an explicit tiebreak rule — the active
  sort order breaks ties, replacing the fixed ascending title key.

## Impact

- `src/Poster/SortOrder.php` — two more cases plus field, direction, and flip
  accessors.
- `src/Support/SortPreference.php` — resolves to a sort state carrying the
  current order, its flip, and the other field's remembered order.
- `src/Poster/PosterLibrary.php`, `src/Poster/Search/PosterSearch.php` — both
  order results through one shared comparator keyed by the sort order.
- `src/Controller/GalleryController.php` — passes the sort state to the view.
- `templates/gallery.html.twig`, `templates/partials/gallery_results.html.twig`,
  `templates/partials/_icons.html.twig` — sort control extracted to a macro used
  by the toolbar and the phone tray, two new glyphs, and the URL-carry rule that
  currently hardcodes `date_added`.
- `public/assets/app.css` — arrow rotation and sort button layout.
- No change to `public/assets/gallery.js`: the target slug is rendered into
  `data-sort`, so the existing click handler navigates correctly as-is.
- No change to `DEFAULT_SORT` parsing, the session key's meaning for existing
  values, or the database.
