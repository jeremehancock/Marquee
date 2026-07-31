## Why

The poster wall's overlay banners are illegible when the wall is embedded in a
small dashboard frame. In a 350×350 widget the title renders at 7.7px, the
detail line at 5.2px and the "Currently Streaming" badge at 6.2px — present, but
too small to read at the distance you sit from the monitor hosting the
dashboard.

This is a consequence of the previous fix rather than a gap it left. Sizing the
banners as a pure share of the poster assumes the viewer's distance grows with
the display, which holds for a TV across a room, a desktop and a phone in the
hand — and fails for a widget on a dashboard, where the frame shrinks but the
viewer does not move.

## What Changes

- Banner type stops being purely proportional and instead holds a fixed size
  over the range of frames where that size fits, scaling only at the two ends
  where it does not. The fixed sizes are the ones from Posteria, the app whose
  wall this reproduces: 24px title, 19.2px badge, 16px detail.
- Every size inside the banners — font sizes, padding, gaps, the streaming
  dot — is driven from a single custom property so the banner grows and shrinks
  as one unit and cannot drift out of proportion with itself.
- The top banner's letter-spacing and side padding are reduced below the fixed
  range so "Currently Streaming" stays on one line in a narrow frame. The label
  text itself is unchanged.
- Displays at 1080p and above are unaffected; the wall on a 4K TV keeps the
  sizes it has today.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-wall`: the banner sizing requirement currently mandates strict
  proportionality to the poster "at any display size or aspect ratio", with a
  scenario asserting the type occupies the same proportion of the poster on
  every display. As written that forbids this change. It becomes proportional
  above and below a fixed range, with legibility in a small embedded frame as
  the reason for the floor. The accompanying requirement that the banners not
  dominate the poster is unchanged.

## Impact

- `public/assets/wall.css` — the `.wall__banner` rules. No other stylesheet, no
  template, no JavaScript, and no PHP.
- `templates/wall.html.twig` — unchanged; the banner markup already carries the
  classes this needs.
- No change to the wall's routes, its public-access model, its rotation or poll
  cadence, or the now-playing data it renders.
- Visible on every display narrower than 1080p, most notably on phones, where
  the type nearly doubles. That is the intended Posteria sizing and the main
  thing to confirm on the `:dev` image before archiving.
