## Why

Marquee does everything it needs to do, but it does not yet look like an app
someone chose to install. Surfaces are three near-identical greys separated by a
hairline, dialogs appear and vanish without any sense of arriving, and most
controls do not react to the pointer at all — so the interface reads as generic
rather than as the polished Plex companion it sits beside.

## What Changes

- Introduce a **design token contract** — elevation, radius, motion duration and
  easing, and translucent surface tints — so every surface in the application
  draws from one vocabulary instead of the ad-hoc values currently scattered
  through the stylesheet.
- Give floating chrome a **glass treatment**: the page header, the narrow-screen
  toolbar and tab bar, and the modal and tray backdrops become translucent with a
  blurred backdrop, so content stays visible behind them. The desktop pinned
  block is deliberately excluded — it is wide enough that content moving behind
  it draws attention to the bar rather than through it.
- Keep the page background **one flat colour**. A gradient wash was built and
  then removed: the gallery's pinned controls must be opaque, so they have to
  reproduce the page exactly, and a gradient is not something a sticky bar can
  reproduce reliably. The constraint is now stated as a requirement.
- Establish an **elevation scale** and apply it to everything that floats —
  dialogs, trays, toasts, tooltips, and poster cards on hover.
- Give **motion to state changes** that currently snap: button hover and press,
  dialogs entering and leaving, poster cards lifting under the pointer, and
  category tab selection.
- Make **reduced-motion handling app-wide** rather than a per-selector list that
  must be extended by hand every time an animation is added.
- Keep the Plex palette. The amber accent, the base colour, and the brand mark
  are unchanged; no gradient accents, no hue shift.

Nothing about what the application *does* changes: no route, control, layout,
or interaction is added, removed, or moved.

## Capabilities

### New Capabilities

- `visual-design`: The application's visual language — the design token
  contract (elevation, radius, motion, translucent surfaces), how surfaces are
  layered and separated, which elements respond to pointer and focus and how,
  and the app-wide reduced-motion guarantee. It owns *how things look and how
  they move*; the existing capabilities keep owning what they do.

### Modified Capabilities

- `poster-library`: "Responsive gallery layout and pinned controls" currently
  requires the pinned category tabs and toolbar to be **opaque**, so that "no
  poster is visible passing behind them" and posters scrolling past are "fully
  hidden". Glass chrome is incompatible with that as written. The requirement now
  says the surface MAY be translucent and MAY differ by width, demanding
  *legibility and full coverage of the grid's width* in either case. In practice
  the narrow-screen toolbar takes the glass and the desktop block stays opaque.
  Everything else in the requirement — what is pinned at which width, the
  edge-to-edge span, the layering below every overlay, the keyboard behaviour,
  the short-results case — is unchanged.

No other capability changes. The placeholder-animation and fade-in requirements
in `poster-library` and `poster-sources`, the animated scroll-to-top in `search`,
and the tray, tooltip, and layering requirements in `application-shell` all
describe behaviour that survives as written. The page header and the mobile
bottom tab bar carry no opacity requirement, so glassing them modifies nothing.

## Impact

**Code**

- `public/assets/app.css` — the token block, and a pass over the component
  rules to draw from it. This is the bulk of the work.
- `public/assets/wall.css` — adopts the shared tokens where the poster wall
  overlaps the app's vocabulary.
- Templates are expected to change little or not at all; the treatment is
  carried by existing class names. Any markup change is limited to a wrapper
  needed for a backdrop layer, and to the transition attributes needed for
  dialogs and trays to animate out as well as in.
- `tests/Unit/Asset/StickyToolbarTest.php` — two assertions require the literal
  `background: var(--bg)` on the pinned controls, with the message "The pinned
  desktop controls must be opaque." The phone one moves to asserting the
  translucent tint, its blur, and the `@supports` fallback; the desktop one keeps
  the opacity assertion and gains one that the page background stays flat, since
  the two must match.

**Constraints to respect**

- `app.css` documents *why* several values are what they are, and some are
  load-bearing: the `.grid` 200px column minimum is tied to the seven-action
  poster stack's height, `.gallery-head`'s background keeps the poster grid from
  scrolling through the pinned controls, and `.card__actions .btn` sizing is
  what keeps that stack inside the card. Padding, radius, and control-height
  changes must respect those notes.
- The base colour `#1c1e24` is duplicated in the web manifest, the
  `theme-color` meta tag, the inline header logo, `logo.svg`, and `favicon.svg`.
  Holding it as one flat value keeps all five correct with no arithmetic.
- `backdrop-filter` must degrade to a solid tint where it is unsupported, so no
  surface ever becomes unreadable.

**Not in scope**

- The blurred poster montage behind the gallery. Considered and deferred: it
  adds image loading and a mobile performance question, and the flat page it
  would have replaced turned out to be load-bearing for the pinned controls.
- Any change to the colour palette's hue, the accent, or the brand mark.
- Typography, spacing rhythm, and icon redraws.

**Dependencies**: none added. No build step, no new asset, no runtime cost
beyond compositing.
