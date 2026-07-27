## Context

All five items are presentational polish on server-rendered Twig + a single
stylesheet ([public/assets/app.css](public/assets/app.css)) with the theme
tokens defined in `:root` (`--bg #1c1e24`, `--surface #282a2d`, `--ink #e9e6df`,
`--muted #9aa0aa`, `--accent #e5a00d`, `--accent-dim #b9800c`, `--border
#34373d`). No JS, routing, data, or Plex behavior is involved. The only PHP touch
is a display helper on the `Poster` value object; `Poster::title()` must stay
untouched because it also feeds sorting.

## Goals / Non-Goals

**Goals:**
- Retint the four All-view badges so they read as part of the slate + gold theme
  while staying mutually distinguishable.
- Add clear hover/focus feedback to the overlay action buttons.
- Drop the redundant trailing `[library]` token from the visible caption without
  losing it from the tooltip or the on-disk filename.
- Parenthesize the library type in the import picker and center the Step 1 pill
  labels.

**Non-Goals:**
- No change to filenames, import dedup, sort order, routing, or the touch action
  sheet.
- No new palette tokens unless needed; reuse existing ones and `color-mix`.
- No redesign of the overlay layout or badge placement/behavior (hover-hide and
  touch-persist stay as-is).

## Decisions

**Badge palette — four muted, well-separated hues instead of stock primaries.**
Keep the current solid-pill + white-text + subtle white border construction
(good legibility over any poster art), but replace the `.card__badge--*` fills
with four desaturated hues that harmonize with slate + gold and stay clearly
distinct: Movie → gold (accent family, e.g. `#c8871a`); TV Show → muted teal
(`#2f8f7f`); TV Season → desaturated blue (`#3f7bb5`); Collection → muted violet
(`#6d5ac0`). Gold/teal/blue/violet separate cleanly and avoid the previous
Movie-vs-Season amber collision. Values are starting points; the constraint is
white text at legible contrast and four distinguishable hues. _Alternative
considered:_ a single neutral chip with only a colored border — rejected as too
subtle to read the type at a glance over busy poster art.

**Action-button hover/focus — token-driven states, no new markup.** Add
`:hover`/`:focus-visible` rules scoped to `.card__actions .btn`:
- default button → brighten toward the accent (border `var(--accent)`,
  background `color-mix(in srgb, var(--accent) 14%, var(--surface))`, color
  `var(--ink)`);
- `.btn--accent` → deepen to `var(--accent-dim)`;
- `.btn--danger` → tint background with its own red (`rgba(228,120,95,0.16)`) and
  strengthen the border.
Include `:focus-visible` for keyboard parity (outline in `var(--accent)`), plus a
short `transition`. Reuses the same `color-mix`/token idiom already in
`.choice input:checked + span`. _Alternative considered:_ a generic global
`.btn:hover` — rejected to avoid unintended changes to buttons elsewhere in the
app; scope to the overlay.

**Caption trim — a display-only `Poster` accessor.** Add
`Poster::captionTitle()` returning `title()` with a trailing bracketed token
removed via `preg_replace('/\s*\[[^\]]*\]\s*$/', '', ...)`. In
[gallery_results.html.twig](templates/partials/gallery_results.html.twig) the
caption renders `poster.captionTitle` as visible text while `title="{{
poster.title }}"` keeps the full title in the tooltip. `title()` (sort key +
tooltip) is unchanged, so ordering and disambiguation are preserved. _Alternative
considered:_ stripping in the template with a Twig regex — rejected; keeping the
rule in one tested PHP method is cleaner and reusable.

**Single-line caption with ellipsis.** Replace the caption's two-line
`-webkit-line-clamp` with a single-line clamp: `white-space: nowrap; overflow:
hidden; text-overflow: ellipsis; max-width: 100%`. The caption is already a
block the width of the card, so this guarantees it never exceeds the poster width
and never wraps — a long title can no longer add a second line and make its grid
row taller than its neighbours. _Alternative considered:_ keeping two lines then
clamping — rejected; the user wants no wrapping at all, and uneven 1- vs 2-line
captions are exactly what pushed rows down.

**One shared custom tooltip, JS-driven, delegated.** Add a small controller to
[app.js](public/assets/app.js) (loaded on every layout page) that owns a single
`<div class="tooltip">` appended to `body`. It listens on `document` for
`pointerover`/`focusin` (ignoring `pointerType === 'touch'` so touch never gets a
stuck tooltip), reads the target's `data-tooltip`, positions the bubble above the
target — flipping below when there is no room — and clamps it horizontally to the
viewport; it hides on `pointerout`/`focusout`/`scroll`/`Escape`. Rendering at
`body` level sidesteps the caption's own `overflow: hidden`, ancestor stacking
contexts, and viewport-edge clipping that a pure-CSS `::after` tooltip cannot
handle here; event delegation means AJAX pagination and Alpine-rendered previews
work with no re-binding. The bubble is styled from theme tokens in
[app.css](public/assets/app.css) (`.tooltip`). All native tooltips become
`data-tooltip`: poster captions (gallery + orphans), pagination arrows, the Find
Posters preview image, and the wall exit. Icon-only controls that used `title`
as their only name keep an `aria-label` (pagination already has one; the wall's
`×` exit gains one). _Alternative considered:_ a pure-CSS `[data-tooltip]::after`
— rejected because the caption clips its own overflow for the ellipsis and edge
columns would overflow the viewport.

**Wall reuse.** The wall ([wall.html.twig](templates/wall.html.twig)) is a
standalone page that loads neither `app.css` nor `app.js`. To give its exit
button the *same* tooltip, load `app.js` there (its service-worker/version code
no-ops harmlessly) and duplicate the small `.tooltip` block into
[wall.css](public/assets/wall.css) with literal colors (wall.css defines no theme
vars). _Alternative considered:_ leaving the wall on a native tooltip — rejected;
the request is explicitly "anywhere we have tooltips."

**Import type in parentheses.** In [plex.html.twig](templates/plex.html.twig)
render the type label as `({{ library.isMovie ? 'Movies' : 'TV' }})` inside the
existing `.check__meta` span. Pure text change.

**Pill text centering.** Make `.choice span` center its label robustly:
`display: flex; align-items: center; justify-content: center; text-align:
center;` (keeping the pill padding and radius). This guarantees centering
regardless of pill width. _Alternative considered:_ only `text-align: center` —
kept as the minimum, but flex centering also covers the case where pills are
given a shared width later.

## Risks / Trade-offs

- [New badge hues fail contrast over bright posters] → keep white text with the
  existing subtle border and dark tints; verify each hue against a light poster.
- [`captionTitle()` over-trims a real title that legitimately ends in brackets]
  → the regex only strips a single trailing `[...]` segment; the full title stays
  in the tooltip, so nothing is truly lost. Acceptable for a display caption.
- [Scoped hover rules miss a button variant] → cover base, `--accent`, and
  `--danger`; the overlay only uses these three.

## Migration Plan

No data or config migration. Ship template/CSS/PHP edits together; rollback is a
plain revert. Verify by loading the All view (badges, hover states, captions) and
the import screen (parenthesized type, centered pills) on both a pointer and a
touch viewport.

## Open Questions

- _Resolved:_ the "type at the end of the title" is the poster name shown in the
  caption under each poster — i.e. the trailing bracketed library token (e.g.
  `[Movies]`). The caption-trim decision above addresses exactly this.
