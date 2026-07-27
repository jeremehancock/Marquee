## Context

Gallery pagination is rendered by
`templates/partials/gallery_results.html.twig`, driven by a `Page` value object
(`src/Poster/Page.php`) that already exposes `page`, `totalPages()`,
`hasPrevious()`, `hasNext()`, and the item-range helpers. Today the template
renders only a Previous link, a "Page X of Y" status span, and a Next link.
Navigation is progressively enhanced: `public/assets/gallery.js` delegates
clicks on `.pagination a` and loads the link's `href` without a full reload, so
any anchor placed inside `.pagination` participates automatically.

The back-to-gallery link is a static label in `templates/plex.html.twig` and
`templates/orphans.html.twig`, pointing at a `back_url` the controller already
supplies.

## Goals / Non-Goals

**Goals:**
- Render first/last/prev/next controls plus a windowed, ellipsized run of page
  numbers, keeping the rendered number count bounded for any total.
- Preserve the existing query/sort-preservation behavior on every link.
- Reuse the existing no-reload click delegation without JS changes.
- Rename "Back to library" to "Back to gallery".

**Non-Goals:**
- Changing the pagination data model, page size, or clamping behavior.
- Client-side page-number computation or new JS behavior.
- Restyling the gallery beyond what the new number list needs.

## Decisions

**Compute the page window in PHP, not Twig.** Add a method to `Page` — e.g.
`paginationWindow(int $edge = 1, int $around = 1): array` — that returns an
ordered list of tokens: integer page numbers plus an ellipsis marker (a
sentinel such as the string `'…'` or `null`) for each collapsed gap. The
template iterates the tokens, rendering a link for an int, the active marker
when the int equals the current page, and a plain `…` span otherwise. Rationale:
the windowing is index arithmetic with edge cases (near start, near end, gap of
exactly one page that should show the number rather than an ellipsis) — that
belongs in a unit-testable value object, per the project's "no logic in
templates" convention, not in Twig loops.

**Window shape:** always show the first `edge` and last `edge` pages, plus
`around` pages either side of the current page; collapse any gap of 2+ pages
into a single ellipsis, but render the actual number when a gap is exactly one
page (so `1 … 3` never appears where `1 2 3` fits). Defaults chosen to match the
`< 1 2 3 … 82 >` example from the request.

**Keep query/sort preservation in the template.** The existing `q` string
(built from `query` and `sort`) is already assembled once in the template;
first/last/number links reuse it exactly like Previous/Next do. No controller
change needed.

**No JS change.** First/last/number links are ordinary `.pagination a` anchors,
so the existing delegated handler drives them. The active current-page marker is
a non-anchor element so it is inert.

## Risks / Trade-offs

- [Off-by-one / duplicate numbers in the window at boundaries] → Cover with unit
  tests on `Page::paginationWindow()` across representative totals and current
  pages (page 1, mid, last, small totals with no ellipsis, single-page).
- [Active current page accidentally rendered as a clickable link] → Template
  branches on the token equalling `page` and emits a non-anchor; assert in a
  template/render test that the current number is not an `<a>`.
- [Ellipsis rendered where a single omitted page could be shown] → Windowing
  rule emits the number for gaps of exactly one; covered by unit test.

## Migration Plan

Pure presentation change, no data or config migration. Deploy with the image;
rollback is reverting the template, `Page` method, and label edits. Verify the
Docker image builds and smoke-test the gallery per project convention before
pushing.
