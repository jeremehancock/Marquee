## Context

The gallery ([templates/gallery.html.twig](../../../templates/gallery.html.twig))
renders a tab strip (All / Movies / TV Shows / TV Seasons / Collections), a
search box, sort controls, and a `#results` grid. Live search and pagination are
already AJAX: [public/assets/gallery.js](../../../public/assets/gallery.js)
intercepts search input and `.pagination a` clicks, calls `load(url, push)` to
fetch the page, swaps `#results`, and `history.pushState`s the new URL. The
server ([src/Controller/GalleryController.php](../../../src/Controller/GalleryController.php))
already honours `?q=` for every view, so a filtered request to any view works
today.

The gap is purely in the client/template: the tab links are plain
`<a href="/library/<view>">` with no query, and tab clicks are **not**
intercepted — they trigger a full-page navigation to the bare view, dropping the
`?q=` entirely. So a user who searches in All and taps Movies lands on the
unfiltered Movies grid.

Two things are needed: (1) preserve the query across view switches, and (2) make
the filtered state legible so the user understands they are looking at a subset.

## Goals / Non-Goals

**Goals:**
- Keep the active query when switching views, via the existing AJAX `load()`
  path (no full reload), with the URL updated for shareability and back/forward.
- Communicate the filtered state clearly: a populated search box plus an explicit
  result summary (query + match count for the current view) and a clear control.
- Keep tab active-state, tab hrefs, and the summary consistent as the query
  changes through live typing.
- No server routing, database, config, or API changes.

**Non-Goals:**
- Changing how matching works (normalization, ranking, article-awareness) — out
  of scope; the `search` matching requirements are untouched.
- Persisting a query across full page reloads or browser sessions beyond what the
  URL already provides.
- Cross-view result counts or a global "results everywhere" view — the summary
  describes only the currently selected view.

## Decisions

### Decision: Intercept tab clicks and reuse the AJAX `load()` path
Add a delegated click handler for `.tabs a` in `gallery.js` (alongside the
existing `.pagination a` handler). On click: prevent default, read the current
query from the search input, build `<tab-base>` + `?q=<query>` (query omitted
when empty, matching the live-search URL format), call `load(url, true)`, and
update the active-tab class locally.

*Why:* The AJAX path, URL-sync, and `popstate` handling already exist and are
proven for search and pagination; tab switching is the same operation with a
different base. Reusing it keeps behavior consistent and avoids a full reload.

*Alternative considered — server-side only (render tab hrefs with the current
query in Twig):* Simplest for a first paint, but the hrefs go stale as soon as
the user types (live search changes the query via pushState without
re-rendering the tabs), so tabs would carry an outdated query. Rejected as the
sole mechanism. We still set query-aware hrefs server-side as a
progressive-enhancement fallback (works without JS), but the JS handler reads the
live input value as the source of truth.

### Decision: Result summary lives in `#results`, rendered server-side
Render the filtered-state summary inside the `#results` partial
([templates/partials/gallery_results.html.twig](../../../templates/partials/gallery_results.html.twig)),
so it is produced by the same request that produces the grid and is swapped in
automatically by the existing `setResults()` — no extra client bookkeeping to
keep it in sync. The summary shows the active query and the current view's match
count (already available from the paginated result/total), plus a Clear control.

*Why:* `load()` replaces only `#results`. Putting the summary there means every
view switch and every keystroke refreshes it for free. Putting it in the static
toolbar would require separate JS updates and risk drift.

*Alternative considered — Alpine-driven summary bound to a JS query variable:*
More moving parts and a second source of truth for the count; the server already
knows the exact filtered total, so let it render the text.

### Decision: Keep the search box in the static toolbar (unchanged position)
The search `<input>` stays where it is; its `value` is already populated from
`query` server-side and preserved client-side across `load()` calls (only
`#results` is swapped, not the toolbar). This is the primary persistence cue; the
in-results summary is the reinforcing cue.

## Risks / Trade-offs

- [Tab hrefs drift from the live query if JS is disabled or fails] → Server-side
  query-aware hrefs give a correct filtered result on click even without JS; the
  JS handler simply upgrades this to no-reload and always uses the live input
  value, so the two never disagree in practice.
- [Match count in the summary could confuse across views ("3 in Movies" vs a
  larger All count)] → Scope the summary text explicitly to the current view and
  update it on every switch; do not imply a global total.
- [Empty filtered view mistaken for an empty library] → The filtered empty state
  reuses the summary (query + zero matches) and Clear control, distinguishing
  "no matches for this query" from "this view has no posters".
- [Extra delegated handler on the gallery root] → Minimal; mirrors the existing
  pagination handler and adds no new dependencies.
