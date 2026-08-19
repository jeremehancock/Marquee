## Why

The settings screen does not read as one page. Its vertical gaps come from nine
different values, and in one place the gap disappears entirely: the Auto-import
section's explanatory paragraph and the checkbox beneath it are touching, so the
control reads as part of the sentence above it rather than as the first question
in the section. Nothing about the screen tells a reader where one question ends
and the next begins, which is the whole job of spacing in a form.

## What Changes

- The design token contract gains a spacing scale. Elevation, corner radius,
  motion, easing, and translucency are all already scales; spacing is the one
  family of dimensions still written as scattered literals.
- The settings screen's vertical rhythm comes from that scale. Three nested
  containers each space their own children — the page, the section, and the
  field — so every gap on the screen is one of three values, and a reader can
  tell a field boundary from a section boundary by the space alone.
- A set of related checkboxes under one label ("What to import", the library
  exclusions) becomes a single labelled group rather than a loose heading above
  loose controls. The label is announced with the group, so a screen reader
  hears four choices belonging to one question instead of four unrelated
  checkboxes.
- The collapsed gap in Auto-import is fixed, and the class of bug behind it is
  removed rather than patched: spacing is stated between siblings, so there is
  no "first item" case left to get wrong when a future section is added.

No setting changes meaning, no value changes, and nothing on the screen moves
except the space around it.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `visual-design`: the design token contract extends to cover a spacing scale;
  a new requirement governs how vertical rhythm is composed from it; and the
  shared form vocabulary gains the rule that related controls under one label
  form a single labelled group.

The settings screen's markup changes, but no `settings` requirement does — the
settings capability governs what is configurable and how it is stored, not how
the screen is spaced or how its controls are grouped.

## Impact

- `public/assets/app.css` — spacing tokens added to the `:root` contract;
  `.settings`, `.settings__form`, and `.form-section` gain grid flow; the
  per-element vertical margins they replace are removed, including the
  `.field:first-of-type` rule that causes the collapsed gap.
- `templates/settings.html.twig` — the "What to import" and library-exclusion
  clusters become grouped fields.
- `templates/partials/_form.html.twig` — gains a macro for a labelled group of
  checkboxes, so the two clusters are not hand-assembled in the page.
- `tests/Unit/Asset/` — a new CSS-reading test in the established pattern,
  pinning the spacing tokens and the flow model.
- `tests/Functional/SettingsScreenTest.php` — extended for the grouped fields.

No PHP behaviour, no routes, no stored settings, and no dependencies are
touched.
