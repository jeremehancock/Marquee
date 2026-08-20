## 1. Convert the credit to the shared tooltip

- [x] 1.1 In `templates/partials/_attribution.html.twig`, replace `title="…"`
  with `data-tooltip="…"` on all four provider links (TMDB, TheTVDB, fanart.tv,
  TVmaze), keeping each hint's wording and each `alt` exactly as they are.
- [x] 1.2 Extend the partial's header comment to record that the hint is the
  app's tooltip and the accessible name is the logo's `alt` — two names on one
  link is the mistake this prevents, and the file is where a new provider gets
  added.
- [x] 1.3 Confirm `grep -rn 'title=' templates/` returns no native tooltip
  attribute anywhere (`data-confirm-title` and `data-title` are unrelated
  and stay).

## 2. Pin it

- [x] 2.1 In `tests/Functional/ApplicationShellTest.php`, beside the existing
  attribution assertions, assert every provider logo link in the rendered credit
  carries `data-tooltip`.
- [x] 2.2 Assert no logo link in the credit carries a `title` attribute, scanning
  the whole credit block rather than a fixed list of four, so a provider added
  later with the old attribute fails.
- [x] 2.3 Assert each link's `data-tooltip` matches its own logo's `alt`, so the
  tooltip and the accessible name cannot drift apart.
- [x] 2.4 Comment the new assertions with what they defend: the credit is where
  the native tooltip last survived, and these links are image-only, where a
  `title` is easy to reintroduce as if it were the accessible name.

## 3. Verify

- [x] 3.1 Run `composer test`, `composer stan` and `composer cs` — all three
  must pass.
- [x] 3.2 Check `README.md`, `docs/` and `CLAUDE.md` for staleness. Nothing
  user-facing changes in wording or behavior beyond the tooltip's appearance;
  if no edit is warranted, say so explicitly rather than inventing one.
- [ ] 3.3 On a desktop browser, hover each of the four logos in the page footer
  and confirm the themed bubble appears with no native tooltip behind it.
- [ ] 3.4 Narrow the window until the drawer replaces the page footer, open it,
  and confirm the tooltip paints above the drawer rather than behind it.
- [ ] 3.5 Tab to each logo link and confirm the tooltip appears on focus.
