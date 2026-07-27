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

**Caption trim — library-name-driven, sourced from the DB.** The trailing token
is the Plex library name, which import bakes into the filename; the file-safe
sanitiser flattens the `(year)`/`[library]` structure to underscores, so there is
no bracket to strip and the token is otherwise indistinguishable from title words.
The reliable source of the library name is the existing `plex_items` table (it
stores `library_title` keyed by category + filename). So
`PlexItemRepository::librariesForCategory()` returns `filename => library_title`;
[GalleryController](src/Controller/GalleryController.php) builds one map per
category (always — the filename carries the library even when Plex is currently
unconfigured) and passes `plex_libraries` to the template.
`Poster::captionTitle(?string $library)` normalises the library name the same way
the filename was (`[^A-Za-z0-9._-]`→`_`, then `_`/`.`→space) and strips it only
when it is the exact trailing token, so the year survives and non-Plex posters
(null library) keep their full title. The caption text and its `data-tooltip` both
use this trimmed title; `title()` (sort key) is untouched. _Alternative
considered:_ stripping a bracket in the display layer (the first attempt) —
rejected because the sanitiser already destroyed the brackets, so it stripped
nothing. _Alternative considered:_ using the DB's clean `title` column directly —
rejected because it omits the year users expect in the caption.

**Mobile sheet keeps the library in parentheses.** `Poster::sheetTitle(?string
$library)` does the same match but rewrites the trailing token to ` (Library)`
instead of dropping it. The card exposes it as `data-sheet-title`; the touch
controller in [gallery.js](public/assets/gallery.js) already titled the sheet from
the caption's text, so it now prefers `data-sheet-title` and falls back to that
text. This is the one place the library is intentionally shown.

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
`data-tooltip`: poster captions (gallery + orphans), pagination arrows, and the
Find Posters preview image. Icon-only controls that used `title` as their only
name keep an `aria-label` (pagination already has one). _Alternative considered:_
a pure-CSS `[data-tooltip]::after` — rejected because the caption clips its own
overflow for the ellipsis and edge columns would overflow the viewport.

**Wall shows only posters.** The wall is for unattended display on a monitor, so
its on-screen exit control is removed entirely (`.wall__exit` markup and styles);
a viewer leaves by closing the tab. Because that was the wall's only tooltip, the
wall keeps loading neither `app.js` nor the tooltip CSS — the custom tooltip stays
scoped to the main app. _Alternative considered:_ keeping the exit but hiding it
until hover — rejected; the user wants no chrome on the wall at all.

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
- [`captionTitle()` drops a real title word that happens to equal the library
  name] → it only strips the library when it is the *exact trailing* token
  (` <library>` at the end), so an interior "Movies" is kept; a title whose real
  last word is the library name is a rare, low-cost cosmetic case.
- [A uniqueness suffix (`…-1`) sits after the library token] → then the token is
  not the exact ending and is left in place; rare (only on name collisions) and
  cosmetic.
- [Library map adds a DB query per category on every gallery load] → one indexed
  lookup on `plex_items` per category, same pattern as the existing `linked`/
  `addedAt` maps; negligible.
- [Scoped hover rules miss a button variant] → cover base, `--accent`, and
  `--danger`; the overlay only uses these three.

## Migration Plan

No data or config migration. Ship template/CSS/PHP edits together; rollback is a
plain revert. Verify by loading the All view (badges, hover states, captions) and
the import screen (parenthesized type, centered pills) on both a pointer and a
touch viewport.

## Open Questions

- _Resolved:_ the "type at the end of the title" is the poster name shown in the
  caption under each poster — the baked-in library name (e.g. "… 2003 Movies").
  The library-name-driven caption-trim decision above addresses exactly this, and
  the mobile sheet keeps it in parentheses.
- Orphans: the orphans grid still shows the filename-derived title (it has no
  `plex_libraries` map, and orphans are unlinked posters under review). Left as-is
  unless the library should be trimmed there too.
