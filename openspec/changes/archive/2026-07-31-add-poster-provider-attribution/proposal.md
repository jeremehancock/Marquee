## Why

Marquee's Find Posters feature is powered by artwork from TMDB, TheTVDB and
fanart.tv, but nothing in the app says so. Those providers ask to be credited by
name and logo wherever their artwork is used, and crediting them also tells a
user where the posters they are browsing actually come from.

## What Changes

- Add a "Posters provided by:" credit line with the TMDB, TheTVDB and fanart.tv
  logos to the footer chrome the shared layout already renders.
  - On desktop it appears in the page footer, above the existing product name
    and version line.
  - On mobile the page footer is hidden, so it appears in the navigation
    drawer's footer instead, above that footer's product name and version line.
- Each logo links to its provider's website in a new tab.
- Ship the three provider logos as static assets served from `/assets`.
- Mediux is deliberately excluded — Marquee's poster source does not return
  artwork from it.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: gains a "Poster provider attribution" requirement
  covering what the footer chrome credits, where it appears on desktop versus
  mobile, and that each logo links out. The existing footer requirements are
  unchanged — attribution sits alongside the product name and version, it does
  not replace them.

## Impact

- `templates/layout.html.twig` — page footer gains the attribution block.
- `templates/partials/_menu.html.twig` — drawer footer gains the same block.
- New `templates/partials/_attribution.html.twig` — the shared markup, so the
  provider list is defined once.
- `public/assets/app.css` — attribution layout and logo sizing, plus the
  existing `@media (max-width: 640px)` rules that already hide the page footer.
- New logo assets under `public/assets/providers/`.
- `tests/Functional/ApplicationShellTest.php` — coverage for the credit line and
  the outbound links.
- No PHP, config, database, or Dockerfile changes. No new dependencies.
