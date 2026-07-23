## Context

The gallery is built around one category per page. `GalleryController::show()`
resolves a `PosterCategory` from the URL slug, `PosterLibrary::browse()` lists a
single category's directory, sorts, and paginates, and the template computes one
page-level `base = /library/<category>` that every card action, the live search,
and the change-poster modal hang off of. `PosterCategory` is a four-case enum
whose value is both the URL slug and the on-disk directory name.

This change introduces an aggregate "All" view over the four categories without
disturbing that per-category machinery. The main tension is that "All" is not a
real category (no directory) yet must slot into the same routing, tab strip, and
controller flow — and that in a mixed view, a poster's filename is no longer a
unique key, so actions must be keyed on (category, filename).

## Goals / Non-Goals

**Goals:**
- Add an All view that merges the four categories into one mixed-alphabetical,
  paginated grid, as the default landing view and first tab.
- Show a per-poster type badge only in the All view; hide it on hover on pointer
  devices, keep it on touch.
- Make every poster action in the All view act on the poster's own category.
- Fit five tabs on a phone without overflow via a horizontal scroll-snap row.
- Keep the four single-category views behaving exactly as today.

**Non-Goals:**
- No change to storage layout, the `PosterCategory` enum's four real cases, the
  database, or any Plex/import/export behavior.
- No grouped-by-type layout, no per-type filtering within All, no new sort
  configuration.
- No badges in single-category views.
- No change to how an individual poster is edited (poster-editing behavior is
  unchanged; only which category a given card targets in the All view).

## Decisions

### Model "All" as a pseudo-slug, not an enum case
`/library/all` is handled by branching in the controller on the `all` slug; the
`PosterCategory` enum keeps exactly its four directory-backed cases. Rationale:
the enum's value doubles as a real directory name, and inventing a fifth case
with no directory would leak a special case into every `match` and every storage
call. Keeping All out of the enum confines the special case to the controller and
the tab view-model.

- **Alternative — fifth enum case `All`:** rejected. It would force `directory()`,
  `FilesystemPosterStorage`, and every exhaustive `match` to special-case a
  category that has no files, spreading the exception widely.
- **Alternative — separate route `/all` with its own handler:** rejected in favor
  of one `/library/{category}` URL family so the tab loop stays a uniform
  `href=/library/<value>` and `show()` remains the single gallery entry point.

### Tab strip becomes a small view-model
The template currently iterates `PosterCategory::all()`. It will instead iterate a
small ordered list of `{ value, label, active }` tab descriptors — All first, then
the four categories — built by the controller (or a tiny helper). This keeps the
"All is not a category" fact out of the template while letting the active-tab
comparison stay a simple value match.

### Aggregate browse merges, tags, sorts, then paginates
Add an aggregate path (e.g. `PosterLibrary::browseAll()`) that lists all four
category directories, concatenates the `Poster` objects (each already carries its
own `category`), applies the existing article-aware sort with a category tiebreak,
then paginates the combined list with the same `perPage`/clamping logic as
`browse()`. Since each `Poster` already knows its category, no tagging step is
needed beyond the merge. Search, when a query is present, filters the merged set
the same way single-category browse does.

- The tiebreak on equal sort titles is category order Movies → TV Shows →
  TV Seasons → Collections, giving a stable deterministic order.

### Cards and the change modal carry the poster's own category
The results partial currently uses one page-level `base`. In the All view each
card must emit its own category so its action forms post to
`/library/<posterCategory>/…` and the change event carries the category. Concretely:
- Card action forms build their action URL from `poster.category.value`, not the
  page base. In single-category views this equals the page category, so behavior
  is unchanged.
- The change trigger (`data-action="change"`) gains a `data-category`; the
  `gallery:change` event carries it; the Alpine modal builds its post base from
  the event's category instead of the page-level `this.base`.
- The `linked` set becomes category-aware: instead of `filenamesForCategory(one)`
  the controller gathers linked filenames per category (a map keyed by category)
  so "is this poster linked?" is answered by (category, filename).

### Badge rendering and hover-hide reuse the existing overlay mechanics
The badge is a small element inside `.card__frame`, rendered only when the active
view is All (one template flag). Hover-hide mirrors the existing reveal rule:
inside `@media (hover: hover)`, `.card__frame:hover .card__badge` fades to
`opacity: 0` exactly as `.card__overlay` fades in. Under `@media (hover: none)`
the overlay is already hidden, so the badge simply persists — no extra rule
needed. The mobile action sheet clones `.card__actions` (which already lives per
card), so category-correct forms carry into the sheet automatically.

### Default landing redirect
`GalleryController::home()` redirects `/` to `/library/all` instead of
`/library/<default>`. The `PosterCategory::default()` helper stays for any code
that still needs a concrete default category, but the site root now lands on All.

## Risks / Trade-offs

- **[Filename collisions across categories cause a wrong-target action]** → Every
  action in the All view is keyed on (category, filename) and posts to the
  poster's own category endpoint; the `linked` lookup is category-scoped. Covered
  by a spec scenario for same-filename posters in different categories.
- **[Four `scandir` calls per All page render increase I/O]** → The All view lists
  four directories instead of one on each render. For a self-hosted library this
  is a bounded, local-disk cost comparable to the existing per-category listing;
  pagination still slices after the merge. If it ever matters, listing is the
  natural place to cache later. Not optimized now.
- **[Mobile scroll-snap can leave the active tab off-screen]** → The active tab
  should be scrolled into view on load and an edge fade should hint at more tabs,
  so users can tell the row scrolls. Acceptable trade for never overflowing.
- **[Badge could obscure poster art or the overlay]** → Badge is small, corner-
  anchored, and hides on hover on pointer devices where the overlay appears; it
  only persists on touch where there is no overlay.
- **[Template `base` assumption is load-bearing and easy to miss]** → The change
  from page-level `base` to per-poster category touches the partial, the change
  event, and the modal together; all three must move or same-filename actions in
  All silently target the wrong category. Called out explicitly in tasks.

## Migration Plan

Purely additive UI/routing change with no data migration. Deploys as a normal
release. Rollback is reverting the change; no persisted state is affected. The
only user-visible default shift is that `/` now lands on All instead of Movies;
bookmarks to `/library/<category>` continue to work unchanged.

## Open Questions

None outstanding — routing model, sort/tiebreak, badge behavior, default view,
and per-poster action keying were settled during exploration. Badge visual form
(icon vs. text vs. both) and the exact scroll-snap affordance are left to
implementation within the spec's constraints.
