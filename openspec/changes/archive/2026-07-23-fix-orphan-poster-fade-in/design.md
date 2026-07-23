## Context

Poster cards are styled by `.card__frame` / `.card__image` in
`public/assets/app.css`. The frame paints an infinite shimmer via
`.card__frame::before`, and `.card__image` ships at `opacity: 0`, transitioning
to `opacity: 1` only once the class `is-loaded` is present. Nothing in CSS adds
that class — it is added by `markLoaded()` in `public/assets/gallery.js`.

`gallery.js` is loaded from `layout.html.twig`, so it runs on every page. But
`markLoaded`, `initImages`, and the `initImages(document)` call all live inside
the `DOMContentLoaded` handler *after* this guard:

```js
var root = document.querySelector('[data-gallery]');
if (!root) { return; }
```

Only `gallery.html.twig` carries `data-gallery`. `orphans.html.twig` reuses the
same `.card` / `.card__frame` / `.card__image` markup but has no gallery root, so
the handler returns before any image is marked loaded. Every orphan poster stays
at `opacity: 0` forever while the shimmer underneath keeps animating — which
reads as a lazy loader stuck mid-load. The image itself downloads fine; only the
reveal is missing.

The rest of the guarded block (live search, pagination fetch, delegated
mutations, action sheet) genuinely depends on the gallery root and must stay
behind the guard.

## Goals / Non-Goals

**Goals:**
- Poster images fade in on any page rendering poster cards, orphans included.
- The shimmer stops once its image resolves, including on load failure.
- No behavior change on the gallery page.

**Non-Goals:**
- Rewriting the lazy-load mechanism (native `loading="lazy"` stays).
- Introducing an IntersectionObserver or any new dependency.
- Touching the poster wall, which has its own asset (`wall.js`) and markup.
- Restyling orphan cards to gain gallery actions (hover overlay, action sheet).

## Decisions

**1. Hoist the image-reveal wiring out of the `[data-gallery]` guard.**

Move `markLoaded` / `initImages` to the IIFE scope in `gallery.js` and call
`initImages(document)` at `DOMContentLoaded` *before* the root check. The gallery
block keeps calling `initImages(results)` after swapping grid HTML, so
fetch-refreshed cards still fade in.

*Alternatives considered:* adding `data-gallery` to the orphans page — rejected,
it would activate search/pagination/mutation code against a page with no
`#results` element and no gallery routes. A separate `posters.js` shared asset —
rejected as more moving parts than a scope change; if a third page needs card
behavior later, extracting then is cheap.

**2. Suppress the shimmer with `:has()` rather than a second JS class.**

Add a rule so `.card__frame` whose image is `is-loaded` drops the `::before`
animation. `:has()` keeps the JS single-purpose (one class, on the element that
transitions). Where `:has()` is unsupported, the loaded image simply paints over
the shimmer as it does today — degradation is invisible, not broken.

*Alternative considered:* having `markLoaded` also add a class to the parent
frame. Works everywhere, but couples the JS to card DOM structure for a purely
cosmetic effect.

**3. Resolve on `complete`, not on `complete && naturalWidth > 0`.**

`markLoaded` listens for `error` as well as `load`, but its fast path required
`naturalWidth > 0`. A fetch that has already failed is `complete` with
`naturalWidth === 0`, so it fell through to attaching listeners for events that
had already fired — and stayed transparent forever. Browser-verified: a card
pointing at a 404 stayed at `opacity: 0` with the shimmer running even after the
scope fix. Widening the fast path to `img.complete` is what actually delivers the
spec's "image that fails to load" scenario.

*Trade-off:* an `<img>` with an empty `src` is also `complete`, so it would be
marked resolved. Poster cards always render a `src`, and "nothing to wait for"
is the right answer for that case anyway.

## Risks / Trade-offs

- **The reveal remains JS-dependent; if `gallery.js` fails to load, every poster
  on every page is invisible.** → Pre-existing condition, and now uniform across
  pages rather than page-specific. Not addressed here; a CSS-only default would
  mean losing the fade-in.
- **`initImages(document)` now runs on pages with no cards.** → It is a single
  `querySelectorAll` returning an empty list; cost is negligible.
- **`:has()` support gap on very old browsers.** → Shimmer keeps animating behind
  a fully opaque image, which is what happens today; no visual regression.
- **No automated coverage for the JS behavior** (no Node toolchain in this
  project). → Guard the markup contract with a functional test asserting orphan
  cards use the shared card classes, and verify the rendered result in a headless
  browser against the real app before/after.

## Migration Plan

Static assets only; no data, route, or config change. Deploy is a normal release.
The `asset()` Twig helper appends each file's mtime, so the changed `gallery.js`
and `app.css` are new URLs that defeat both the browser cache and the service
worker's `/assets/` runtime cache. Rollback is reverting the two asset files.

## Open Questions

None.
