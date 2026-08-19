## Context

The settings screen is the application's only real form. Its spacing was written
a rule at a time as the screen grew, and the result is nine distinct vertical
values with no shared origin:

| Between | Set by | px |
| --- | --- | --- |
| heading → first item in a section | `.form-section__title` margin-bottom | 18 |
| section intro prose → first field | `.field:first-of-type` | **0** |
| field → field | `.field` margin-top | 20 |
| label → control, control → hint | `.field` grid gap | 6 |
| checkbox → its own label text | `.field--check` gap | 4 |
| group label → checkbox list | collapsed max(UA `<p>` 1em, 14) | ~16 |
| checkbox list → trailing hint | `.checkbox-list` margin-bottom | 14 |
| stale-exclusion list | `.settings__stale` | 8 |
| section → section | `.form-section` margin-top | 18 |
| save button block | `.settings__actions` | 22 |
| page blocks (back link, `h1`, intro) | browser defaults | ~16–21 |

The zero is the visible defect. `.field:first-of-type { margin-top: 0 }` at
`public/assets/app.css:696` is a **type** selector: `:first-of-type` matches the
first sibling of the same *element type*, which is the first `<div>`, not the
first field. Five of the six sections open with their `<h2>`, so the first
`<div>` genuinely is the first field and the rule works by accident. Auto-import
opens with a `<p class="field__hint">` explaining the section, so its first
`<div>` — the "Import from Plex on a schedule" checkbox — is matched and loses
its 20px. The prose and the control touch.

Two clusters on the screen are hand-assembled composites rather than fields:
"What to import" (`templates/settings.html.twig:84-90`) and the library
exclusions, each a `<p class="field__label">` sitting above a bare
`<div class="checkbox-list">` with a trailing `<p class="field__hint">`. They are
three siblings that read as one question, and nothing in the markup says so —
which is why they space badly and why assistive technology announces the
checkboxes as unrelated controls.

The stylesheet already opens with a design token contract covering elevation,
radius, motion, easing, and translucency, and its own comment states the purpose:
"Component rules draw from these rather than restating literals". Spacing is the
one dimension family that never got the treatment.

## Goals / Non-Goals

**Goals:**

- Every vertical gap on the settings screen comes from a small shared scale.
- The gaps are nested so that section, field, and within-field spacing are
  visibly different, and a reader can find a question boundary without a rule or
  a heading.
- The collapsed Auto-import gap is fixed by removing the class of bug, not by
  patching the one instance.
- The two checkbox clusters become real labelled groups, spaced like fields and
  announced as groups.

**Non-Goals:**

- Retuning spacing anywhere but the settings screen. The scale is added to the
  shared contract and other screens keep their current values; migrating them is
  separate work.
- Horizontal spacing and component padding. `.panel`'s 28px padding, the
  checkbox-list column gap, and every control's internal padding are component
  dimensions and stay as they are.
- Changing any setting, value, label, hint, or control.
- Restructuring the section headings. See "Uniform gap under a heading" below.

## Decisions

### Grid `gap` on the container, not margins on the children

Three containers become `display: grid` and state one gap each:

```
.settings          gap: --space-lg    page blocks
  .settings__form  gap: --space-lg    between panels, and before the save block
    .form-section  gap: --space-md    between fields
      .field       gap: --space-2xs   label ↔ control ↔ hint   (already this)
```

`.field { display: grid; gap: 6px }` already exists — this is the same idiom
applied two levels up, so the screen becomes one pattern nested three deep rather
than a grid inside a pile of margins.

Three properties of `gap` are why it is the right mechanism rather than tidier
margins:

- **It applies only between children.** There is no first-child or last-child
  case, so `.field:first-of-type` is deleted rather than corrected to
  `:first-child`. The defect cannot return when a seventh section is added.
- **Grid children do not collapse margins.** The `max(16, 14)` collapse between
  the group label and the checkbox list stops being something anyone has to
  reason about.
- **It forces the audit.** Grid ignores nothing, so every child's own vertical
  margin must be found and zeroed for the layout to be correct. Leaving
  `.checkbox-list { margin: 14px 0 }` in place would be immediately visible
  rather than silently additive.

*Alternative considered:* the owl selector, `.form-section > * + *
{ margin-block-start: … }`. It reaches the same spacing without changing display
type, so it is the lower-risk edit. Rejected because it keeps margin collapsing
alive between the owl's margin and any child that still declares its own, and
because it does not force the audit — a child with a leftover `margin-top: 14px`
just wins quietly. It also does not match the idiom `.field` already uses.

*Alternative considered:* flexbox column with `gap`. Equivalent for this layout.
Grid chosen for consistency with `.field` and `.checkbox-list`, both already
grids.

### The scale: five steps, each with a consumer

```
--space-2xs:  4px   inside a control cluster (checkbox ↔ its label text)
--space-xs:   6px   within a field: label ↔ control ↔ hint
--space-sm:  10px   rows of a checkbox list
--space-md:  20px   between fields in a section
--space-lg:  28px   between sections, and between page blocks
```

Seeded from values the stylesheet already uses, in the manner the token block's
own comment describes: 4, 6, 10, and 20 are lifted unchanged from `.field--check`,
`.field`, `.checkbox`, and `.field` respectively, so nothing about a field's
internal rhythm moves.

Only one value changes: the gap between sections rises from 18px to 28px, which
is where "comfortable" comes from. It matches `.panel`'s own 28px padding, so the
space between two panels equals the space inside their edges, and the sections
read as separate cards rather than a stack that happens to have borders.

No step is defined without a consumer in this change. The `14px`, `18px`, `22px`,
and `~16px` values in the table above are not preserved as tokens — they are the
inconsistency, and each is replaced by the step above or below it.

### The checkbox clusters become grouped fields

Each cluster becomes one child of the section grid:

```
fieldset.field
  legend.field__label   "What to import"
  div.checkbox-list     the checkboxes
  p.field__hint         "With none of these selected, …"
```

`.field`'s existing `gap: var(--space-xs)` then spaces the group internally at
6px — the same rhythm as a label and its input — and the section grid separates
it from its neighbours at 20px, the same as any other field. No rule specific to
these clusters is needed, which is the point: they stop being special.

**`fieldset`/`legend` was written first and withdrawn during implementation.**
The native pair needs no ARIA, which is why it was the plan. It does not survive
contact with the grid: a `<legend>` is laid out by the browser inside the
fieldset's border rather than as an ordinary child, so it never becomes a grid
item and never takes the field's gap. The usual escape — floating the legend back
into normal flow — is unavailable, because `float` computes to `none` on the
child of a grid container. What remained were browser-specific hacks (a floated
legend plus a clearfix, or a non-grid fieldset wrapping an inner grid and
reintroducing exactly the margin this change removes) bought with markup that
reads slightly better.

So the fallback this document already sanctioned is what shipped: a `<div>` with
`role="group"` and `aria-labelledby` pointing at a `<p class="field__label">`. The
group is announced either way — the spec requires that the label reach assistive
technology, not that a particular element carry it — and the layout needs no rule
of its own.

One knock-on: Twig has no block-passing macro call. `{% call %}` and `caller()`
are Jinja2. The checkboxes are captured by the caller with a `set`/`endset` block
and passed to the macro as rendered markup, which is the idiom Twig offers in its
place.

The markup moves into a `_form.html.twig` macro (`checkbox_group`) rather than
being written twice in the page. Both clusters have the same shape, and
`_form.html.twig`'s header states the reason the file exists: defining a control
in one place is what stops the next form inventing a second vocabulary.

### Uniform gap under a heading

A section's `<h2>` is a child of the section grid like any other, so heading →
first item is `--space-md`, the same as field → field. Typographically a heading
would ideally sit closer to the block it introduces, which would mean wrapping
the heading and its intro prose in a `<header>` — markup churn across all six
sections for a refinement that may not be visible.

Uniform first. If it reads wrong on the built image, the `<header>` wrap is a
follow-up, not a reason to hold this change.

### Test strategy

`tests/Unit/Asset/` already holds sixteen tests that read `app.css` and assert on
its rules; `DesignTokenContractTest` and `DisabledStateTest` are the closest
models. A new `FormRhythmTest` in that pattern asserts:

- every step of the scale is declared on `:root`;
- `.settings`, `.settings__form`, and `.form-section` each declare `display: grid`
  and a `gap` drawn from a token, not a literal;
- **`.field:first-of-type` does not appear in the file** — the regression guard
  that matters, since the bug is a selector that looks correct;
- the three gaps are strictly increasing (`--space-xs` < `--space-md` <
  `--space-lg`), which is the "nesting distinguishes a field from a section"
  scenario expressed as an assertion.

`tests/Functional/SettingsScreenTest.php` gains coverage that both clusters render
as `fieldset` with a `legend`, and that every checkbox in each is inside its
fieldset.

Neither test can catch a *new* section added with hand-rolled spacing. That is
the same shape of hazard as the `aria-disabled` and `PosterUrlFetcher` rules in
`CLAUDE.md`, and the same answer applies: the requirement is written in the spec,
and the token block's comment says where spacing comes from.

## Risks / Trade-offs

**Making `.settings` and `.form-section` grid containers changes layout for every
child, not just their spacing.** → Both currently hold only full-width block
children stacked vertically, so a single-column grid is the layout they already
have. The risk is a child that relied on margin collapsing or on being a block
box; the audit that zeroes the margins is where that surfaces. Verified on the
built `:dev` image before archive, per the project's rule.

**~~`fieldset` is notoriously awkward to style~~ — it fought back, as predicted,
and the fallback shipped.** Resolved during implementation; see the decision
above. `role="group"` + `aria-labelledby` carries the same announcement with no
browser-specific layout hacks.

**Raising the section gap to 28px lengthens the page.** → It is one screen, read
top to bottom and rarely, and the sections are already panels. The added scroll
buys the boundary legibility that is the whole point.

**The scale enters the shared contract while only one screen uses it.** → A
half-migrated contract is a real cost: a reader may assume other screens follow.
Mitigated by scoping the requirement to "a container that stacks content" rather
than "every screen", so nothing else is out of compliance, and by the token
block's comment naming the settings screen as the first consumer.

**`composer cs` may reformat, and PHPStan sees none of this.** → The change is
CSS and Twig; the PHP gates will pass without exercising it. The verification
that matters is visual, on the built image.

## Migration Plan

Not applicable. No data, no stored settings, no routes, and no PHP behaviour
change. The revert is a single commit — the CSS and the two templates.

## Open Questions

None blocking. One deferred, recorded above: whether heading → intro prose wants
a tighter gap than field → field, answerable only by looking at the built image.
