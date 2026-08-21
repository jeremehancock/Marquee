## Context

Bottom trays are built from two lineages that share a grab handle and nothing
else about their heads.

`.sheet__panel` is a tray at every width — Sort, Import from Plex, Orphaned
posters, Settings, the Actions (⋯) menu, Poster actions. Its head is
`.sheet__head` (base rule, [app.css:2959](../../../public/assets/app.css#L2959))
and its title is a `<span class="sheet__title">`
([app.css:2971](../../../public/assets/app.css#L2971)).

`.modal__panel` is a centred dialog above 640px and a bottom tray below it —
Change poster, Delete all orphans?, the generic confirm, Support development.
Its head is `.modal__head`, which has a base rule
([app.css:2439](../../../public/assets/app.css#L2439)) carrying no padding at
all, and a second rule inside the `@media (max-width: 640px)` block
([app.css:3360](../../../public/assets/app.css#L3360)) that supplies the tray
padding. Its title is an `<h2>` styled by `.modal__head h2`
([app.css:2445](../../../public/assets/app.css#L2445)).

Both wear `.sheet__grip` + `.sheet__handle`, so the handle's bottom edge is
17px below the panel's top in every tray (12px grip padding + a 5px handle).
Everything below that diverges. Measured at a 16px root:

| | pad-top | title type | line box | half-leading | row height | **handle → glyph** |
| --- | --- | --- | --- | --- | --- | --- |
| `.sheet__head` | 14px | 16px / 1.3 | 20.8px | 2.4px | 20.8px | **16.4px** |
| `.modal__head` (mobile) | 2px | 17.6px / 1.6 | 28.2px | 5.28px | 28.2px | **7.3px** |
| `.support-ask__head` | 2px | 17.6px / 1.6 | 28.2px | 5.28px | **40px** | **13.2px** |

Support is a third value because `.support-ask__mark` is a 40px tile in a
`1fr auto 1fr` grid row. The tile — not the title — sizes the row, so the title
centres 5.92px lower than it otherwise would. The tile's painted top edge lands
2px under the handle, which is what made the drift noticeable.

Three constraints carried in from the existing design:

1. The mark must stay **on the head row**. The archived `in-app-support-dialog`
   change records that putting it on its own line below the head failed — it
   read as belonging to neither the heading it names nor the copy it
   introduces. `.support-ask__head`'s comment also records that taking the mark
   out of flow was tried and rejected, because an absolutely positioned child
   resolves against the padding box while its in-flow siblings centre in the
   content box.
2. The `1fr auto 1fr` grid must survive. It is what actually centres the
   heading against a 40px tile on one side and a text glyph on the other, and
   what keeps it centred on a phone where the close control is `display: none`
   but its track is not.
3. Padding inside a component is that component's own dimension and does not
   come from the spacing scale — the `Design token contract` requirement says
   so explicitly. The 14px is a literal and stays one.

## Goals / Non-Goals

**Goals:**

- One handle-to-title distance across all ten trays.
- That distance is the largest of the three that exist today, so no tray
  tightens and no title shrinks.
- A tray head's height comes from its title, so the next head that adds an icon
  or badge does not silently move its own title out of alignment.
- The invariant is pinned by a test, in the established shape-tripwire family.

**Non-Goals:**

- The divider asymmetry (`.sheet__head` has `border-bottom` + 12px
  `margin-bottom`; `.modal__head` has neither). Out of scope, stated in the
  proposal.
- Desktop dialogs. The Support dialog above 640px keeps its 40px head row.
- Giving `.sheet__title` heading semantics.
- Introducing a typography scale to the token contract. The current contract
  covers colour, elevation, radius, spacing, and motion — not type — and
  extending it is a larger decision than this change should make.

## Decisions

### 1. Normalize to the `h2` type at 14px padding, not the sheet type

The user's instruction was to adopt whichever option produces the largest gap.
Both candidates were measured at the sheets' 14px padding:

| Combination | handle → glyph | Effect on titles |
| --- | --- | --- |
| sheet type (16px / 1.3) | 16.4px | four dialog titles shrink 17.6px → 16px |
| **`h2` type (17.6px / 1.6)** | **19.3px** | six tray titles grow 16px → 17.6px |

The `h2` wins on both counts. Its `line-height: 1.6` contributes 5.28px of
half-leading above the glyph against the span's 2.4px, and adopting it means no
title anywhere gets smaller — which is the reading of "largest" that stays true
of the titles as well as the gap.

Resulting distances: sheets 16.4 → 19.3px, modals 7.3 → 19.3px, Support
13.2 → 19.3px.

### 2. `.sheet__title` changes its type, not its element

It takes `font-size: 1.1rem` and drops the `line-height: 1.3` override so it
inherits the body's 1.6, matching `.modal__head h2` exactly. It stays a
`<span>`.

Alternative considered: change the markup to `<h2>` in all six sheet templates
and delete `.sheet__title` entirely, letting `.modal__head h2` serve both. That
is tidier CSS, but it bundles an accessibility decision (do tray titles want
heading semantics, and at what level, given the panel already carries an
`aria-label`) into a spacing change, and touches six templates for a change
that is otherwise CSS-only.

This is a base rule, so it applies above 640px too. A `.sheet` opened at a wide
viewport gets a 1.6px larger title. Accepted — `.sheet` is not display-gated,
but its triggers are phone chrome, so this is close to unobservable.

### 3. The Support head's row is one title line tall, via `1lh`

The mark stops sizing the row:

```
.support-ask__head {            /* inside @media (max-width: 640px) */
    font-size: 1.1rem;          /* the title's type, declared on the head */
    grid-template-rows: 1lh;    /* one line of that type */
}
```

`1lh` resolves against **the element it is used on**, not against the grid item
that happens to hold the title. That is the trap: written on a head whose own
font-size is the inherited 16px, `1lh` computes 25.6px rather than 28.16px, and
the row is 2.5px short — enough to move the title out of alignment while
looking entirely reasonable in the source. Declaring `font-size: 1.1rem` on the
head is what makes `1lh` mean "one line of the title", and it also lets the
`h2` inherit its size instead of restating it.

With a 28.16px row and a 40px tile under the grid's existing
`align-items: center`, the tile overflows 5.92px above and below the row rather
than growing it:

```
                                       clearance today: 2.0px
panel top ────────── 0
                            grip padding-top 12
handle top ───────── 12
handle bottom ────── 17     ┐
                            │ 8.1px clearance
mark top ─────────── 25.1   ┘
head content top ─── 31.0   ┐  head padding-top 14
title glyph top ──── 36.3   │  ← 19.3px below the handle
head content bot ─── 59.2   ┘  row = 28.2px
mark bottom ──────── 65.1      overflows into the 12px bottom padding
head box bottom ──── 71.2      no clipping
```

The tile keeps its 40px size, stays centred on the line its heading occupies,
and gains 6px of clearance under the handle as a side effect.

Alternatives considered:

| Approach | Why not |
| --- | --- |
| `grid-template-rows: calc(1.1rem * 1.6)` | Restates both type values a third time; drifts silently if either changes. |
| Tokenize the type as `--tray-title-size` / `--tray-title-leading` | Correct, but adds a typography axis to a token contract that has none, which is a bigger decision than this change. |
| `position: absolute` on the mark | Explicitly rejected in the existing rule's comment — it resolves against the padding box and breaks the vertical centring. |
| Shrink the mark to 28px | Fixes the row by making the tile no taller than the line, but discards a deliberate 40px dimension to solve a layout problem. |
| Negative block margins on the mark | Cancels the overhang with a literal that has to be recomputed whenever the type changes. |

`lh` is Baseline (Chrome 109, Safari 16.4, Firefox 120). The stylesheet already
ships `color-mix()` unconditionally — including in `.support-ask__mark` itself
— which has a comparable support floor, so this introduces no new class of
requirement.

### 4. Scope the row pin to the mobile block

`.support-ask__head` is a base rule today. Pinning the row there would shrink
the desktop dialog's head by ~12px and pull its body up, which the proposal
lists as a non-goal. The pin therefore goes inside `@media (max-width: 640px)`,
where the tray requirement actually applies.

The cost is that the mark is centred on a 40px row above 640px and on a 28.2px
row below it. The rendered result is nearly identical — in both cases the tile
is vertically centred against its heading — so this is a difference in
mechanism, not appearance.

### 5. The test asserts clearance, not coordinates

The new test belongs in `tests/Unit/Asset/` beside `AlertGlyphClearanceTest`
and `TraySurfaceTest`, and follows their shape: read `public/assets/app.css`,
strip comments (the stylesheet names selectors in prose), match rules by
regex, assert relationships rather than rendered pixels.

`AlertGlyphClearanceTest` is the direct precedent — it already does
cross-selector arithmetic, asserting that `.alert` pads further from the left
than its `::before` glyph reaches. The concern here is the same shape rotated
90°: **a head must pad further from the top than its adornment overhangs.**

Four assertions:

1. `.sheet__head` and the mobile `.modal__head` declare the same `padding-top`.
2. `.sheet__title` and `.modal__head h2` resolve to the same `font-size`, and
   `.sheet__title` declares no `line-height` of its own, so both inherit the
   body's.
3. `.support-ask__head` inside the mobile block declares both a `font-size` and
   a `grid-template-rows`, and the row is expressed in `lh` — pinning the
   derivation, not a number, so re-tuning the type cannot break it.
4. The head's `padding-top` exceeds half the difference between
   `.support-ask__mark`'s height and one line of the head's type. This is the
   clearance assertion: it fails if the mark grows, if the padding shrinks, or
   if the type gets smaller.

One hazard specific to this test: **`.modal__head` matches twice.** The base
rule at 2439 and the mobile rule at 3360 have identical selectors, and
`AlertGlyphClearanceTest`'s `rule()` helper returns the first match — which is
the rule carrying no padding at all. The test must slice the
`@media (max-width: 640px)` block out first and match within it. Same for
`.support-ask__head`, which will exist in both scopes once decision 4 lands.

## Risks / Trade-offs

- **The test reads CSS with regular expressions.** → Accepted, with precedent:
  three existing tests in `tests/Unit/Asset/` do the same, and the repo has no
  JS test runner or headless browser to compute styles with. The mitigation is
  the one those tests use — assert relationships and shapes, never rendered
  coordinates, so the assertion survives re-tuning and fails only on the
  mistake it was written for.
- **`.modal__head` matching twice.** → Called out above; the test slices the
  media block first. Worth a comment in the test, since the failure mode is a
  silent pass against the wrong rule.
- **`1lh` on the wrong element computes a plausible wrong number.** → The
  `font-size` declaration on the head is what makes it correct, so assertion 3
  requires both to be present in the same rule.
- **Six sheet titles get 1.6px larger.** → Intended, and the direction the
  user chose. Verified by eye on the `:dev` image before archiving.
- **Nothing here can be verified by test alone.** → The gaps are arithmetic
  over declared values; whether the result *looks* right needs a phone against
  the `:dev` image. Same standing caveat `TraySurfaceTest` records for itself.

## Open Questions

- Should the desktop Support dialog eventually adopt the same row pin, so the
  head contract is width-independent? Deferred, not blocking — decision 4
  scopes it to the mobile block for now.
- The divider asymmetry is left open by design. If the trays still read as two
  families after this lands, that is the next thing to look at, and it is a
  product decision rather than a spacing one.
