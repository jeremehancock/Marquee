## Context

The shared layout already renders two footers, and they are near-duplicates of
each other by design:

- `templates/layout.html.twig` — `.footer`, the page footer. `app.css` hides it
  outright inside `@media (max-width: 640px)`.
- `templates/partials/_menu.html.twig` — `.menu__footer`, pinned to the bottom of
  the mobile navigation drawer, which exists precisely because the page footer is
  hidden at that width. The drawer's opener (`.menu-btn`) is `display: none`
  above 640px, so the drawer is mobile-only in practice.

Both currently render the same product name + version + update-note line, and
`app.css` already styles them with shared selector lists (`.footer a,
.menu__footer a`). Attribution follows that established split exactly: one block
of markup, rendered in both places, visible in whichever footer the current
viewport shows.

Marquee is dark-only (`--bg: #1c1e24`, no `prefers-color-scheme` rules in
`app.css`). The logos come from the Posteria marketing site, where they sit on a
comparably dark footer, so they transfer without recolouring.

`public/sw.js` caches anything under `/assets/` with stale-while-revalidate at
runtime — there is no precache manifest to extend. The `asset()` Twig helper
appends the file's mtime for cache-busting.

## Goals / Non-Goals

**Goals:**

- Credit TMDB, TheTVDB and fanart.tv in footer chrome, reachable on every screen
  size.
- Define the provider list once, so the two footers cannot drift apart.
- Serve the logos locally, with no third-party request at render time.

**Non-Goals:**

- Crediting Mediux. Marquee's poster source does not return its artwork.
- Any attribution inside the Find Posters UI, on candidate thumbnails, or on the
  poster wall. This change is footer chrome only.
- Making the provider list configurable. It is fixed markup, matching how the
  product name is fixed.
- A light theme, or per-theme logo variants.

## Decisions

**One shared partial, included twice.** Add
`templates/partials/_attribution.html.twig` holding the label and the three
links, and include it from both `layout.html.twig` and `_menu.html.twig`. This is
what makes "defined in exactly one place" true rather than aspirational.
*Alternative rejected:* duplicating the three `<a>` elements in both templates —
five lines shorter, but it is exactly the drift the spec forbids, and the
existing footers already show how easily two near-identical blocks diverge.

**Reuse the vendor logo files as-is.** Copy `tmdb.svg`, `tvdb.png` and
`fanart.png` from the Posteria.app repo into `public/assets/providers/`. They are
the providers' official marks; redrawing or recolouring them would violate the
brand terms that motivate crediting them in the first place. Mediux's `mediux.svg`
is not copied.

**Size in CSS, not with an HTML `height` attribute.** The marketing site sets
`height="15"`, `height="35"`, `height="30"` inline, because the three marks have
very different aspect ratios (TMDB is a wide wordmark at 273×36; TVDB is nearly
square at 400×216; fanart.tv is 303×61). Marquee needs the same per-logo
normalisation, but expressed as a per-provider CSS class so the sizes live with
the rest of the layout rules and can be adjusted at the 640px breakpoint. Set
`width`/`height` attributes on the `<img>` tags anyway, at the intrinsic ratio,
so the footer does not reflow while the images decode.

**Placement above the existing footer line, in both footers.** The credit is the
broader statement; the product/version line is the narrower one, and the update
note attaches to it. Inserting above keeps that line — and the
`.js-update-note` span the update check writes into — untouched.

**Let the existing 640px rule do the mobile switch.** `.footer { display: none }`
already hides the whole page footer on a phone, including anything added inside
it, and `.menu__footer` is only ever seen there. No new visibility rules are
needed; the switch is a consequence of where the markup is included.

**Attribution appears on the login page too.** `login.html.twig` extends the
layout and blanks only the `nav` block, so it inherits the page footer. That is
correct — the credit is a property of the software, not of being signed in — and
requires no special-casing.

**`rel="noopener"` on every provider link**, matching the existing
`getmarquee.now` links in both footers.

## Risks / Trade-offs

- **Three more image requests on every page load** → They are small (2 KB, 2 KB,
  13 KB), served from the same origin, and `sw.js` caches `/assets/` after the
  first load. Explicit `width`/`height` prevents layout shift.
- **The drawer footer grows taller, squeezing the drawer's scrollable body on a
  short phone** → The credit is a single row of small logos; `.menu__footer` is
  already `flex: 0 0 auto` with the body scrolling above it, so the body absorbs
  the loss rather than the footer being clipped. Verify on the shortest target
  viewport during the browser check.
- **`tvdb.png` is a 400×216 raster displayed at ~35px tall** → It downscales
  cleanly and stays sharp on a 2× display; SVG is not available for it.
- **The provider list is markup, so adding a fourth provider later means editing
  a template** → Acceptable. The list changes only when the poster source's
  upstreams change, which is a code change anyway.
