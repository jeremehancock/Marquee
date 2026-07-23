## Why

Today the gallery only ever shows one category at a time, so there is no way to
see the whole library at a glance or to browse across types. Users who just want
to scan everything they have must click through four tabs. Adding a single "All"
view — with a small type badge on each poster so mixed types stay legible — makes
the library browsable as a whole and becomes a more natural landing page than
Movies alone.

## What Changes

- Add an **All** view that merges all four categories (Movies, TV Shows, TV
  Seasons, Collections) into one flat, mixed-alphabetical grid.
- Make **All the default landing view and the first tab**. `/` now redirects to
  the All view instead of Movies.
- Give each poster **in the All view** a small **type badge** (Movie / TV Show /
  TV Season / Collection). The badge **hides on hover** on pointer devices so it
  never fights the action overlay, and **persists on touch** where there is no
  overlay. Badges appear **only** in the All view; single-category tabs are
  unchanged.
- Because filenames are not unique across categories, every poster action in the
  All view is keyed on **(category, filename)**: each card and the change-poster
  modal carry the poster's own category and act on its own category endpoints.
- Improve the **mobile tab strip** so five tabs no longer overflow or crowd the
  screen (a horizontal scroll-snap row rather than a wrapping grid).
- The remembered library section now records **All** when the All view is active,
  so returning from Orphans/Import lands back on All.

No breaking changes to existing category routes, storage layout, or the
poster-editing flow — the four category views behave exactly as before.

## Capabilities

### New Capabilities
<!-- None. This extends existing gallery browsing behavior. -->

### Modified Capabilities
- `poster-library`: adds an aggregate "All" view across the four categories as
  the default landing view and first tab; a mixed-alphabetical ordering with a
  category tiebreak; a per-category type badge shown only in the All view that
  hides on hover and persists on touch; keying poster actions on
  (category, filename) within the aggregate view; the remembered section
  covering All; and the mobile tab strip accommodating five tabs without
  overflow.

## Impact

- **Routing** (`src/Routes.php`): `/library/all` handled as an aggregate view;
  `/` redirect target changes to the All view.
- **Controller** (`src/Controller/GalleryController.php`): branch on the `all`
  slug to merge listings, gather the linked-poster set across all four
  categories, and pass a tab view-model plus an "is All view" flag to the
  template.
- **Domain** (`src/Poster/PosterLibrary.php`, `PosterCategory.php`): an aggregate
  browse path and a small tab/label helper; the `PosterCategory` enum stays four
  real, directory-backed cases (All is not an enum case).
- **Templates** (`templates/gallery.html.twig`,
  `partials/gallery_results.html.twig`): tab strip includes All; cards render a
  per-poster category and (in All) a badge; card actions and the change modal use
  each poster's own category base.
- **Front-end** (`public/assets/gallery.js`, `public/assets/app.css`): the change
  event carries the poster's category; badge styling with hover-hide; mobile tab
  scroll-snap.
- No database, Plex, storage-format, or configuration changes.
