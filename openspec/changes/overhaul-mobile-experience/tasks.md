## 1. Nav links: single source of truth

- [x] 1.1 Add `templates/partials/_nav_macros.html.twig` defining a macro for the
  secondary navigation links (Poster Wall → `/wall`, Import from Plex → `/plex`,
  Orphans → `/orphans`) and a macro for the account link (Log out → `/logout`,
  gated by `auth_bypass`). Each macro renders the existing `.btn` / nav markup so
  callers get today's output.
- [x] 1.2 In `templates/gallery.html.twig`, replace the hard-coded
  `.toolbar__actions` links with a call to the secondary-nav macro, confirming
  the rendered desktop toolbar HTML is unchanged.
- [x] 1.3 In `templates/layout.html.twig`, render the Log out link in the topbar
  via the account macro (unchanged desktop output).

## 2. App-wide mobile menu (layout)

- [x] 2.1 Add a standalone Alpine scope on the topbar in `layout.html.twig`
  (`x-data="{ menuOpen: false }"`) placed outside any page content root so it
  does not nest inside `galleryUI`/`orphansPage`.
- [x] 2.2 Add a menu (hamburger) button in the topbar with an `aria-label`
  (e.g. "Menu"), toggling `menuOpen`; render it only where secondary navigation
  exists (skip when `auth_bypass` and there is nothing to show).
- [x] 2.3 Add `templates/partials/_menu.html.twig` containing the tray markup,
  reusing the `.sheet` structure (backdrop, panel, head with close button). Its
  body calls the secondary-nav and account macros from task 1. Wire dismissal:
  backdrop click, `@keydown.escape.window`, and closing on link choice.
- [x] 2.4 Include `_menu.html.twig` once in `layout.html.twig` so every page
  inherits the menu.

## 3. CSS: mobile chrome + tray

- [x] 3.1 Style the menu button (hidden on pointer/desktop, shown at
  `max-width: 640px`); keep the brand and button aligned in the topbar.
- [x] 3.2 Present the menu tray using the existing `.sheet` styles; add only the
  minimal rules needed for a vertical list of nav links in the tray body.
- [x] 3.3 In the `@media (max-width: 640px)` block, hide `.toolbar__actions` (now
  in the tray) while keeping `.search` and `.sort` in the toolbar; verify no
  horizontal overflow and that tabs still scroll.
- [x] 3.4 Confirm the desktop (pointer/wide) layout of topbar, toolbar, and tabs
  is visually unchanged.

## 4. Tooltip cursor affordance

- [x] 4.1 Add `cursor: help` to `.card__caption` (non-interactive tooltip host)
  in `public/assets/app.css`, scoped so it does not affect interactive
  tooltip hosts (pagination `.btn` steps, find-item preview image) which keep
  `cursor: pointer`.
- [x] 4.2 Verify hovering a truncated caption shows the `help` cursor and the
  custom tooltip still appears; interactive tooltip hosts still show pointer.

## 5. Cross-page verification

- [x] 5.1 Gallery on a phone width: only tabs, search, sort, and the grid above
  the fold; menu button opens the tray with all secondary links; tray dismisses
  on backdrop/Escape/link.
- [x] 5.2 Plex import, Orphans, and Poster Wall pages: the menu button and tray
  render and work (menu lives in the shared layout).
- [x] 5.3 Login page (and `auth_bypass`): no menu button shown.
- [x] 5.4 Poster action sheet still opens on tap and is unaffected by the new
  menu tray (both reuse `.sheet` but are independent scopes).
- [x] 5.5 Run existing checks (PHP-CS-Fixer, PHPStan, PHPUnit) to confirm no
  template/asset change broke the build.

## 6. Native-app polish (second pass)

- [x] 6.0 Show the menu even when `AUTH_BYPASS` is set (gate only Log out), so
  secondary nav stays reachable on a phone.
- [x] 6.1 App-style trays: replace the × close with a centered grab handle and
  add swipe-down-to-dismiss, shared across the menu, poster, sort, and import
  trays via one generic gesture handler that reuses each tray's backdrop close.
- [x] 6.2 Make tray buttons pop: lift plain buttons onto an elevated surface tone
  with a clearer border (accent/danger keep their colour), scoped to `.sheet__body`
  so the desktop hover overlay is untouched.
- [x] 6.3 Native-style tab bar on mobile: equal-width icon+label tabs that all fit
  the screen (icons partial + short labels), desktop text tabs unchanged.
- [x] 6.4 Sort tray on mobile: a sort trigger opens a tray with the same orders;
  inline sort stays on desktop.
- [x] 6.5 Import tray on mobile: intercept Import from Plex on touch to fetch the
  /plex form into a tray and re-init Alpine on it; desktop still navigates.
- [x] 6.6 Verify: Twig compile, JS syntax, PHPUnit/PHPStan/CS, update delta specs.

## 7. Native-app polish (third pass)

- [x] 7.1 Enlarge the mobile tab icons/labels and move the tab bar to a fixed,
  always-visible bottom bar; pad the body so content clears it.
- [x] 7.2 Contain progress inside trays: restructure the sheet so its body scrolls
  while the panel clips, and pin reused `.overlay` progress to the tray instead of
  the full screen.
- [x] 7.3 Import fully in the tray: intercept the import form submit to run via
  fetch, report the result, close, and refresh the gallery — no navigation.
- [x] 7.4 Orphans in a tray: open /orphans in a tray (reusing its scan/delete
  component), and make delete-all run in place (fetch) so nothing navigates; guard
  the gallery's delegation against nested tray scopes.
- [x] 7.5 Replace pagination with infinite scroll on the phone: append the next
  page as a sentinel nears the viewport.
- [x] 7.6 Verify: Twig compile, JS syntax, PHPUnit/PHPStan/CS, update delta specs.
