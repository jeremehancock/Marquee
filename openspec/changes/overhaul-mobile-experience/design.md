## Context

The gallery already has a solid desktop layout and, from the earlier
`mobile-action-sheet` change, a touch-first poster action tray: on touch devices
tapping a poster opens a bottom sheet built from the card's own `.card__actions`
markup (reused via `outerHTML`, no duplication), while pointer devices keep the
hover overlay. That sheet is driven by a shared Alpine factory,
`overlayComponent()` in `public/assets/gallery.js`, mixed into each page's root
(`galleryUI`, `orphansPage`) and rendered by the shared
`templates/partials/_overlays.html.twig` partial.

What is still desktop-shaped on a phone is the surrounding chrome:

- The topbar (`layout.html.twig`) shows the brand and a bare "Log out" link.
- The gallery toolbar (`gallery.html.twig`) packs search, a sort toggle, and
  three secondary-navigation buttons — Poster Wall, Import from Plex, Orphans —
  into one bar. On a phone `.toolbar__actions` wraps but still eats vertical
  space above the grid and pulls focus away from the posters.
- Poster captions carry a custom tooltip (`[data-tooltip]`, positioned by
  `app.js`) but, being plain text, render the text/I-beam cursor, which reads as
  "editable."

Constraints: no Node build step; Twig + Alpine + hand-written CSS only. Desktop
is considered "right" and must not regress. The maintainer explicitly wants to
avoid a large amount of duplicated code — i.e. no parallel mobile-only page
templates and no second navigation/overlay system.

## Goals / Non-Goals

**Goals:**
- Make the phone gallery view dominated by posters: tabs, search, sort, grid.
- Move secondary navigation (Poster Wall, Import from Plex, Orphans, Log out)
  behind an app-wide mobile menu tray, consistent with the existing action-sheet
  "app-like tray" feel.
- Reuse the existing bottom-sheet/overlay machinery and keep a single source of
  truth for the navigation links.
- Leave the desktop layout unchanged.
- Fix the tooltip cursor on non-interactive tooltip hosts (desktop tweak).

**Non-Goals:**
- No backend, routing, controller, config, or dependency changes.
- No redesign of the poster action sheet (already done) or of the change-poster /
  confirm / viewer modals.
- No new JS module or framework; menu state is a few lines of inline Alpine.
- No per-page bespoke mobile templates — the same templates serve both widths.

## Decisions

### 1. One menu, defined in the shared layout — not per page
The menu button and tray live in `layout.html.twig` so every authenticated page
(gallery, Plex import, orphans, wall) gets the same navigation without each
template re-implementing it. The tray markup goes in a new shared partial,
`templates/partials/_menu.html.twig`, included once by the layout.

*Alternative considered:* add the menu inside `galleryUI` on the gallery page
only. Rejected — it would leave the other pages with the old desktop header on a
phone and push menu markup into a page component instead of the app shell.

### 2. Single source of truth for the nav links (Twig macro)
The secondary links (labels + hrefs for Poster Wall, Import from Plex, Orphans,
and Log out) are defined once as a Twig macro in a small partial
(`templates/partials/_nav_macros.html.twig`, e.g. `secondary_nav()` and
`account_nav()`), then *called* from two places:
- the existing desktop positions — the gallery `.toolbar__actions` and the
  topbar Log out — so desktop output is byte-for-byte what it is today;
- the mobile menu tray body.

This is what keeps the overhaul from duplicating link markup: the tray and the
desktop chrome render the *same* macro output. Log out is gated by
`auth_bypass` exactly as today.

*Alternative considered:* physically move the links into the topbar and relocate
them with CSS. Rejected — CSS cannot move DOM between the toolbar and a
page-global tray, and moving them in the DOM would change the desktop layout the
user is happy with.

### 3. Reuse the bottom-sheet presentation, with independent menu state
The tray reuses the existing `.sheet` CSS (backdrop, slide-up panel, head with a
close button) so it matches the poster action sheet and adds minimal CSS. Its
open/close state is a tiny, standalone Alpine scope on the topbar
(`x-data="{ menuOpen: false }"`) with backdrop-click and `@keydown.escape.window`
handlers — mirroring the existing modal patterns in `_overlays.html.twig`. It is
deliberately *independent* of `galleryUI`/`orphansPage` so it works on every
page, including the Plex and wall pages that have their own (or no) Alpine root.
No change to `gallery.js` or `app.js` is required for the menu.

*Alternative considered:* extend `overlayComponent()` with a `menu` field.
Rejected — that factory is only mixed into gallery/orphans roots, so it would not
cover the Plex import or wall pages, and it would couple app-shell chrome to a
poster-library component. A 3-line inline scope in the layout is simpler and
universal.

*Alternative considered:* a side/nav drawer instead of a bottom sheet. A bottom
sheet is chosen for visual consistency with the action sheet already shipped and
to reuse its CSS; this is the one place a reviewer might prefer a drawer, so it
is called out in Open Questions.

### 4. Mobile toolbar declutters via CSS, keeping search + sort
On narrow screens `.toolbar__actions` (the three secondary buttons) is hidden
with CSS (they now live in the tray), while `.search` and the `.sort` toggle
stay in the toolbar as the gallery's primary controls. The menu button itself is
shown only on narrow screens (and hidden on pointer/desktop) using the same
breakpoint the gallery already uses (`@media (max-width: 640px)`), with
`(hover: none)`/`(hover: hover)` reserved for the touch-vs-pointer action
distinction that already exists. Because the tray simply *re-renders* the same
macro, hiding the desktop copy on mobile is purely presentational — no
JavaScript moves nodes.

### 5. Tooltip cursor affordance
Add `cursor: help` to non-interactive tooltip hosts — specifically
`.card__caption` (which currently inherits the text cursor). Interactive tooltip
hosts (pagination `.btn` steps, and the find-item preview image which is itself
clickable) keep `cursor: pointer`. Scope the rule narrowly (to the caption /
non-interactive `[data-tooltip]` text) rather than to every `[data-tooltip]`, so
it does not override the pointer cursor on tooltip-bearing buttons and links.

## Risks / Trade-offs

- **[Nested Alpine roots on the gallery page]** The gallery page would have the
  layout's `menuOpen` scope in the header and `galleryUI` on the content root.
  → They are sibling scopes, not nested; Alpine handles multiple independent
  `x-data` roots. Keep the menu scope on the `<header>`, outside the
  `[data-gallery]` root, so there is no nesting.
- **[Breakpoint drift between "is mobile" for chrome and "is touch" for actions]**
  The action sheet keys off `(hover: none)`; the menu keys off a width
  breakpoint. A large touch tablet could be "touch" but wide. → Acceptable and
  intentional: the action-sheet vs overlay decision is about input capability,
  the menu vs inline-toolbar decision is about width. Document both in CSS
  comments and keep the width breakpoint aligned with the existing gallery
  mobile rules (640px).
- **[Duplicate-render vs hide]** The nav macro renders in both the desktop
  chrome and the tray, with CSS hiding the one that does not apply. Both copies
  exist in the DOM at all widths. → This is intentional (keeps markup DRY at the
  source and avoids JS DOM-moving); the hidden copy is inert and tiny (four
  links).
- **[Menu on non-gallery pages]** Plex/orphans/wall must expose the menu too.
  → Because it lives in the layout, they get it for free; verify each renders and
  that the tray's own links (e.g. hide "Orphans" while already on Orphans, if
  desired) behave sensibly.

## Migration Plan

Pure front-end/template change shipped in the Docker image like any other. No
data, config, or route migration. Rollback is reverting the template/CSS diff.
Verify by loading the app at desktop width (unchanged) and at phone width (menu
button present, tray opens/closes, gallery shows only tabs/search/sort/grid), and
by hovering a truncated caption on desktop (help cursor, custom tooltip still
shows).

## Open Questions

- **Tray style:** bottom sheet (chosen, reuses action-sheet CSS) vs a top/side
  drawer. Confirm the bottom sheet feels right for navigation, or switch to a
  drawer at apply time — the nav-links macro and menu-state decisions are
  unaffected either way.
- **Sort control placement:** kept inline in the mobile toolbar as a primary
  gallery control. If the toolbar still feels busy, sort could also move into the
  tray, but that would reintroduce a page-specific control into an app-wide menu;
  left inline for now.
