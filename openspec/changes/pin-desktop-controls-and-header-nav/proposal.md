## Why

On a phone the gallery keeps search, sort, and the category tabs on screen at all
times. On a desktop they scroll away after the first row of posters, so changing
category, searching, or re-sorting means scrolling back to the top of a page that
can run several screens deep. Desktop is currently worse than a phone at the two
things a user does most while browsing a library.

The same toolbar also carries four secondary actions — Poster Wall, Import from
Plex, Orphans, Support Development — which have nothing to do with the poster grid
they sit above. They overflow the toolbar onto a second row, they crowd out the
controls that do belong there, and they leave the desktop header holding nothing
but a brand and a Log out link. Moving them up is what makes the remaining toolbar
short enough to pin.

## What Changes

- The category tabs and the gallery toolbar (search and sort) stay pinned to the
  top of the viewport on a pointer/desktop screen as the gallery scrolls, instead
  of scrolling away with the content.
- The secondary actions move out of the gallery toolbar and into the shared page
  header, where they render as icon-and-label buttons rather than the text-only
  buttons used today.
- Log out joins them as a matching icon-and-label button in the same group,
  replacing the plain text link it is today.
- Because the secondary actions now live in the shared layout, they appear on
  every page that renders the header — so `/plex` and `/orphans` gain the
  navigation they lack today, matching what the phone menu tray already provides
  on every page. The current destination is marked as such rather than offered as
  a live link to the page already being viewed.
- Below the width where icon-and-label buttons fit beside the brand, the header
  actions fall back to icons alone with their existing tooltips. Their full names
  remain their accessible names at every width.
- Phone behaviour is unchanged throughout: the bottom tab bar, the pinned mobile
  toolbar, and the overflow menu tray all keep working exactly as they do now.

## Capabilities

### New Capabilities

None. This change modifies existing capabilities only.

### Modified Capabilities

- `poster-library`: the desktop gallery controls become a pinned block. Two
  requirements that currently freeze desktop presentation as "unchanged" —
  "Responsive gallery layout" and "Native-style category tab bar on small
  screens" — are rewritten, since the desktop toolbar no longer scrolls with the
  page, no longer carries the secondary actions, and the tabs no longer sit in
  their original position.
- `application-shell`: the shared page header gains the secondary navigation on
  pointer/desktop screens, presented as icon-and-label actions with a
  narrower-width icon-only fallback and a marked current destination. "App-wide
  mobile actions menu" and "Page header aligns with the content column on desktop"
  are both amended to account for it.

## Impact

- `templates/layout.html.twig` — the header renders the secondary links and the
  button form of Log out.
- `templates/gallery.html.twig` — the tabs and toolbar are wrapped in a single
  pinnable block; the toolbar's actions group is removed.
- `templates/partials/_nav_macros.html.twig` — links gain short labels for the
  header alongside their full accessible names; `logout_link`'s plain-link branch
  loses its last caller and collapses.
- `public/assets/app.css` — a pinned-block rule for pointer/desktop widths, an
  opt-out inside the existing `max-width: 640px` block so the mobile toolbar keeps
  its own pinning, header action styling with an icon-only band, and removal of
  the rules that hid nav icons and laid out the toolbar actions group.
- `public/assets/app.js` — the tooltip system's "don't restate what is already on
  screen" opt-in is generalised from truncated text to cover a label the layout
  has dropped outright, which is what the header's icon-only band needs.
- No PHP, routing, configuration, or data changes. No new dependencies.
- Layering must continue to place the pinned block below every tray, dialog, and
  the fullscreen viewer, as the existing mobile rules already require.
