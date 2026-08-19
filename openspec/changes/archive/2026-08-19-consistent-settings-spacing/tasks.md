## 1. The spacing scale

- [x] 1.1 Add the five spacing tokens to the `:root` contract in
      `public/assets/app.css`, alongside the radius and motion scales:
      `--space-2xs: 4px`, `--space-xs: 6px`, `--space-sm: 10px`,
      `--space-md: 20px`, `--space-lg: 28px`.
- [x] 1.2 Comment the scale in the voice of the blocks around it: what each step
      is for, that it is seeded from values already in the file, that only the
      section gap changed (18px → 28px, matching `.panel`'s padding), and that it
      governs the space *between* stacked elements — padding inside a component
      is that component's own dimension.
- [x] 1.3 Point the existing rules that already use these values at the tokens:
      `.field` gap → `--space-xs`, `.field--check` gap → `--space-2xs`,
      `.checkbox` gap → `--space-sm`, `.checkbox-list` row gap → `--space-sm`.
      No rendered pixel changes.

## 2. The flow model

- [x] 2.1 Make `.form-section` a grid: `display: grid; gap: var(--space-md)`.
- [x] 2.2 **Delete `.field:first-of-type { margin-top: 0 }`** and `.field`'s
      `margin-top: 20px`. The section grid now owns that space. This is the fix
      for the collapsed Auto-import gap.
- [x] 2.3 Zero the vertical margins of every remaining `.form-section` child so
      the grid gap is the only source of space: `.form-section__title`'s
      `margin: 0 0 18px`, `.checkbox-list`'s `margin: 14px 0`,
      `.settings__stale`'s `margin: 8px 0`, and the UA margins on the bare `<p>`
      elements used as section intros and group labels.
- [x] 2.4 Make `.settings__form` a grid with `gap: var(--space-lg)`, and remove
      `.form-section`'s `margin-top: 18px` and `.settings__actions`'s
      `margin: 22px 0`.
- [x] 2.5 Make `.settings` a grid with `gap: var(--space-lg)`, and zero the UA
      margins on its direct children (the back-link `<p>`, `h1`, the intro
      `p.stats`, the flash `p.alert`). Confirm the trailing superseded-variables
      `div.alert` is spaced by the grid rather than by nothing.
- [x] 2.6 Read the settings screen top to bottom in a browser and confirm every
      gap is one of `--space-xs`, `--space-md`, or `--space-lg`, with no
      collapsed or doubled space left over.

## 3. Grouped checkbox fields

- [x] 3.1 Add a `checkbox_group(id, legend, boxes, hint)` macro to
      `templates/partials/_form.html.twig`. **Changed during implementation:** it
      renders a `<div class="field" role="group" aria-labelledby>` with a
      `<p class="field__label">`, not a `<fieldset>`/`<legend>`. A legend is laid
      out inside the fieldset's border, so it never becomes a grid item or takes
      the field's gap, and `float` — the usual escape — computes to `none` on a
      grid child. The fallback `design.md` already sanctioned. Twig also has no
      `{% call %}`/`caller()` (that is Jinja2), so the boxes are captured by the
      caller with `set`/`endset` and passed in.
- [x] 3.2 ~~Add the `fieldset` reset to `app.css`~~ — **not needed.** With no
      `<fieldset>` there is nothing to reset, and the Plex import screen's
      `.fieldset` is untouched by construction rather than by scoping.
- [x] 3.3 Rewrite the "What to import" cluster in `templates/settings.html.twig`
      (currently a `p.field__label`, a bare `div.checkbox-list`, and a trailing
      `p.field__hint`) using the macro.
- [x] 3.4 Rewrite the library-exclusions cluster the same way, keeping the
      `plex_configured` / `libraries_error` / empty branches intact — only the
      branch that renders the checkbox list becomes a group. Its label reads
      "Libraries to exclude", which the loose markup never had.
- [x] 3.5 Confirm in a browser that both groups sit at `--space-md` from their
      neighbours while their internal parts sit at `--space-xs`, and that the
      library checkboxes still submit as `excluded[]` with unique ids.

## 4. Tests

- [x] 4.1 Add `tests/Unit/Asset/FormRhythmTest.php` in the pattern of
      `DesignTokenContractTest`: assert each spacing token is declared on
      `:root`, and that `.settings`, `.settings__form`, and `.form-section` each
      declare `display: grid` with a `gap` drawn from a token rather than a
      literal.
- [x] 4.2 In the same test, assert `.field:first-of-type` does not appear
      anywhere in `app.css`. Document in the test why: the selector reads as
      "the first field" and means "the first `<div>`", so it is a defect that
      looks correct on review.
- [x] 4.3 In the same test, assert the three gaps are strictly increasing
      (`--space-xs` < `--space-md` < `--space-lg`) — the "nesting distinguishes a
      field from a section" scenario as an assertion.
- [x] 4.4 Extend `tests/Functional/SettingsScreenTest.php`: both clusters render
      as a `role="group"` whose `aria-labelledby` resolves to the group's own
      label, every checkbox in each sits inside its group, and the trailing hint
      is inside the group rather than a sibling of it. (Restated from
      `fieldset`/`legend` — see 3.1.)
- [x] 4.5 Run `composer test`, `composer stan`, and `composer cs`. All three must
      pass.

## 5. Documentation and validation

- [x] 5.1 Check whether `README.md`, `docs/`, or `CLAUDE.md` are made stale by
      this change. Spacing and form grouping are internal presentation; if
      nothing user-facing changed, say so explicitly in the commit rather than
      inventing edits. **Nothing is stale.** No page describes spacing, grouping,
      or the token contract. `README.md:166-173` and `docs/configuration.md`
      describe what each setting does, which is unchanged; the Libraries group's
      new visible label reads "Libraries to exclude", matching README's existing
      "Which Plex libraries to exclude".
- [x] 5.2 Run `openspec validate consistent-settings-spacing --strict`.
- [x] 5.3 Build the Docker image and read the settings screen on the `:dev` image
      at both a desktop width and a phone width. The verification for this change
      is visual — the PHP gates exercise none of it.
- [x] 5.4 Decide the deferred question from `design.md`: does heading → intro
      prose want a tighter gap than field → field? If yes, record it as a
      follow-up rather than widening this change. **Answered: no.** Validated on
      the `:dev` image — the uniform gap reads correctly, so the `<header>` wrap
      is not needed and no follow-up is recorded.
