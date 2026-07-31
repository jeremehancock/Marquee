## Context

The gallery already has the loading treatment this change spreads: a shimmer
drawn by `.card__frame::before`, a `.card__image` that ships at `opacity: 0`, and
`markLoaded`/`initImages` in `gallery.js` adding `is-loaded` on `load` or `error`
(treating a failure as resolved so a broken poster never shimmers forever). Cards
also carry native `loading="lazy"`, which works because the card frame reserves
the poster's space before the image arrives.

Three places render poster images outside that treatment:

- The Find Posters candidate grid (`templates/gallery.html.twig`) — an Alpine
  `x-for` over `finder.results`, each `<img class="find-item__img" :src=…>`. It
  already carries `loading="lazy"` and an `aspect-ratio: 2 / 3`, so space is
  reserved, but there is no placeholder and no fade: cells sit empty and images
  snap in.
- The shared full-screen viewer (`templates/partials/_overlays.html.twig`) — one
  `<img :src="viewer">` re-pointed at a different poster each time.
- The Find Posters preview (`viewer--finder`) — the same shape, plus an action
  bar under the image.

The two viewers differ from a card in the way that matters here: the element is
long-lived and its `src` changes, so a one-shot class added by DOM scanning is
wrong — once `is-loaded` is on the element it stays on for the next poster.

Constraints: no Node build step and no JS test runner in this repo; Alpine.js and
plain CSS only; the existing card rules and their spec wording must keep working
unchanged.

## Goals / Non-Goals

**Goals:**

- One placeholder/fade-in treatment, reused by cards, candidate cells, and both
  viewers, rather than three near-copies.
- The viewers start unresolved on every open, including the second and later
  opens.
- Candidate thumbnails are fetched as they approach the visible part of the
  results, not all at once.
- The Find Posters preview's action bar does not move when the image lands.

**Non-Goals:**

- Paginating or incrementally fetching Find Posters *results* — the search still
  returns its candidate list in one request; only images load progressively.
- Touching the poster-wall (`wall.js`/`wall.css`), which preloads deliberately
  and has its own timing.
- Any change to PHP services, routes, the database, or outbound HTTP.
- A JS test runner. Verification stays the repo's existing pairing: shape
  tripwires in `tests/Unit/Asset/` plus hand-checking the `:dev` image.

## Decisions

### Extend the existing rules by selector, don't invent a parallel system

The shimmer and fade rules in `app.css` grow additional selectors —
`.find-item__frame::before`, `.viewer__frame::before` next to `.card__frame`, and
`.find-item__img`, `.viewer img` next to `.card__image` for the opacity/`is-loaded`
pair. `is-loaded` stays the single shared "resolved" marker.

*Alternative considered:* rename to generic `.lazy-frame`/`.lazy-img` classes and
migrate the cards. Rejected — it churns the gallery template, the orphans
template, `initImages`, and the existing asset tests to gain nothing the grouped
selectors don't already give, on a change whose whole point is the parts that
*aren't* cards.

### Candidate cells get a frame element, mirroring `.card__frame`

Each `x-for` item becomes `<figure class="find-item"><div class="find-item__frame">
<img class="find-item__img" …></div></figure>`, with the `aspect-ratio: 2 / 3` and
border/radius moving from the image to the frame. The frame is what holds the
space and draws the shimmer; the image fills it. This is the card structure, so
the shimmer, the `:has(.is-loaded)` stop rule, and the fade all apply by adding
selectors rather than by writing new behaviour.

### Per-item Alpine state for the grid, not a DOM scan

`initImages` scans for `.card__image` once per render pass; candidate images are
created by Alpine at arbitrary times, so wiring them that way means re-running the
scan on every `x-for` update. Instead each cell owns its own flag:

```
<figure class="find-item" x-data="{ loaded: false }">
    <div class="find-item__frame">
        <img class="find-item__img" :src="poster.thumb" alt="" loading="lazy"
             :class="loaded ? 'is-loaded' : ''"
             @load="loaded = true" @error="loaded = true" …>
```

`@error` sets the same flag as `@load` — identical to `markLoaded`'s reasoning,
and stated in the spec: the placeholder stops either way. Alpine binds `:src` and
the listeners during the same initialization pass, and image `load` never fires
synchronously with the `src` assignment, so a cached thumbnail still triggers the
handler; no `complete` check is needed for elements Alpine created itself.

*Alternative considered:* a `MutationObserver` feeding `markLoaded`. Rejected as
more machinery for the same result, in a spot where Alpine already owns the
element's lifecycle.

### Viewer loaded-state lives in the Alpine component and resets on open

`overlayComponent()` in `gallery.js` gains `viewerLoaded: false`, and `view(url)`
sets it back to `false` alongside `this.viewer = url`. The finder preview gets the
same pair on its own state (`finder.previewLoaded`, reset in
`openFinderPreview`). Both images bind `:class="…Loaded ? 'is-loaded' : ''"` with
`@load`/`@error` handlers.

Resetting inside the open method — rather than watching `viewer` — is what makes
the "reopen on a different poster" scenario hold, and it also absorbs a quirk of
the current markup: closing sets `viewer = null`, which leaves `src=""` and can
fire a stray `error`. Whatever that does to the flag is discarded, because the
next open clears it before the new `src` is applied.

*Alternative considered:* keep using `markLoaded` on the viewer image via
`$watch`. Rejected — it would leave the class owned by imperative JS while Alpine
owns the `src`, exactly the split that produces a viewer showing a stale
`is-loaded`.

### The placeholder in a viewer is a sibling element, not a `::before` on a frame

A card's frame knows its size from the grid. A full-screen image does not: it is
sized by `max-width`/`max-height` against its natural dimensions, which are
unknown until it loads. So each viewer renders a placeholder element shown while
unresolved (`x-show="!viewerLoaded"`) at poster proportions, and the image fades in
over its own natural size once it resolves. Trading an exact size match for a
placeholder that is visibly *there* is the right way round: the alternative —
forcing every image into a 2:3 box so the placeholder matches — would letterbox
legitimately wide candidate artwork forever to smooth one transition.

### A flex stage pins the finder preview's action bar

`.viewer--finder` currently centres image and bar together as a column, so the
bar's position depends on the image's height — which is unknown while loading and
different for a non-2:3 candidate. Wrapping the image and its placeholder in a
`.viewer__stage` (`flex: 1 1 auto; min-height: 0`, centring its contents) makes
the stage claim all height above the bar, so the bar sits at a fixed position from
the moment the preview opens.

This replaces, rather than adds to, the existing `max-height: calc(100% - 132px)`
hack on `.viewer--finder img` — that rule exists to reserve room for the tallest
bar state, which the stage now does structurally. The plain viewer uses the same
stage so both share one set of rules.

### Native `loading="lazy"` stays the deferral mechanism

The candidate grid scrolls inside its own `max-height: 62vh; overflow-y: auto`
container. Native lazy loading is specified against viewport intersection computed
*through* clipping ancestors, so an image scrolled out of that container is not
intersecting and stays unfetched — which is exactly the spec's "relative to the
region the results actually scroll in". No hand-rolled `IntersectionObserver`.

*Alternative considered:* an observer rooted on `.find-grid`. Rejected — it
reimplements what the browser already does, and it would be the second scroll
observer in `gallery.js` after the phone infinite-scroll one, with no shared
purpose.

## Risks / Trade-offs

- **Native lazy loading in a nested scroller behaves differently than expected in
  some browser** → the fallback is the pre-change behaviour (everything fetched
  eagerly), which is not a regression; the placeholder and fade are independent of
  deferral and still work. Confirm on the `:dev` image with the network panel
  before archiving.
- **The `:has()` stop rule for the candidate shimmer** → same exposure the cards
  already accept: without `:has()` the animation keeps running behind an opaque
  image, invisible. No new risk, and the existing comment covers it.
- **The finder preview's layout changes shape** (stage + bar instead of a centred
  column) → the visible result should be identical at the common 2:3 aspect;
  check a wide candidate and the confirm step, where the bar grows a line, by
  hand on `:dev`.
- **A candidate whose image 404s now shows a stopped, empty frame** rather than a
  bare broken-image icon → deliberate, and the same trade the gallery already
  makes.
- **Per-candidate Alpine components** add one component per cell → candidate lists
  run to tens of items, not thousands; the grid already re-renders wholesale on
  each search.
- **No automated proof of the timing** → unavoidable without a JS runner. Tests
  are tripwires on the shape (shared selectors present, reset-on-open present);
  the behaviour is validated by hand, as `GalleryLoadingIndicationTest` documents
  for the existing dim.

## Migration Plan

Presentation-only and self-contained in three asset/template files plus
`gallery.js`. No data, config, or route changes; nothing to migrate and no
staged rollout. Rollback is reverting the commit.

## Open Questions

None blocking. One deliberate deferral: if candidate lists ever grow large enough
that a single search response is itself the cost, incremental *result* fetching is
a separate change against `poster-sources`, not a widening of this one.
