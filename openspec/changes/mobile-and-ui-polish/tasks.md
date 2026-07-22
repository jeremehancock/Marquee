## 1. Auth-aware logout

- [x] 1.1 Expose an `auth_bypass` Twig global; hide the logout link when set.

## 2. Labelling

- [x] 2.1 Rename the found-poster apply action to "Select".

## 3. Mobile layout

- [x] 3.1 Mobile media query: scrolling tabs, wrapping toolbar, smaller poster grid.
- [x] 3.2 Tap-to-reveal overlay (hidden + non-interactive by default; hover on
      desktop, tap on touch); remove the always-visible touch overlay.

## 4. Verify

- [x] 4.1 Functional: logout hidden when bypassed, shown when auth enabled.
- [x] 4.2 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 4.3 `openspec validate mobile-and-ui-polish --strict` passes.
