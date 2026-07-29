## 1. Add the navigation link

- [x] 1.1 In `templates/partials/_nav_macros.html.twig`, add a `support` entry
  to the `ic()` path map — a heart outline using the same 24×24 viewBox and
  `fill="none"` / `stroke="currentColor"` conventions as the existing icons
- [x] 1.2 In the same file, add a Support Development link to
  `secondary_links()` after Orphans:
  `<a class="btn nav-item" href="https://getmarquee.now/#support"
  target="_blank" rel="noopener">` wrapping `.nav-ico` and a `.nav-label` of
  "Support Development", matching the shape of the existing entries
- [x] 1.3 Confirm no edit is needed in `templates/partials/_menu.html.twig` or
  `templates/gallery.html.twig` — both render the macro already

## 2. Add the README section

- [x] 2.1 Add a `## Support Development` section to `README.md` linking to
  <https://getmarquee.now/#support>, placed after Acknowledgements at the end,
  with a sentence on what supporting the project does
- [x] 2.2 Keep the wording consistent with the README's existing voice — a
  plain invitation, no pressure

## 3. Verify

- [x] 3.1 Load the gallery on a desktop width and confirm Support Development
  renders in the toolbar with its icon, opens in a new tab, and does not push
  the toolbar into wrapping or overflow at mid-range widths
- [x] 3.2 Open the menu tray on a narrow screen and confirm the link appears
  between Orphans and Log out, closes the tray when tapped, and opens the
  support page in a new tab
- [x] 3.3 Run with `AUTH_BYPASS` enabled and confirm the link is still present
  while Log out is absent
- [x] 3.4 Confirm the login page — which overrides the navigation block — still
  renders no navigation
- [x] 3.5 Run the PHPUnit suite and PHPStan
