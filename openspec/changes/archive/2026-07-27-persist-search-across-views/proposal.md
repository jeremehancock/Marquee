## Why

A search entered in one view (e.g. **All**) is silently discarded the moment the
user switches to another view (**Movies**, **TV Shows**, etc.), because the
category tabs are plain links to `/library/<view>` that carry no query. A user
who searches "matrix" in All and then taps Movies to narrow it down instead gets
the full, unfiltered Movies grid — the opposite of what they intended — with no
indication their search was dropped.

## What Changes

- Switching views (tabs) SHALL keep the active search query, so the new view is
  shown filtered by the same query rather than reset to its full list.
- View switches SHALL preserve the query without a full page reload, consistent
  with how live search and pagination already behave, and SHALL update the URL
  so the filtered view is shareable and survives back/forward.
- The gallery SHALL make it visually clear that the grid is a *filtered* view of
  the current category — not just via the populated search box, but with an
  explicit result summary (query + match count for the current view) and an
  obvious way to clear back to the full list.
- When a query yields no matches in the newly selected view, the empty state
  SHALL still make clear the view is filtered (not empty), so the user
  understands why the grid is empty and how to clear it.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `search`: extends "search preserves category and pagination" so the query is
  also preserved when switching between category views, and adds a requirement
  that the filtered state is clearly communicated to the user.

## Impact

- **Templates**: `templates/gallery.html.twig` — tab links must carry the
  current query; add a result-summary / filtered-state indicator in the toolbar
  or results header.
- **Frontend**: `public/assets/gallery.js` — intercept tab clicks to preserve
  the query and load via the existing AJAX path; keep tab hrefs / active state
  and the summary in sync as the query changes.
- **Controller/template data**: `src/Controller/GalleryController.php` — tab
  descriptors and/or template already expose `query` and result totals; may need
  to surface the current view's result count for the summary. Server-side
  `?q=` handling already works for every category, so no routing changes.
- No database, API, or configuration changes.
