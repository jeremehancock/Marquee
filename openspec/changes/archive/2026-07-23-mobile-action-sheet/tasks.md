## 1. Frontend

- [x] 1.1 Add a bottom action sheet (Alpine state + markup) that shows a tapped
      poster's actions, reusing the card's action markup via event delegation.
- [x] 1.2 `gallery.js`: on touch, open the sheet; on desktop, open full screen;
      close the sheet after an action, backdrop tap, or Escape.
- [x] 1.3 CSS: hover-only overlay on pointer devices, hidden on touch; sheet
      styling; two-column tab grid on small screens.

## 2. Verify

- [x] 2.1 Gallery renders the sheet wiring; existing suite stays green.
- [x] 2.2 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 2.3 `openspec validate mobile-action-sheet --strict` passes.
