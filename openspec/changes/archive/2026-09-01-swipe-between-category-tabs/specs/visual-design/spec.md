## ADDED Requirements

### Requirement: A dragged category is not lifted, scaled or dimmed

A category being moved by a horizontal drag SHALL be presented as a plain
full-width panel sliding horizontally. It SHALL NOT be scaled, raised, shadowed,
or drawn over a dimmed page, and the page behind it SHALL NOT be tinted.

The transform that moves the panels SHALL be horizontal, with no vertical
component.

Depth was tried in the sibling application this gesture comes from and reverted.
Scaling a panel about the centre of the viewport moves everything above that
centre toward it — roughly 23px of immediate, uneased vertical drop for the top
row on a tall phone — which is received as the page glitching rather than as
depth, and it happens before anything has slid anywhere. It is the first thing
the viewer notices.

The dim goes with the scale rather than surviving it. Without a scale the two
panels are each a full viewport wide and sit edge to edge, so no gap between
them ever opens and a dim behind them could never be seen. Keeping it would
leave a rule that cannot render.

#### Scenario: A dragged panel carries no scale

- **WHEN** a category is being moved by a drag or settling after one
- **THEN** its transform SHALL contain no scale, and the panel SHALL be drawn at
  its resting size

#### Scenario: The page behind is not dimmed

- **WHEN** a drag is in progress
- **THEN** no scrim, tint or overlay SHALL be drawn behind or between the moving
  panels

#### Scenario: The movement is horizontal only

- **WHEN** the panels are moved by a drag or a settle
- **THEN** the offset that moves them SHALL be horizontal, with no vertical
  component

### Requirement: A panel taken out of the scroller keeps the box it had in flow

A panel pinned out of the document's scroller for the duration of a drag SHALL
occupy the same position and size it occupied in flow, on both axes, written
from a measurement taken before any style is written for the gesture. Its
contents SHALL sit where they sat.

Taking a box out of flow silently changes three things about its geometry, and
each has been observed as movement in a gesture that should only slide
sideways:

- **It stops inheriting its container's padding.** Pinning to the viewport's
  edges rather than to the measured box widens the grid by that padding when the
  gesture is claimed and narrows it again on release.
- **It stops collapsing margins with its children.** A first child whose top
  margin collapsed through the panel keeps that margin outside it while in flow —
  so the measured box is where the content starts — and regains it inside the
  panel once pinned, dropping everything in it by that margin.
- **It stops occupying its place in the flow.** Two separate things are measured
  from that place. The document's scrollable height comes from it, and a document
  that collapses below the viewer's scroll offset is clamped by the browser,
  which moves every pinned or sticky element resolving against that offset. The
  sticky containing block of any sibling chrome comes from it too — a sticky
  element travels only within its parent's *content* box, so a panel leaving the
  flow collapses that box to the height of the chrome itself, and a viewer
  scrolled past it watches that chrome stop sticking and leave with it.

The application SHALL hold the pinned panel's place in the flow for the duration
of the gesture, with a stand-in of the same height, and SHALL NOT scroll the page
as part of claiming, tracking or abandoning one. Both panels are positioned
against the viewport, so neither needs the page moved to be correct.

Holding the place is required rather than holding the height, and the difference
is not pedantry: padding restores the document's height and leaves the sticky
containing block collapsed, so the scroll stops being clamped and the header
still unsticks. One stand-in for the panel restores both, because it restores
what both are derived from.

The three are one requirement because they share a cause and a symptom: a grid
that moves when a thumb lands, for a reason that appears nowhere in the markup.
Vertical movement in a horizontal gesture is the specific complaint that removed
the lift, and it is no more acceptable at eight pixels than at twenty-three.

The measurement SHALL be taken before the gesture writes anything, and no
measurement SHALL be taken after a write within the same frame. Reading a
property that a just-written style has invalidated forces the browser to redo
its layout there and then, and the frame it lands on is the gesture's first one
— the frame the viewer is judging.

#### Scenario: A pinned panel keeps its in-flow width

- **WHEN** a drag is claimed
- **THEN** each pinned panel SHALL be positioned and sized from its measured
  in-flow box, and the grid SHALL NOT change width

#### Scenario: A pinned panel's contents do not drop

- **WHEN** a drag is claimed on a panel whose first child's top margin collapsed
  through it in flow
- **THEN** that child SHALL stay where it was, and nothing in the panel SHALL
  move vertically

#### Scenario: The page is not scrolled by the gesture

- **WHEN** a drag is claimed from any scroll position, tracked, and then
  abandoned
- **THEN** the page's scroll offset SHALL be unchanged throughout, and the pinned
  and sticky chrome SHALL NOT move

#### Scenario: The measurement precedes the writes

- **WHEN** the gesture sets up its pinning
- **THEN** the box SHALL be measured before any style is written, and no further
  measurement SHALL be taken during setup

#### Scenario: Nothing is measured during tracking

- **WHEN** a drag is being tracked frame by frame
- **THEN** each frame SHALL write the panels' offsets and SHALL read no layout
  property, every value it needs having been captured when the gesture was
  claimed

### Requirement: A gesture the viewer is driving is not suppressed under reduced motion

Under reduced motion the category drag SHALL still follow the viewer's finger.
The settle after release SHALL be effectively instant, and the destination
category SHALL still be correctly rendered and active.

This is a stated exception to the app-wide suppression rather than a gap in it.
That rule removes motion the application performs at the viewer; a panel moving
because a thumb is moving it is not motion done to them, and freezing it would
leave the gesture with no feedback at all rather than with less. What reduced
motion removes here is the part the application animates on its own — the travel
after the finger has lifted.

#### Scenario: The panels still follow the finger

- **WHEN** reduced motion is in effect and a horizontal drag is in progress
- **THEN** the panels SHALL track the touch exactly as they otherwise would

#### Scenario: The settle is instant

- **WHEN** reduced motion is in effect and a drag is released
- **THEN** the panels SHALL reach their resting positions without a visible
  animation

#### Scenario: The outcome is unchanged

- **WHEN** reduced motion is in effect and a drag commits
- **THEN** the destination category SHALL be active and fully rendered, exactly
  as it is with motion enabled

## MODIFIED Requirements

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

Motion a viewer is directly driving with a continuing touch SHALL also be
permitted to continue, for the same reason and no other: it is not motion the
application is performing at them. This covers the tray dismissal drag and the
category drag, both of which move only while a finger is moving them. The
animation each performs once the finger has lifted is not exempt and SHALL be
suppressed.

An exemption SHALL be justified by one of these two accounts — meaning, or the
viewer's own hand — and SHALL NOT be granted because an animation is difficult
to suppress or looks better with it.

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

#### Scenario: A drag under the viewer's finger survives

- **WHEN** reduced motion is in effect and the viewer drags a tray or a category
  panel
- **THEN** it follows the finger as it otherwise would

#### Scenario: What that drag does after release does not

- **WHEN** reduced motion is in effect and such a drag is released
- **THEN** the movement that completes it SHALL be effectively instant, while
  still reaching the correct resting state
