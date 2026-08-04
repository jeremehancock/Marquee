## 1. Move the secondary navigation into the shared header

- [x] 1.1 In `templates/partials/_nav_macros.html.twig`, give each link in
      `secondary_links()` both a full label and a short one (Wall, Import, Orphans,
      Support), following the `tab__text` / `tab__text--short` pattern already used
      by the category tabs, and keep the full name as the link's accessible name at
      every width.
- [x] 1.2 In the same macro, mark a link that targets the page currently being
      viewed with `aria-current="page"` and a current-state class, so it is not
      offered as a live self-link on `/plex` and `/orphans`.
- [x] 1.3 In `templates/layout.html.twig`, render `nav.secondary_links()` inside
      `.topnav__desktop`, and switch Log out to `nav.logout_link(true)` so it
      matches the other actions. Keep Log out gated on `auth_bypass`; leave the
      `menu-btn` and the tray include untouched.
- [x] 1.4 Collapse `logout_link(as_button = false)` to a single form now that its
      plain-link branch has no callers.
- [x] 1.5 Remove the `.toolbar__actions` wrapper and its `nav.secondary_links()`
      call from `templates/gallery.html.twig`.

## 2. Pin the gallery controls on desktop

- [x] 2.1 In `templates/gallery.html.twig`, wrap `.tabs` and `.toolbar` in a single
      `.gallery-head` element. Leave `#results` and everything below it outside the
      wrapper, since it is swapped wholesale by no-reload updates.
- [x] 2.2 Add the base `.gallery-head` rule in `public/assets/app.css`:
      `position: sticky; top: 0; z-index: 30;` plus `background: var(--bg)` and
      vertical padding. Comment why the background is load-bearing rather than
      cosmetic, matching the existing note on the mobile toolbar.
- [x] 2.3 Move the separating rule from `.tabs`'s `border-bottom` to
      `.gallery-head`'s bottom edge, so the pinned block is bounded by one rule
      instead of carrying an internal divider.
- [x] 2.4 Add `.gallery-head { display: contents }` inside the existing
      `@media (max-width: 640px)` block, with a comment explaining that a sticky
      element is confined to its containing block and the phone toolbar's own
      pinning would otherwise collapse to the wrapper's height.
- [x] 2.5 Update the z-index ladder comment above `.modal` so tier 30 is described
      as the pinned gallery head on both form factors rather than as the pinned
      mobile toolbar.

## 3. Present the header actions

- [x] 3.1 Reveal the nav icons on desktop by removing the `.nav-ico { display: none }`
      rule, and style `.topnav__desktop`'s actions as a spaced row of icon-and-label
      buttons.
- [x] 3.2 Style the current-destination state from 1.2 as non-interactive, following
      the treatment `tab--active` already establishes.
- [x] 3.3 Add the icon-only band: below roughly 900px and above the 640px phone
      breakpoint, hide the labels and keep the icons, wiring each button's
      `data-tooltip` to its full name.
- [x] 3.4 Verify the labelled row against a long `SITE_TITLE` at the 960px content
      column in a real browser and adjust the 900px changeover if the row crowds —
      the figure is derived from nominal character metrics, not measurement.
- [x] 3.5 Remove the now-unused `.toolbar__actions` rules, both the base rule and
      the `display: none` override in the mobile block.

## 4. Update the tests

- [x] 4.1 Replace `testDesktopToolbarStillScrollsWithThePage` in
      `tests/Unit/Asset/StickyToolbarTest.php` — its assertion is now inverted — with
      coverage that `.gallery-head` is sticky at `top: 0` outside the mobile block,
      is opaque, and sits at tier 30.
- [x] 4.2 Add a test asserting `.gallery-head { display: contents }` inside the
      mobile block, so the phone toolbar's sticky range cannot be silently collapsed
      by the wrapper.
- [x] 4.3 Update the class docblock on `StickyToolbarTest` — it currently explains
      pinning as a phone-only affordance.
- [x] 4.4 In `tests/Functional/ApplicationShellTest.php`, assert the secondary links
      and the button-form Log out render in the header on every page that draws
      navigation, and that the login page still renders none.
- [x] 4.5 Assert that a link targeting the current page is marked with
      `aria-current="page"`, and that the tray still shows full names where the
      header shortens them.
- [x] 4.6 In `tests/Functional/GalleryTest.php`, assert the gallery toolbar no longer
      carries the secondary links, and fix any assertion that reached them through
      the gallery body (the `target="_blank"` check for Poster Wall now belongs to
      the header).

## 5. Verify by hand and close out

- [x] 5.1 On a desktop viewport: scroll the gallery and confirm the tabs and toolbar
      stay pinned, posters are fully hidden behind them, and an open tray, dialog,
      and the fullscreen viewer each cover them.
- [x] 5.2 Confirm a no-match search leaves the block resting below the header rather
      than pinned, and that switching category and paging still return to the top.
- [x] 5.3 On a phone viewport: confirm the toolbar still pins through a full scroll
      of the grid rather than releasing early, the bottom tab bar is unchanged, and
      the menu tray still lists all five actions with their full names.
- [x] 5.4 Check the intermediate band by narrowing a desktop window through ~900px
      and ~700px: labels give way to icons, nothing wraps or overflows.
- [x] 5.5 Check `README.md`, `docs/`, and `CLAUDE.md` for anything the move makes
      stale, and fix it in the same commit — or state explicitly that nothing
      user-facing changed.
- [x] 5.6 Run `composer test`, `composer stan`, and `composer cs`; all three must
      pass before committing.
