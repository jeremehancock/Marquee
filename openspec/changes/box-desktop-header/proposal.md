## Why

On a wide screen the app's top bar stretches edge to edge while everything below
it — the gallery, the page footer — sits in a centred 960px column, so the brand
and the navigation drift far away from the content they belong to. Boxing the
header to the same column makes the app read as one contained surface instead of
a full-bleed bar floating above a narrow page.

## What Changes

- On pointer/desktop-width screens the top bar becomes a centred box no wider
  than the page content column, with its brand and navigation aligned to the
  same left and right edges as the content below it.
- The boxed header reads as a self-contained surface — bordered on all sides and
  rounded — rather than a bar whose background bleeds to the viewport edges.
- Narrow screens are explicitly unchanged: the top bar stays a full-bleed bar
  pinned to the top edge, with its bottom border and no rounding, exactly as it
  renders today.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the shared layout gains a requirement that the page
  header is constrained to the content column on desktop widths and remains
  full-bleed on narrow screens.

## Impact

- `public/assets/app.css` — the `.topbar` rules and a desktop-width media query.
- No template, PHP, or JavaScript change: `templates/layout.html.twig` keeps its
  existing markup.
- Affects every page that extends the shared layout (gallery, Plex import,
  orphans, login). The standalone poster wall has no top bar and is untouched.
