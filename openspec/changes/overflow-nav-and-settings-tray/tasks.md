## 1. Split the link set

- [x] 1.1 In `templates/partials/_nav_macros.html.twig`, split `secondary_links()` into a bar group (Poster Wall, Import from Plex, Orphans) and an overflow group (Settings, Support Development, Log out), keeping both as the single source of truth for all three placements. Log out stays gated on `signed_in()` by the caller as it is today.
- [x] 1.2 Add a macro or set that answers "is the current destination in the overflow group", defined beside the group itself so the two cannot drift.
- [x] 1.3 Give `item()` a `data-settings` hook for the `settings` key, alongside the existing `data-import` / `data-orphans`.
- [x] 1.4 Update `templates/partials/_menu.html.twig` to render both groups in sequence, so the mobile tray still lists all six entries under their full names in the existing order.

## 2. Desktop overflow menu

- [x] 2.1 In `templates/layout.html.twig`, render the bar group, then the overflow trigger and its panel, then the connection status. Add `moreOpen` to the existing `x-data="{ menuOpen: false }"` on `.topnav`.
- [x] 2.2 Give the trigger the same three-dot glyph the `.menu-btn` uses, an accessible name naming what it opens, `aria-haspopup="menu"`, and `aria-expanded` bound to `moreOpen`.
- [x] 2.3 Wire dismissal: Escape, click/focus outside, and choosing an entry all close the panel, and closing returns focus to the trigger.
- [x] 2.4 Mark the trigger as current when the page being viewed is one the overflow group holds, reusing the `nav-item--current` treatment. The entry inside the panel keeps rendering as a `<span>` via the existing `item()` logic.
- [x] 2.5 In `public/assets/app.css`, style the panel: `position: absolute` off a now-`relative` `.topnav`, never `fixed` — the header's `backdrop-filter` makes it a containing block for fixed descendants (see the write-up in `partials/_menu.html.twig:7-24`).
- [x] 2.6 Extend the full-label / no-tooltip rules `.menu__body` already applies so they also cover the overflow panel, rather than duplicating them.
- [x] 2.7 Confirm the 641–900px icon-only fallback (`app.css:407-415`) still applies to the bar group and leaves the trigger and panel untouched.

## 3. Elevation ladder

- [x] 3.1 Give the panel `z-index: 35` so it draws above `.gallery-head` (sticky, `z-index: 30`, `app.css:1002-1006`), which it opens over.
- [x] 3.2 Add the rung to the ladder comment at `app.css:21-27` — "pinned controls 30, header overflow menu 35, tab bar 40, …" — and give the panel a shadow from the tier that agrees with it.
- [x] 3.3 Re-read `DesignTokenContractTest::testElevationAgreesWithTheStackingLadder` by hand and update its transcribed tiers. The test never reads a `z-index` out of the CSS, so it will not fail on its own if this is skipped.
- [ ] 3.4 Open the panel over a scrolled gallery with its controls pinned and confirm by eye that it draws above them.

## 4. Settings tray

- [x] 4.1 Add the settings tray to `templates/gallery.html.twig`: `.sheet` bound to `settingsOpen`, `.sheet__panel--tall`, heading "Settings", body `x-ref="settingsBody" data-nested-scope`, matching the Import and Orphans trays beside it.
- [x] 4.2 Add the `a[data-settings]` clause to the secondary-destination click delegate at `public/assets/gallery.js:414`, dispatching `gallery:settings` under the same `isTouch()` + `[data-gallery]` gating.
- [x] 4.3 Add `openSettings()` to the gallery component: call the existing `_loadTray('/settings', 'settingsBody')` on every open (no `loaded` flag — the library list and the superseded notice both decay), with the loading state and the load-failure fallback the Import and Orphans trays use.
- [x] 4.4 Add `closeSettings()`, clearing the body so a reopen starts from the server's state rather than a rejected submission.
- [x] 4.5 After load, intercept the `form[action="/settings"]` submit and route it through `saveSettings(form)`.

## 5. Save contract

- [x] 5.1 `saveSettings(form)` posts the form's `FormData` with `redirect: 'manual'`, showing the tray-contained progress overlay while it is in flight.
- [x] 5.2 On `response.type === 'opaqueredirect'`, close the tray and `location.reload()`. Comment the call site: an opaque redirect is the *success* branch here, and `manual` is chosen so the throwaway `GET /settings` does not consume the `Settings saved.` flash the reloaded gallery renders (`templates/gallery.html.twig:64`).
- [x] 5.3 On `200`, swap the re-rendered fragment back into the tray, re-run `Alpine.initTree`, re-bind the submit handler, and leave the tray open with its errors. Do not reload.
- [x] 5.4 On a network failure, leave the tray open and report it, matching how the other trays fail.

## 6. Tests

- [x] 6.1 Update `tests/Functional/ApplicationShellTest.php` for the two-tier header: `testSecondaryActionsRenderInTheHeaderOnEveryPageWithNavigation` and `testHeaderCarriesLogOutWhenSignedIn` now expect Log out in the panel rather than the bar.
- [x] 6.2 Extend `testTheCurrentDestinationIsMarkedAndNotLinked`, or add a sibling, asserting the trigger is marked current on `/settings` while the entry inside stays a non-link.
- [x] 6.3 Assert the trigger's accessible name, `aria-haspopup`, and `aria-expanded`, alongside the existing `testMenuTriggerPresentsAnOverflowGlyphRatherThanAHamburger`.
- [x] 6.4 Confirm `testTrayKeepsTheFullNamesTheHeaderShortens` still passes with the tray rendering two groups, and that all six entries are still present.
- [x] 6.5 Add a `SettingsTrayTest` under `tests/Unit/Asset/`, modelled on `ImportTrayReuseTest` and `OrphansTrayRescanTest`: the `data-settings` delegate clause, the re-fetch-on-every-open behaviour, the `redirect: 'manual'` save, the opaque-redirect reload branch, and the 200-keeps-the-tray-open branch.
- [x] 6.6 Assert the settings tray uses `.sheet__panel--tall`, as the Import and Orphans trays do.
- [x] 6.7 Assert the overflow panel declares a `z-index` above `.gallery-head`'s, in the style `StickyToolbarTest` and `TraySurfaceTest` already use for stacking comparisons.

## 7. Docs and gates

- [x] 7.1 Update `README.md`: Settings sits behind the overflow control in the header on desktop (lines ~127-128 and ~159), and opens as a tray on a phone. Add it to the trays named in the mobile-experience bullet (~line 68) and the "Does it work on mobile?" answer (~line 493).
- [x] 7.2 Check `docs/` for anything that describes the navigation or how Settings is reached, and state explicitly in the commit if nothing there is stale. (`docs/configuration.md` names the Settings *screen*, which is unchanged; the `⋯` references in `docs/testing.md` and `docs/development-workflow.md` are Plex Web and VS Code, not Marquee.)
- [x] 7.3 Run `composer test`, `composer stan`, and `composer cs`. All three must pass before committing.
- [ ] 7.4 Check the desktop header by eye at 960px, in the 641–900px band, and on a phone viewport, with a long `SITE_TITLE` set — the width case this change exists for.
