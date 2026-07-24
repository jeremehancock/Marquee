## Context

The gallery today sorts posters one way only: article-aware title order,
computed in `PosterLibrary::paginate()` over a flat `list<Poster>`. A `Poster`
is a thin view of an image file on disk (category, filename, size,
`modifiedAt`); it knows nothing about Plex. The Plex link lives separately in
the `plex_items` SQLite table (rating key → filename, plus section, title,
thumb, `updated_at`), populated by `ImportService`. Plex's own "added at"
timestamp is neither parsed by `HttpPlexClient` nor stored.

To order by "date added to Plex" we therefore need a Plex-sourced timestamp
per poster, a way to feed it into the sort, a configurable default, and a UI
toggle. Not every poster is Plex-mapped in principle (the mapping row may be
missing for items imported before this change), so the sort needs a fallback.

## Goals / Non-Goals

**Goals:**
- Add a Date-added sort order (newest first) for single categories and `all`.
- Add a `DEFAULT_SORT` env var (`alphabetical` default, `date_added` opt-in).
- Add a toolbar toggle whose choice persists across navigation, overriding the
  configured default for the session.
- Capture and store Plex `addedAt` during import.
- Keep Alphabetical behavior byte-for-byte identical when nothing opts in.

**Non-Goals:**
- Changing search: search results stay relevance-ranked and ignore the toggle.
- A per-category remembered sort (one session-wide sort selection is enough).
- Backfilling `added_at` for already-imported items beyond what the next
  import naturally refreshes; the file-mtime fallback covers them meanwhile.
- User-configurable sort direction (date-added is fixed to newest-first).

## Decisions

### 1. Model sort order as a `SortOrder` enum
A `SortOrder` enum (`Alphabetical`, `DateAdded`) with `fromEnv`-style parsing
and a URL/session slug (`alpha` / `date_added`). This keeps the two orders
named in one place and mirrors the existing `PosterCategory` / `GalleryView`
value-object style. Alternative — passing raw strings around — was rejected as
untyped and easy to typo against PHPStan-max.

### 2. Store `added_at` on `plex_items`; parse `addedAt` in the client
Add an integer `added_at` column via the existing idempotent `ensureColumn`
migration (defaults to 0, like `thumb`/`section_key` were added). Thread it
through `PlexItem`, `PlexItemRecord`, and the `ImportService` upsert.
`HttpPlexClient::item()` and `seasons()` read the `addedAt` XML attribute (a
Unix epoch integer in Plex's API) via the existing `attr()` helper.
Alternative — a separate table — is overkill; the value belongs 1:1 with the
existing mapping row.

### 3. Resolve the sort→timestamp join in the controller, not the library
`PosterLibrary` deliberately has no database dependency; it operates on
`Poster` objects. `GalleryController` already depends on `PlexItemRepository`.
So the controller resolves the effective `SortOrder`, and for date-added it
asks the repository for an `(category, filename) → added_at` map covering the
view's categories, then passes both into `PosterLibrary::browse/browseAll`.
The library sorts: date-added by `map[cat][filename] ?? poster.modifiedAt`
descending (fallback keeps every poster placed), alphabetical unchanged.
Alternative — injecting the repo into `PosterLibrary` — would break its clean
"posters on disk" boundary and its unit tests. A new repository method returns
the lookup (e.g. `addedAtForCategory(string): array<string,int>`).

### 4. Effective sort = session override ?? DEFAULT_SORT
`DEFAULT_SORT` is read once into `PosterConfig` (alongside
`ignoreArticlesInSort`). The toggle writes the chosen `SortOrder` slug to the
session under a single key, reusing the established `LastCategory`/session
pattern. The controller reads: session value if present and valid, else the
config default. A `?sort=` query param drives the toggle links and, when
present, updates the session so the choice sticks — matching how the app
already threads `q` and `page` through links.

### 5. Toolbar toggle + pagination carry the sort
The toggle renders in the existing `.toolbar` next to search as two links (or a
segmented control) pointing at `{{ base }}?sort=alpha` / `?sort=date_added`,
preserving any active `q`. Pagination links in `gallery_results.html.twig`
already append `q`; they gain the `sort` param the same way so paging keeps the
order. Because Alphabetical is the app default, links for the default order can
omit the param to keep URLs clean.

## Risks / Trade-offs

- **Stale `added_at` for pre-change imports** → those rows have `added_at = 0`;
  the fallback orders them by file mtime so they remain sensibly placed, and a
  normal (non-forced) re-import backfills real values as `skip-unchanged` still
  upserts the mapping. No forced re-import is required.
- **Plex omits `addedAt` on some item types (e.g. seasons/collections)** → the
  fallback to file mtime keeps them ordered; the spec explicitly allows a
  missing timestamp without failing import.
- **All-view timestamp lookup cost** → building the `(cat,filename)→added_at`
  map for date-added sort adds one small query per category (≤4). Negligible
  next to reading the poster directories already done each request.
- **Session-wide (not per-category) sort might surprise a user** → acceptable
  and matches the "preferred sort" framing; the toggle always shows the current
  order so it is never hidden.

## Migration Plan

1. Ship the `added_at` column migration (idempotent, runs on boot).
2. Deploy; existing installs default to Alphabetical unchanged.
3. Date-added ordering works immediately using mtime fallback; real Plex
   timestamps populate on the next import. Rollback is safe — the extra column
   is ignored by the prior version and the SQLite file is disposable.

## Open Questions

- None blocking. Direction is fixed to newest-first per the original app's
  behavior; if a "oldest first" is wanted later it is an additive follow-up.
