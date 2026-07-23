## 1. Frontend

- [x] 1.1 Rewrite `plex.html.twig` into a two-step form (type first, then
      compatible libraries) with reset-on-type-change and a gated Import button.
- [x] 1.2 Add content-type chip styles to `app.css`.

## 2. Verify

- [x] 2.1 Render check: type chips + libraries present; Import gating intact.
- [x] 2.2 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 2.3 `openspec validate import-stepper --strict` passes.
