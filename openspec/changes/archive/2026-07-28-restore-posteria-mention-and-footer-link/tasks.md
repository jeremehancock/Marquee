## 1. Footer links

- [x] 1.1 In [layout.html.twig:54](templates/layout.html.twig#L54), wrap
  `{{ app_name }}` in an anchor to `https://marquee.dumbprojects.com` with
  `target="_blank"` and `rel="noopener"`; leave `· v{{ app_version }}` and the
  `.js-update-note` span outside the anchor.
- [x] 1.2 Apply the identical anchor to `{{ app_name }}` in the drawer footer at
  [_menu.html.twig:24](templates/partials/_menu.html.twig#L24), again leaving
  the version and `.js-update-note` span outside it.
- [x] 1.3 Add a `.footer a, .menu__footer a` rule next to `.footer` in
  [app.css:90](public/assets/app.css#L90): inherit the surrounding muted color,
  no underline at rest, underline on `:hover`, and a visible `:focus-visible`
  outline.
- [x] 1.4 Load a page and confirm the page footer still reads
  `Marquee · v<version>`, the product name opens the project site in a new tab,
  and the version text is not clickable.
- [x] 1.5 At a mobile width, open the navigation drawer and confirm its footer
  reads the same, the product name opens the site in a new tab with the drawer's
  page still loaded behind it, and the link is visually distinct from the nav
  items rather than reading as one.
- [x] 1.6 Update `testFooterAndHomeScreenLabelNameTheProductNotTheSiteTitle` in
  `tests/Functional/PwaTest.php`, which pins the old footer markup, and add a
  test in `tests/Functional/ApplicationShellTest.php` covering the new spec
  scenarios: both footers link the product name, with `target="_blank"` and
  `rel="noopener"`.

## 2. README wording

- [x] 2.1 In the README intro paragraph, name posteria.app as the poster search
  service behind "pick a replacement from an online poster search".
- [x] 2.2 In the **Edit posters in place** feature bullet, name posteria.app in
  the **Find Posters** sub-bullet.
- [x] 2.3 In **Acknowledgements**, credit posteria.app as the hosted poster
  search service Find Posters uses.
- [x] 2.4 Trim the **Find Posters** FAQ answer to a plain statement of where
  results come from, dropping the aggregation detail and the "point it at your
  own instance if you self-host the service" aside.
- [x] 2.5 Re-read every posteria.app passage in the README and confirm none of
  them describe endpoints, request/response shapes, how the service aggregates
  results, or how to self-host it, and that the `POSTER_SOURCE_URL` row is left
  as a plain configuration entry.

## 3. Verification

- [x] 3.1 Run `openspec validate restore-posteria-mention-and-footer-link` and
  fix any reported issues.
- [x] 3.2 Run the test suite (`composer test` / `phpunit`) and PHPStan to
  confirm the template change breaks nothing.
- [x] 3.3 Check the login page renders correctly and that `/wall` (standalone
  template, no footer) is unchanged.
