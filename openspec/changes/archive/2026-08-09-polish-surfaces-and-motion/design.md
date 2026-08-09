# Design: Surfaces and motion

## Context

`public/assets/app.css` is 2,029 lines of hand-written CSS. There is no CSS
framework anywhere in the project — the "basic" look is an aesthetic gap, not a
framework to escape. Four measurable shortfalls produce it:

| Gap | Present state |
| --- | --- |
| Elevation | Three `box-shadow` declarations in the whole file — tooltip, toast, and the connection dot's halo. Dialogs, trays, cards, and panels are separated only by a 1px hairline. |
| Motion | About twelve transitions, at seven different hand-picked durations (0.1 / 0.12 / 0.15 / 0.18 / 0.2 / 0.4 / 0.5s), all bare `ease`. `.btn` has no transition at all, so every button hover snaps. Dialogs appear and vanish with no animation; only the mobile sheet slides. |
| Surfaces | `--bg #1c1e24`, `--surface #282a2d`, `--surface-2 #33363d` — roughly 7% lightness apart, near-zero hue, all fully opaque. They read as "slightly different grey", not as layers. |
| Tokens | Nine custom properties, all colour. Radii of 8, 10, 12, and 999px are chosen per rule. There is no shadow, radius, duration, or easing token. |

Two properties of this codebase shape the approach more than anything else.

**The stylesheet's comments are load-bearing.** Rules document why their values
are what they are, and several encode constraints that other specs enforce:
`.grid`'s `minmax(200px, 1fr)` is derived from the seven-action poster stack's
height and carries the note "retune this with the control height, never alone";
`.gallery-head`'s background is annotated as functional rather than cosmetic;
`.card__actions .btn` sizing is what keeps the stack inside the card.

**CSS is under test.** `tests/Unit/Asset/` holds ten "shape tripwire" PHPUnit
tests that parse `app.css` and `gallery.js` and assert on their contents —
`StickyToolbarTest` asserts the literal string `background: var(--bg)` on both
pinned bars under the message "The pinned desktop controls must be opaque."
Restyling is therefore not a free-hand exercise; the tests are the contract and
they must be moved deliberately.

## Goals / Non-Goals

**Goals**

- One token vocabulary that every surface draws from, so the next component
  inherits the look instead of re-deriving it.
- Depth: layered, translucent chrome and a real elevation scale.
- Motion on every state change that currently snaps.
- Reduced-motion coverage that does not need extending per element.

**Non-Goals**

- Any change to what the application does. No route, control, layout, or
  interaction is added, removed, or moved.
- The blurred poster montage behind the gallery — deferred; it adds an image
  request and a mobile performance question, and the flat page it would replace
  turned out to be load-bearing for the pinned controls (see D2).
- Palette hue changes, a second accent, gradient accent fills, typography, the
  spacing rhythm, or icon redraws.
- A CSS build step. No preprocessor, no new dependency, no Node in the runtime
  image.

## Decisions

### D1. Extend the existing `:root` token block rather than introduce a layer system

Tokens are added to the same `:root` block that already holds the nine colours.

*Alternatives considered.* A separate `tokens.css` file was rejected: it adds a
second request on every page for about forty declarations, and the service
worker caches `/assets/` by URL, so two files means two cache entries to reason
about. CSS `@layer` was rejected because the stylesheet's whole override
strategy is source order — the mobile block sits last precisely so it wins at
equal specificity, and its `StickyToolbarTest` helper finds it by string offset.
Layers would silently reorder that.

### D2. Hold `--bg: #1c1e24` fixed, and keep the page flat

The base colour is duplicated in five places outside the stylesheet: the web
manifest (`ManifestController.php`, both `background_color` and `theme_color`),
the `theme-color` meta tag in the layout, the inline header logo SVG,
`logo.svg`, and `favicon.svg`. Changing it means changing all six in step or
introducing a visible seam around the brand mark.

A gradient wash anchored on it was built, shipped to `:dev`, and removed. The
reason is a constraint that only appears once the two are on screen together:
the gallery's pinned controls must be opaque, because they exist to hide the
posters scrolling under them — so they must reproduce the page background
*exactly*, or they read as a rectangle laid on top of it. Against a flat fill
that is free. Against a gradient there is no good answer:

- Leaving the bar flat makes it a visibly different shade from the page.
- Painting the bar the same gradient fails too. Matching requires
  `background-attachment: fixed` on both so each resolves against the viewport,
  and that is unreliable on a `position: sticky` element — browsers composite
  sticky elements into their own layer, and the background then resolves against
  that layer, squeezing the whole gradient into the bar's height.

So the page is one colour. `visual-design` states this as a requirement rather
than leaving it as an implementation detail, because the failure is not obvious
from either rule alone: adding a gradient to `body` looks entirely reasonable
until you notice what it did to the bar two hundred lines away.

*Alternative considered.* Retuning the palette cooler, Overseerr-style, would
mean touching all six locations and re-checking the amber against a new base.
Rejected as scope the user did not ask for — the Plex palette is to be
preserved.

### D3. Translucency is a token pair, not a one-off per surface

Each glass surface takes a tint token plus a blur token, so the strength of the
effect is tunable in one place. Two tints are defined: a lighter one for chrome
that content passes behind (header, pinned controls, tab bar) and a heavier one
for backdrops behind dialogs and trays.

`backdrop-filter` support is handled with `@supports not (backdrop-filter: blur(1px))`,
which restores an opaque fill. Stating the fallback in a negative `@supports`
rather than declaring the opaque colour first and overriding it keeps the
opaque value from being the one a reader sees as the intent, and it is the form
the new `poster-library` scenario ("The pinned controls stay legible without
blur support") is written against.

The tint alone must carry legibility. Blur redistributes luminance but does not
guarantee contrast — a poster with a bright region behind a tab label is exactly
the failure case — so the tint is chosen for contrast against a worst-case
backdrop, and the blur is treated as depth, not as the thing making text
readable.

### D4. Glass by width, which modifies `poster-library`

This is the one place the visual change collides with an existing normative
requirement. "Responsive gallery layout and pinned controls" requires the pinned
controls be opaque so posters are "fully hidden"; glass chrome cannot satisfy
that as written.

The requirement is changed to permit translucency rather than to require it —
MAY, not SHALL — and to say outright that it may differ by width. That wording
is what lets the two form factors diverge, which is where they ended up after
looking at both on a real screen:

- **The narrow-screen toolbar is glass.** It is a narrow bar with content moving
  behind it constantly, and seeing that movement is what keeps it from reading
  as a lid over the grid.
- **The desktop block is opaque.** It is wide, straight-edged, spans the content
  column, and is the frame the gallery is read through rather than something
  floating over it. Glassed, it announced itself every time a poster slid under
  it — the opposite of what chrome is for.

The concerns the original opacity clause existed to protect are kept explicitly:

- **Coverage.** The original guarded against a strip of poster showing through
  the phone toolbar's 14px side channel. The negative margins that fix it stay,
  and the delta keeps a scenario asserting no unsubdued strip at either edge.
- **Blur strength.** Where the surface is translucent, "blurred and dimmed" is
  not enough on its own — a lightly blurred poster still reads as a rendering
  fault. The delta requires that no poster remain individually recognisable.
- **Opacity where it is claimed.** Where the surface is opaque, the delta still
  requires that no poster be visible behind it at all.
- **Layering.** Unchanged; every overlay still covers the pinned controls.

### D5. Elevation is a five-tier scale keyed to the existing z-index ladder

The stylesheet already documents a stacking ladder — pinned controls 30, tab bar
40, trays 50, dialogs 55, viewer 60, toast 80, tooltip/overlay 100. Elevation
tiers are assigned to match it, so depth and stacking never disagree. The
`visual-design` spec requires that agreement precisely because the ladder
already exists and is easy to contradict by picking a shadow by eye.

Shadows are two-layer — a tight contact shadow plus a wide ambient one — because
a single large blur reads as fog against a dark background.

### D6. Dialog exit animation uses Alpine's `x-transition`, not CSS alone

Dialogs and trays are toggled by Alpine `x-show`, which sets `display: none`
outright. A CSS transition cannot animate that, which is why the sheet has only
an entrance `@keyframes` today and no exit at all.

`x-transition` is Alpine's built-in mechanism for exactly this and adds no
dependency — Alpine is already loaded on every page. Each `x-show` overlay gains
transition attributes; durations and easings come from the motion tokens.

The spec requires dismissal not be delayed by its own animation. `x-transition`
satisfies this: the element is `pointer-events: none` on the way out and Alpine
has already flipped the state, so the page behind is live immediately. Exit
durations are kept shorter than entrance durations, which is the usual reading
of "leaving should feel faster than arriving".

*Alternative considered.* Driving exits from CSS via a `.is-closing` class
managed in `gallery.js` was rejected: it reimplements what Alpine already does,
for every one of the fourteen `x-show` overlays, with a timer per overlay to
clear the class.

### D7. Reduced motion becomes one blanket rule

The current handling is two `@media (prefers-reduced-motion: reduce)` blocks
naming specific selectors, which must be extended by hand for every new
animation — the failure the `visual-design` spec calls out directly.

It is replaced by a single universal rule that collapses animation and
transition durations to a near-zero value rather than to `none`. Near-zero
rather than zero is deliberate: `transition: none` suppresses `transitionend`,
and zero-duration animations can skip `animationend`, so any script waiting on
either would hang. Collapsing to `0.01ms` keeps the events firing while removing
all perceptible motion.

Progress indication is exempt, as the spec requires. The spinner and the
lazy-load shimmer keep animating — they communicate that work is happening, and
freezing them would report the opposite. This is why the rule cannot simply be
`* { animation: none }`.

### D8. Card hover lift uses `transform`, never layout

`transform: translateY(-2px) scale(1.02)` plus an elevation shadow. `transform`
does not participate in layout, so the requirement that the grid never reflows
is satisfied structurally rather than by tuning values. The lift is scoped to
`@media (hover: hover)`, matching how the card overlay is already scoped, so a
tap never leaves a phone card stuck in a lifted state.

The overlay's existing `opacity` transition is unchanged and runs concurrently.

## Risks / Trade-offs

**`backdrop-filter` over a scrolling poster grid is the most expensive effect
here, and the pinned controls are on screen for the entire session** → Blur is
confined to a handful of always-present, fixed-size surfaces rather than applied
per card. `will-change` is deliberately *not* set on them: promoting a
permanently visible full-width layer costs memory continuously to save paint
work that only occurs while scrolling. Verify on a real phone against the `:dev`
image; if scrolling degrades, the phone toolbar falls back to opaque via the
same `@supports` path already built for unsupported browsers.

**A padding, radius, or control-height change breaks a documented constraint**
→ The `.grid` minimum is derived from the poster action stack's height, and
`PosterActionStackTest` guards it. The retrofit pass changes colour, shadow,
radius *token references*, and timing only; any change to a control's box
dimensions is out of scope for this change. Where a radius token replaces a
literal, the token's value equals the literal it replaces.

**Moving the two `StickyToolbarTest` opacity assertions could quietly delete
coverage rather than update it** → The replacements assert the tint token *and*
the blur *and* the `@supports` fallback, so the count of assertions on those
rules goes up, not down. The gutter-bleed assertions, which protect a different
failure, are not touched.

**Glass chrome could read as a rendering fault rather than as intent** → This is
a judgement call that only looks right or wrong on a real screen. It is why the
blur strength is specified as "no poster individually recognisable" instead of a
number, and why validation against the `:dev` image is a task rather than an
afterthought.

**Two tints and five elevation tiers is a system that can be under-used** → The
retrofit pass is a task in its own right, not a side effect of the treatment
tasks, so surfaces left on literal values are found rather than assumed absent.

## Migration Plan

No data, schema, configuration, or API changes. Deployment is the ordinary
image build.

The one stateful concern is the service worker: `sw.js` runtime-caches
`/assets/` under `marquee-assets-v2` with a cache-first strategy, so an existing
installed client could hold the old stylesheet after upgrading. The `asset()`
Twig function appends the file's mtime, so an edited `app.css` is a new URL and
defeats the cache by construction — no service worker change is needed. Still
worth confirming during `:dev` validation against an already-installed PWA
rather than a fresh browser.

Rollback is reverting the change. Nothing outside the stylesheet, the transition
attributes, and the two moved test assertions is touched.

## Open Questions

None blocking. Two things are settled only against a real screen and are
therefore tasks rather than questions: the exact blur radius and tint opacity
that make the pinned controls read as deliberate, and whether the glass survives
scrolling on a mid-range phone.
