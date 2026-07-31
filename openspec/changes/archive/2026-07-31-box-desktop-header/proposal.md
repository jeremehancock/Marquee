## Why

On a wide screen the app's top bar stretches edge to edge while everything below
it — the gallery, the page footer — sits in a centred 960px column, so the brand
and the navigation drift far away from the content they belong to. Boxing the
header to the same column makes the app read as one contained surface instead of
a full-bleed bar floating above a narrow page.

## What Changes

- On pointer/desktop-width screens the top bar's brand and navigation are
  aligned to the same left and right edges as the page content below them,
  instead of sitting out at the viewport edges.
- The header adopts the presentation used by the project's own landing page at
  `getmarquee.now`: the bar still spans the viewport, but it is tinted like the
  page and separated by a single hairline beneath it rather than reading as a
  raised panel.
- Narrow screens are explicitly unchanged: the top bar stays a full-bleed bar
  pinned to the top edge, with its bottom border and no rounding, exactly as it
  renders today.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the shared layout gains a requirement that the page
  header's contents are constrained to the content column on desktop widths,
  and that the header is presented as page-coloured chrome there, while narrow
  screens keep their existing bar.

## Impact

- `public/assets/app.css` — the `.topbar` rules and a desktop-width media query.
- No template, PHP, or JavaScript change: `templates/layout.html.twig` keeps its
  existing markup.
- Affects every page that extends the shared layout (gallery, Plex import,
  orphans, login). The standalone poster wall has no top bar and is untouched.
