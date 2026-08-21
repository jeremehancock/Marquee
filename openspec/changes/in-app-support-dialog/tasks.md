## 1. The overlay

- [x] 1.1 Create `templates/partials/_support.html.twig`: a `<template x-teleport="body">` wrapping a `.modal` root bound to `supportOpen`, with `x-cloak`, `style="display:none"`, `{{ tx.dialog() }}` from `partials/_transitions.html.twig`, and `@keydown.escape.window="supportOpen = false"`.
- [x] 1.2 Give it a `.modal__backdrop` that closes on click — the drag-to-dismiss handler in `gallery.js` synthesises a backdrop click, so this is what makes the phone gesture work as well as the tap.
- [x] 1.3 Add the panel: `<div class="modal__panel" role="dialog" aria-modal="true" aria-label="Support development" tabindex="-1">`, carrying the `.sheet__grip` / `.sheet__handle` pair every modal in this app carries (hidden on desktop, the drag target on a phone). Default width rather than `--narrow`: 380px sets the paragraph to six lines, 440px to five.
- [x] 1.4 Fill the panel: `.modal__head` with `<h2>Support development</h2>` and the `.modal__close` `×` button; `.modal__body` holding the heart mark via `icons.icon('support', …)`, the support copy ported from `~/github/Marquee-Site/index.html`, and one `.btn.btn--accent` to `https://www.buymeacoffee.com/jeremehancock` labelled "Hard drive fund" with `target="_blank" rel="noopener"`.
- [x] 1.5 Write the partial's header comment: why it is teleported (the `backdrop-filter` containing-block trap, as in `_menu.html.twig`), and why it is not in `_overlays.html.twig` (that file binds against the gallery/orphans overlay component; this one binds against the layout's nav scope and must render on pages that include no `_overlays.html.twig`).

## 2. Wiring it to the navigation

- [x] 2.1 In `templates/layout.html.twig`, add `supportOpen: false` to the `.topnav` `x-data` scope.
- [x] 2.2 Include `partials/_support.html.twig` from `layout.html.twig`, inside `.topnav`, beside the existing `partials/_menu.html.twig` include.
- [x] 2.3 In `templates/partials/_nav_macros.html.twig`, give `item()` an `action` argument: when set, render `<button type="button" class="btn nav-item" @click="{{ action }}">` with the same icon/label/short-label body, the same `aria-label`, and the same `data-tooltip` / `data-tooltip-collapsed` pair the anchor branch carries. Comment why it must be a button rather than an `<a>` with no `href`.
- [x] 2.4 Change the Support entry in `overflow_links()` to use that branch with `supportOpen = true`, dropping its href and `external` flag; update the comment above it, which currently says it opens the project's support page in a new tab.
- [x] 2.5 Remove `'support'` from the list in `overflow_current_class()` and note in its comment that Support is no longer a destination one can be viewing.
- [x] 2.6 Dismissal wiring: both groups already close on any click inside, but neither returned focus, and that turns out to matter now that an entry opens an overlay instead of navigating. Alpine hides the panel on the flush *after* the handler, and hiding a focused element hands focus to `<body>` — which is what the focus manager reads a frame later to decide where to put focus back. Added `$refs.moreTrigger.focus()` to the ⋯ panel's click handler and `x-ref="menuTrigger"` + `$refs.menuTrigger.focus()` to the phone tray's, so the manager has a live origin to remember.

## 3. Presentation

- [x] 3.1 Add a `.support-ask` block to `public/assets/app.css` in this app's tokens (`--accent`, `--surface-2`, `--muted`, `--radius-*`): centred column, accent-tinted tile for the heart mark, heading, muted paragraph, accented button. Do not port the site's raw colour values.
- [x] 3.2 Place it beside the existing `.modal` rules and confirm the mobile `@media (max-width: 640px)` block needs nothing new — the sheet reshaping, the restored grip, and the full-width button sizing already apply to `.modal`.
- [x] 3.3 Rendered the real markup against the real stylesheet in headless Chromium at 1280px, 700px and 390px. Above 640px: centred panel, `×` shown, grip hidden, blurred backdrop. Below: docked to the bottom edge, grip shown, `×` hidden, button full width. The 380px `--narrow` panel was rejected here in favour of the 440px default — see 1.3.

## 4. Removing the old destination

- [x] 4.1 Grepped: no code or template reference to `getmarquee.now/#support` survives outside `openspec/` (the archived changes keep theirs, correctly — they record what was true then). The footer's plain `getmarquee.now` link is untouched.
- [x] 4.2 `README.md`'s "Support Development" section said the only way to give was the website; it now names the in-app ask as well and keeps the site link for readers who have not installed. `README.md:164` (Settings sits beside Support Development in the ⋯ menu) is still true and was left alone. Nothing in `docs/` describes the entry.

## 5. Tests

- [x] 5.1 Add `partials/_support.html.twig` to `DIALOG_TEMPLATES` in `tests/Unit/Asset/DialogFocusTest.php` and `'Support development'` to its `dialogNames()` provider, so the new dialog is held to the focus contract and the manager is pinned against special-casing it.
- [x] 5.2 Update `tests/Functional/GalleryTest.php:390`: drop `getmarquee.now/#support` from the expected-href list.
- [x] 5.3 No existing assertion in `ApplicationShellTest` named the anchor or the external href — all five Support assertions are on the accessible name and the label span, and hold unchanged against the button. The tray's inline regex was folded into the new `menuTray()` helper so the two copies cannot drift.
- [x] 5.4 Add a test that the Support entry is a control opening the overlay rather than a link: it carries no `href`, and both the desktop header and the phone tray drive the same `supportOpen` state.
- [x] 5.5 Add a test that no template links to `getmarquee.now/#support` — the behaviour being removed, and a stray copy would be invisible.
- [x] 5.6 Add a test that the support overlay renders on a page outside the gallery (`/plex` or `/orphans`), since "reachable from every page with navigation" is the requirement most easily broken by putting the partial in the wrong place.
- [x] 5.7 Add a test that the overlay holds the Buy Me a Coffee link with `target="_blank"` and `rel="noopener"`, and that it is the only outbound link in the panel.

## 6. Gates

- [x] 6.1 `composer test`
- [x] 6.2 `composer stan`
- [x] 6.3 `composer cs` (`composer cs:fix` if it reports)
- [x] 6.4 Drove the rendered page in headless Chromium with Alpine and `gallery.js` live: the entry is a `<button>` with no `href`; activating it opens the dialog; focus lands on the panel (`inside=true`); Escape closes it; focus returns to the ⋯ trigger. The counterfactual was measured too — with `$refs.moreTrigger.focus()` removed the same run ends on `<body>`, which is what task 2.6 was added for and what `testAMenuThatOpensAnOverlayHandsFocusBackToItsTrigger` now pins. Left for the user's `:dev` validation: the touch drag-to-dismiss, which needs real touch events.
