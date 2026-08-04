## Context

A poster card's actions are built once and rendered twice. The hover overlay on a
pointer device shows `.card__actions` in place; on a touch device
`sheetDetailFor()` in `gallery.js` takes `actions.outerHTML` and hands it to the
action sheet. There is one set of markup and two presentations, already
differentiated by CSS through `.sheet__body .card__actions`.

The overlay is close to its limit. With the values in `app.css` — `0.8rem` text
at the inherited `1.6` line height, `6px` vertical padding, a `1px` border, a
`5px` gap and `12px` overlay padding — each control is about 34.5px and a
seven-action stack needs about 295px. The card frame is `aspect-ratio: 2 / 3`, so
its height is one and a half times the column width, and the grid is
`repeat(auto-fill, minmax(190px, 1fr))` with an 18px gap inside a 912px content
column:

| column width | frame height | 7 actions (linked) | 5 actions |
| --- | --- | --- | --- |
| 190px | 285px | overflows by 10px | fits |
| 214px | 322px | fits, 26px spare | fits |
| 239px | 358px | fits, 63px spare | fits |

Columns are exactly 190px when the content column is `208n − 18` — at n = 4 that
is a 912px viewport minus gutters, reached at roughly 862px. Five columns never
occur, because they would need 1022px against a 912px maximum. So the clipped
case is real and reachable, and `overflow-y: auto` on `.card__overlay` is what
currently hides it.

## Goals / Non-Goals

**Goals:**

- Give each poster action a glyph that identifies it without reading the label.
- Make the direction of the Send/Fetch pair visible rather than parsed.
- Keep all seven actions and all seven labels.
- Guarantee the action stack fits its card at every viewport width.
- Leave one icon macro behind rather than three.

**Non-Goals:**

- Removing, reordering, or demoting any action; no overflow menu, no promotion of
  a subset. The seven are wanted as they are.
- Icon-only controls. Labels stay at every width, in both presentations.
- Changing what any action does, its confirmation, or its wording.
- Changing poster density or the 960px content column.

## Decisions

### Icons sit beside the label, never above it

The overlay has roughly 26px of slack at a typical desktop column width and none
at all at the tight one. Stacking a glyph above its label would add about 20px
per control — 140px across the stack — and turn an edge case into the normal
case.

Beside the label it costs nothing: the glyph is 20px and the control's line box
is already 20.48px, so the flex row's height is unchanged at about 34.5px. This
is the constraint that decides the layout, not a stylistic preference.

*Alternative considered:* icon-only controls in a compact grid, with labels kept
only in the touch sheet. It would reclaim real space and let more of the poster
show through. Rejected as a bigger change than the one being asked for — it
converts a labelled list into something needing tooltips to be usable, and the
seven actions include two irreversible ones where a label is worth its pixels.

### Left-aligned, so the glyphs form a column

Centred controls put each icon at a different horizontal position, which defeats
the purpose — the eye cannot fix on one place and scan. Left alignment with a
leading icon is what makes the column scannable, and it is already how
`.menu__body .btn` presents the navigation tray.

### Fetch takes the Import glyph; Send reverses its arrow

The import glyph settled in the previous change reads as an arrow entering a
boundary, where the boundary is Marquee. Fetch from Plex moves a poster in the
same direction — it is Import narrowed to one item — so it takes the same mark.
Send to Plex moves the poster the other way, and gets the same glyph with the
arrowhead moved to the far end of an identical shaft:

```
Fetch from Plex     ─────▶│        arrowhead at the bar end
Send to Plex        ◀─────│        arrowhead at the far end
                          └ the bar is Marquee, in the same place for both
```

Keeping the shaft and bar identical is deliberate: direction becomes the only
difference, which is exactly the distinction that matters. These are the two
actions the existing markup comments call out as the plausible mis-tap — they
"move the same image in opposite directions" — so the glyphs should differ in
that and nothing else.

*Accepted collision:* Send to Plex lands close to the Log out glyph, which is
also a leftward arrow against a boundary on the right. They differ only in the
boundary's shape, a plain bar against a three-sided bracket. This is accepted
rather than designed around: the two never appear together — Log out is in the
page header, Send is inside a card overlay — and breaking the bar's consistency
with Import to separate them would cost more than the collision does.

*Alternative considered:* anchoring the pair on Plex rather than Marquee, so the
bar means "Plex" and Fetch draws an arrow leaving it. Rejected — it would make
Fetch and Import different glyphs for the same operation, which is the
consistency this decision exists to create.

### Poster columns get a floor tall enough for the whole stack

A seven-action stack needs about 295px, so the frame must be at least that, so
the column must be at least 295 / 1.5 ≈ 197px. Raising the grid's minimum from
190px to 200px clears it with a little room and does not change the column count
at full width — four columns still fit a 912px content column. The only visible
effect is that the four-column threshold moves from roughly an 862px viewport to
roughly 902px; below it, three wider columns are shown instead.

This is a correctness fix for a requirement that already exists rather than a new
idea, and it is separable from the icon work if it turns out to be unwanted.

*Alternative considered:* shrinking the controls so seven fit at 190px. Rejected
— it makes every card worse to serve the narrowest one, and the controls are
already at `0.8rem`.

### One icon macro, not three

`_icons.html.twig` holds six glyphs for the tabs and the sort trigger;
`_nav_macros.html.twig` carries five more in a local `ic()`; this change adds
seven. Three sets of near-identical `<svg>` wrappers differing only in size is
the state to avoid, and the moment to avoid it is while adding the third.

The macro takes a size, since the existing callers differ — 22px for the tab bar,
20px for the nav items — and `_icons.html.twig`'s docblock, which describes its
contents as belonging to "the mobile UI", is corrected: the desktop header has
used those glyphs since the previous change.

### Card-specific class names

The controls need a wrapper for the glyph and one for the label. The nav items
use `.nav-ico` and `.nav-label`, but those names would be untrue on a card, and
renaming them would churn markup and tests that shipped in the previous change
for no behavioural gain.

The card's own `card__*` convention is already established — `card__frame`,
`card__overlay`, `card__actions`, `card__caption`, `card__badge` — so the new
elements follow it. Two conventions that each read correctly, rather than one
that reads correctly in half its uses.

Note that the tooltip helper's `collapsed()` check looks for `.nav-label`, but
nothing here needs it: these labels are visible at every width, so no control
depends on a tooltip to be identifiable.

## Risks / Trade-offs

- **A label wraps once an icon takes its width, making controls taller and
  reintroducing the overflow** → at the 200px floor the control has roughly 132px
  of label space against a longest label ("Fetch from Plex") of roughly 99px, so
  there is headroom. It is thin enough to be worth verifying in a browser rather
  than trusting the arithmetic, and it is the specific thing to look at first.

- **Send and Fetch are still confusable at 20px** → they are deliberately
  identical apart from the arrowhead, which is the point, but it means the
  arrowhead must be legible at that size. The existing confirmation dialogs
  remain the real safeguard; the glyphs are an aid, not a replacement.

- **Send to Plex resembles Log out** → accepted; see the decision above. Worth a
  second look side by side before shipping, since the judgement is that context
  separates them.

- **Raising the column floor changes desktop layout for people who did not ask
  for it** → the effect is confined to a band of viewport widths where the grid
  now prefers three wider columns to four narrow ones, which is also where the
  clipping happens today. Separable from the rest of the change if unwanted.

- **Consolidating the icon macros touches navigation that shipped last change**
  → the move is mechanical and the rendered output is unchanged; the existing
  functional tests assert the header's links and labels and will catch a
  regression.
