## 1. Update the footer links

- [x] 1.1 In `templates/layout.html.twig`, change the page-footer anchor's
  `href` from `https://marquee.dumbprojects.com` to `https://getmarquee.now`,
  leaving `target="_blank"`, `rel="noopener"`, the link text, and the version
  and update-note markup untouched
- [x] 1.2 In `templates/partials/_menu.html.twig`, change the drawer-footer
  anchor's `href` the same way, keeping `@click="menuOpen = false"` and the
  other attributes as they are

## 2. Update the test

- [x] 2.1 In `tests/Functional/ApplicationShellTest.php`, update the
  `assertStringContainsString('href="https://marquee.dumbprojects.com"', ...)`
  assertion to expect `https://getmarquee.now`

## 3. Sweep for stragglers

- [x] 3.1 Run `grep -rn "dumbprojects" . --exclude-dir=.git
  --exclude-dir=vendor --exclude-dir=openspec/changes/archive` and update any
  remaining live reference (README, docs, Docker labels, manifest) to the new
  domain; leave archived change artifacts alone
- [x] 3.2 Confirm the only remaining hits are under
  `openspec/changes/archive/`

## 4. Verify

- [x] 4.1 Run the PHPUnit suite and confirm `ApplicationShellTest` passes
- [x] 4.2 Run PHPStan and PHP-CS-Fixer to confirm the change is clean
- [x] 4.3 Render a page and confirm both the page footer and the drawer footer
  link to `https://getmarquee.now` in a new tab
