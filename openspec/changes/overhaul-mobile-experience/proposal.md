## Why

The desktop gallery is in good shape, but on a phone the same chrome that works
with a mouse crowds the posters: five category tabs, a search field, a sort
toggle, and three secondary navigation buttons (Poster Wall, Import from Plex,
Orphans) all compete for space above the grid, and the log-out link sits in a
header built for a wide screen. The poster action sheet already proved that an
"app-like" tray is the right touch pattern; the rest of the app should follow so
a phone screen is dominated by posters and everything secondary lives behind a
mobile-friendly menu. A small desktop tooltip affordance is fixed alongside.

## What Changes

- **App-wide mobile menu.** On small screens the topbar gains a menu (hamburger)
  button that opens a tray holding the secondary navigation — Poster Wall,
  Import from Plex, Orphans, and Log out — the same links that render inline on
  desktop. The gallery toolbar sheds those buttons on mobile so the phone view
  is just tabs, search, sort, and posters.
- **One source of truth for nav links.** The secondary-navigation links are
  defined once (a Twig macro) and placed both in the desktop toolbar/header
  (unchanged output) and in the mobile tray, so the overhaul adds no duplicated
  link markup and no parallel mobile-only page templates.
- **Reuse the existing tray/overlay machinery.** The menu tray reuses the
  bottom-sheet styles and the shared Alpine overlay pattern already used by the
  poster action sheet, rather than introducing a second, separate drawer system.
- **Desktop is preserved.** The desktop layout, toolbar, and header are
  unchanged in spirit; the menu button and tray are mobile-only.
- **Tooltip cursor affordance (desktop tweak).** Non-interactive elements that
  carry a custom tooltip (poster captions) SHALL show a cursor that signals a
  tooltip (`help`) instead of the text/I-beam cursor, so hovering a truncated
  title reads as "there's more to see," not "edit this."

## Capabilities

### New Capabilities
<!-- None: this change reshapes existing chrome; it targets existing specs. -->

### Modified Capabilities
- `poster-library`: the responsive gallery layout requirement changes so that on
  small screens the secondary navigation collapses into an app menu tray and the
  gallery view stays focused on tabs, search, sort, and the poster grid.
- `application-shell`: the shared-layout requirement gains an app-wide mobile
  navigation menu (a topbar menu button opening a tray of secondary links), and
  the custom-tooltip requirement gains a cursor affordance so non-interactive
  tooltip hosts indicate a tooltip rather than text entry.

## Impact

- Modified templates: `templates/layout.html.twig` (menu button + menu tray,
  app-wide), `templates/gallery.html.twig` (secondary nav moves behind the menu
  on mobile), a new shared `templates/partials/_menu.html.twig` (tray markup) and
  a nav-links macro partial.
- Modified assets: `public/assets/app.css` (menu button, tray presentation,
  mobile toolbar decluttering, tooltip `cursor: help`), and only a minimal
  Alpine wiring for the menu (inline in the layout, mirroring the existing modal
  patterns — no new JS file).
- No backend changes: no controllers, routes, services, config, or environment
  variables are affected. No new dependencies.
