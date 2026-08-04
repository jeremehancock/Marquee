## Why

A poster's actions are seven identically-shaped grey buttons stacked in a column,
distinguished only by their text. Picking one means reading the stack top to
bottom every time, and the two that matter most — Send to Plex and Fetch from
Plex — are the two whose labels look most alike at a glance. A leading icon gives
each action a shape the eye can find without reading, and it makes the direction
of the two Plex actions visible rather than something to be parsed.

There is a second, smaller reason to touch this now. `Poster presentation`
already requires that posters be sized "large enough for the overlay action stack
to fit". At around an 862px viewport the grid settles into four columns of
exactly 190px, which gives a 285px-tall card against an action stack that needs
295px when a poster is linked to Plex. The stack is clipped and scrolls. The
requirement is already there and is already false; this change is the moment it
is cheapest to make true.

## What Changes

- Every poster action control — Change poster, Send to Plex, Fetch from Plex,
  Download, Copy URL, Full screen, Delete — gains a leading icon beside its
  existing label. No label is removed or shortened.
- The controls become left-aligned so their icons form a single column, matching
  how the menu tray already presents an icon beside a label.
- **Fetch from Plex uses the same glyph as Import from Plex.** They are the same
  operation at different scale — one item versus a library — so they get the same
  mark. **Send to Plex is that glyph with its arrow reversed**, making direction
  the whole difference between the pair.
- The action sheet on touch devices shows the same icons, because it renders the
  card's own action markup.
- The minimum poster column width rises so a seven-action stack always fits its
  card, satisfying the existing sizing requirement at every viewport width rather
  than at most of them.
- The three separate inline icon sets are consolidated into one macro. This
  change would otherwise add a third, and the existing `_icons.html.twig` already
  describes itself as belonging to "the mobile UI" for glyphs the desktop header
  now uses.

## Capabilities

### New Capabilities

None. This change adds requirements to an existing capability.

### Modified Capabilities

- `poster-library`: gains a requirement that poster action controls are presented
  with icons — consistently across the hover overlay and the touch action sheet,
  with the Plex send/fetch pair distinguished by arrow direction and labels
  retained so the accessible name never depends on a glyph. Also gains a
  requirement that a card is always tall enough for its full action stack, making
  the existing sizing clause in `Poster presentation` precise and testable rather
  than aspirational.

## Impact

- `templates/partials/gallery_results.html.twig` — each action control wraps its
  icon and label in spans.
- `templates/partials/_icons.html.twig` — becomes the single icon macro, gaining
  the seven action glyphs and absorbing the five nav glyphs; its docblock is
  corrected.
- `templates/partials/_nav_macros.html.twig` — its local `ic()` macro is removed
  in favour of the shared one.
- `public/assets/app.css` — icon and label layout for action controls in both the
  hover overlay and the action sheet; the grid's minimum column width.
- No PHP, routing, configuration, JavaScript, or data changes. The action sheet
  needs none: it already clones the card's action markup verbatim.
- No new dependencies.
