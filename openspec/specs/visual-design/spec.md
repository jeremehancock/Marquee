# visual-design Specification

## Purpose
TBD - created by archiving change polish-surfaces-and-motion. Update Purpose after archive.
## Requirements
### Requirement: Design token contract

The application SHALL define its visual vocabulary as CSS custom properties on
the document root, and component rules SHALL draw from those tokens rather than
restating literal values. The contract SHALL cover, at minimum: surface colours
including translucent variants, text colours, border colours, an elevation
scale, a corner-radius scale, and motion durations and easing curves.

A literal value MAY remain in a component rule only where it is specific to that
component and would be meaningless as a token — a single element's dimensions,
for example. A value that expresses depth, roundness, timing, or surface tint
SHALL come from a token.

#### Scenario: A component draws its elevation from the scale

- **WHEN** a floating surface needs a shadow
- **THEN** it references an elevation token rather than declaring its own
  `box-shadow` values

#### Scenario: A component draws its timing from the scale

- **WHEN** a rule transitions or animates a property
- **THEN** its duration and easing come from motion tokens rather than being
  written inline

#### Scenario: Adding a surface requires no new values

- **WHEN** a new panel, dialog, or tray is added to the application
- **THEN** its background, border, radius, elevation, and transition timing are
  all available as existing tokens, so it matches the surfaces already present
  without introducing new literals

### Requirement: Layered surface elevation

The application SHALL distinguish surfaces by depth as well as by colour. An
elevation scale SHALL assign a distinct shadow to each tier, and every surface
that floats above the page SHALL sit on the tier matching its role: resting
content lowest, then raised content, then trays, then dialogs, then transient
notices. A surface's elevation tier SHALL agree with its stacking order, so a
surface drawn above another never appears to sit beneath it.

#### Scenario: A dialog reads as above the page

- **WHEN** a modal dialog is open
- **THEN** it carries an elevation shadow that separates it from the content
  behind it, rather than being distinguished only by a hairline border

#### Scenario: A tray reads as above the page

- **WHEN** a bottom tray is open on a small screen
- **THEN** it carries an elevation shadow along its raised edges

#### Scenario: Elevation agrees with stacking

- **WHEN** one surface is stacked above another — a confirmation dialog raised
  from inside a tray, for example
- **THEN** the surface on top also carries the higher elevation, so depth and
  stacking tell the same story

### Requirement: Translucent floating chrome

Chrome that floats over content SHALL be translucent with a blurred backdrop, so
that content passing beneath it stays perceptible rather than being hidden by an
opaque band. This SHALL apply to the page header, the narrow-screen pinned
toolbar, the narrow-screen category tab bar, and the backdrops behind dialogs and
trays.

Translucency SHALL be applied where it makes chrome recede and withheld where it
would make chrome assert itself. A narrow bar with content moving behind it gains
from being seen through; a wide, straight-edged block spanning the content column
does not, and the requirement above SHALL NOT be read as obliging every pinned
surface at every width to be translucent.

Legibility SHALL NOT depend on the blur. Every translucent surface SHALL carry a
tint opaque enough that its own text and controls meet their contrast
requirements against any content that may pass behind it.

#### Scenario: Content is visible behind the header

- **WHEN** the page is scrolled beneath the header
- **THEN** the content behind the header is visible through it, blurred and
  dimmed, rather than being covered by a flat band

#### Scenario: The narrow-screen pinned toolbar stays legible

- **WHEN** poster cards scroll beneath the pinned toolbar on a narrow screen
- **THEN** the posters are perceptible through it, and the search field and sort
  trigger remain fully legible

#### Scenario: Chrome that would assert itself stays opaque

- **WHEN** a pinned surface is wide enough that content passing behind it draws
  the eye to the surface rather than through it
- **THEN** it is drawn opaque, and no requirement here obliges it to be
  translucent

#### Scenario: A dialog backdrop blurs the page

- **WHEN** a modal dialog or a tray is open
- **THEN** the page behind it is blurred and dimmed, so attention falls on the
  dialog without the page disappearing entirely

#### Scenario: Blur is unavailable

- **WHEN** the browser does not support backdrop blur
- **THEN** the surface falls back to an opaque tint of the same colour, and no
  text or control becomes harder to read than it is with the blur applied

### Requirement: The page and its opaque chrome share one background

The page background and any opaque chrome resting on it SHALL be the same
colour, so that a pinned or fixed bar is distinguishable from the page only by
its contents and its edge, never by its fill.

This constrains the page background as much as the chrome. Where a surface must
be opaque for a functional reason — the gallery's pinned controls must hide the
posters passing under them — it has to reproduce the page exactly, and a page
background it cannot reproduce is therefore not available. A gradient is the
case that fails: matching it requires the chrome to paint the same gradient
anchored to the viewport, which is not reliable on a sticky element.

#### Scenario: Pinned chrome is not visible as a shape

- **WHEN** the gallery's pinned controls rest over the page at any scroll
  position
- **THEN** no edge of their fill is visible against the page behind them, and the
  block is bounded only by its own border

#### Scenario: The page background stays reproducible

- **WHEN** the page background is chosen
- **THEN** it is something an opaque surface elsewhere in the application can
  reproduce exactly, rather than something that can only be approximated

### Requirement: Interactive elements respond to pointer and focus

Every interactive element SHALL give visible feedback on hover, on keyboard
focus, and on press, and that feedback SHALL be animated rather than applied
instantly. Buttons, links styled as controls, category tabs, and poster cards
are all included.

Hover feedback SHALL be offered only on devices that support hovering, so a
touch device never leaves an element in a stuck hover state after a tap. Press
feedback SHALL be available on every device.

#### Scenario: A button reacts to the pointer

- **WHEN** the pointer moves onto a button on a device that supports hovering
- **THEN** the button's appearance changes over a brief transition rather than
  snapping to its hover state

#### Scenario: A button reacts to being pressed

- **WHEN** a button is pressed, by pointer or by touch
- **THEN** it gives immediate visible press feedback distinct from its hover
  state

#### Scenario: Keyboard focus is as visible as hover

- **WHEN** an interactive element receives keyboard focus
- **THEN** it carries a clearly visible focus indicator, and the indicator is
  not conveyed by colour alone

#### Scenario: Touch does not strand a hover state

- **WHEN** a control is tapped on a device without hover support
- **THEN** no hover styling is left applied after the tap completes

### Requirement: Poster cards lift under the pointer

On devices that support hovering, a poster card SHALL respond to the pointer
with a subtle lift — an elevation and scale change — that resolves over a brief
transition. The lift SHALL NOT change the space the card occupies in the grid,
so no neighbouring card moves and the grid never reflows.

The lift SHALL NOT interfere with the card's action overlay, which continues to
appear on hover as the poster library specifies.

#### Scenario: A card lifts on hover

- **WHEN** the pointer moves onto a poster card on a device that supports
  hovering
- **THEN** the card raises and scales slightly over a brief transition

#### Scenario: The grid does not reflow

- **WHEN** a poster card is lifted
- **THEN** no other card in the grid moves, and the grid's layout is unchanged

#### Scenario: The action overlay still appears

- **WHEN** a poster card is hovered
- **THEN** its action overlay appears as specified by the poster library, and
  the lift does not obscure or displace any action

### Requirement: Dialogs and trays animate in and out

A dialog or tray SHALL animate as it appears and as it is dismissed, rather than
appearing and vanishing instantly. A dialog SHALL fade and scale from slightly
reduced to its resting size; a tray SHALL slide from the edge it is anchored to.
Each SHALL animate its backdrop with it.

The animation SHALL be brief enough that it never delays the user's next action:
a dialog SHALL be interactive as soon as it is on screen, and dismissing one
SHALL take effect immediately even while the exit animation is still running.

#### Scenario: A dialog arrives

- **WHEN** a modal dialog opens
- **THEN** it fades in and scales up to its resting size, and its backdrop fades
  in with it

#### Scenario: A dialog leaves

- **WHEN** a modal dialog is dismissed
- **THEN** it fades and scales out rather than disappearing instantly

#### Scenario: Dismissal is not delayed by its animation

- **WHEN** a user dismisses a dialog and immediately acts on the page behind it
- **THEN** that action is accepted, rather than being blocked until the exit
  animation finishes

#### Scenario: A tray arrives from its edge

- **WHEN** a bottom tray opens on a small screen
- **THEN** it slides up from the bottom edge with its backdrop fading in

### Requirement: Motion is suppressed app-wide under reduced motion

When the user has asked their system to reduce motion, the application SHALL
suppress its decorative motion everywhere, through a rule that applies across
the application rather than through a list of individual elements that must be
extended each time an animation is added.

Suppression SHALL remove the movement, not the state change: an element that
animates between two appearances SHALL still reach the correct appearance, and
every dialog, tray, overlay, and control SHALL remain fully operable. Motion
that carries meaning rather than decoration — a busy indicator showing that work
is in progress — SHALL be permitted to continue.

#### Scenario: A newly added animation is covered without being listed

- **WHEN** a new animated element is added to the application
- **THEN** it is suppressed under reduced motion without any element-specific
  rule being written for it

#### Scenario: State still changes without motion

- **WHEN** reduced motion is in effect and a button is hovered
- **THEN** the button shows its hover appearance immediately, rather than
  showing no change at all

#### Scenario: Overlays remain operable

- **WHEN** reduced motion is in effect and a dialog or tray is opened
- **THEN** it appears in place without animating, and every control inside it
  works as it does otherwise

#### Scenario: Progress indication survives

- **WHEN** reduced motion is in effect and an operation is in progress
- **THEN** the busy indicator still conveys that work is ongoing

### Requirement: The Plex palette is preserved

The application's established colours SHALL be preserved: the amber accent, the
base surface colour, and the brand mark. Visual polish SHALL be achieved through
depth, translucency, and motion rather than by shifting hue, introducing a
second accent, or applying gradients to accented controls.

#### Scenario: The accent is unchanged

- **WHEN** an accented control is displayed
- **THEN** it is drawn in the established amber, as a solid fill rather than a
  gradient

#### Scenario: No second accent appears

- **WHEN** any screen of the application is displayed
- **THEN** no colour outside the established palette is introduced as an accent,
  beyond the status and severity colours already specified elsewhere

### Requirement: Elevation is drawn only where a surface is drawn

A shadow SHALL describe the edge of a surface that is actually painted. Where a
rule removes a surface's background and border for a particular context, it
SHALL remove that surface's elevation in the same place: a shadow with no
surface to cast it draws a rectangle the user cannot see, which reads as a
rendering fault rather than as depth.

This governs every context where an existing surface is restyled rather than
newly authored — most sharply where a component written for a full page is
reused inside a tray, and its panel is flattened so the tray's own surface shows
through instead.

#### Scenario: A panel flattened inside a tray casts no shadow

- **WHEN** a panel authored for a full page is reused inside a tray and drawn
  without its own background and border, so the tray's surface shows through
- **THEN** it carries no elevation shadow either, and nothing outlines the
  rectangle it no longer occupies

#### Scenario: The same panel on its own page keeps its elevation

- **WHEN** that panel is shown on its own page, with its background and border
  drawn
- **THEN** it keeps the elevation of its tier, because there the shadow traces a
  real edge

### Requirement: Content reused inside an overlay sheds its page chrome

A component authored as pinned page chrome SHALL NOT carry its pinning,
translucent tint, gutter bleed, or stacking order with it when the same content
is reused inside a tray or dialog. Those values are solved against the page's
scroll container, background, and gutters, and none of the three is the same
inside an overlay.

An element inside a tray SHALL NOT declare a stacking order that places it above
that tray's own progress overlay. A tray's overlay is deliberately ranked below
the app-wide overlay scale so that it covers only the tray, and any element that
outranks it is drawn over the spinner rather than under it — receiving neither
the dim nor the blur that would otherwise apply to it, because a backdrop filter
affects only what is drawn behind.

Where the same markup serves both a full page and a tray, the safe resolution is
for the reused content to carry no positioning at all rather than to be
re-tuned per context.

#### Scenario: A reused bar spans the tray it is in

- **WHEN** a bar of controls authored for a full page is shown inside a tray
- **THEN** it is laid out against the tray's own padding, with no strip of the
  tray's surface left uncovered beside it and no band of the page's colour laid
  over the tray's

#### Scenario: A tray's progress overlay covers everything in the tray

- **WHEN** a progress overlay is shown inside a tray while previously loaded
  content is still on screen beneath it
- **THEN** every part of that content is drawn beneath the overlay, dimmed and
  blurred by it, including any bar of controls

#### Scenario: Reopening a tray does not expose stale controls

- **WHEN** a tray that has already loaded is reopened and refreshes its contents,
  leaving the previous result visible while the refresh runs
- **THEN** the whole previous result reads as being refreshed, with no element of
  it standing above the progress overlay as though it were still live

