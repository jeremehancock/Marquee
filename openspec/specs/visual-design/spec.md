# visual-design Specification

## Purpose
TBD - created by archiving change polish-surfaces-and-motion. Update Purpose after archive.
## Requirements
### Requirement: Design token contract

The application SHALL define its visual vocabulary as CSS custom properties on
the document root, and component rules SHALL draw from those tokens rather than
restating literal values. The contract SHALL cover, at minimum: surface colours
including translucent variants, text colours, border colours, an elevation
scale, a corner-radius scale, a spacing scale, and motion durations and easing
curves.

The spacing scale SHALL be the source of the space *between* stacked elements, so
that the rhythm of a screen can be retuned by changing the scale rather than by
finding every rule that sets a margin or a gap. Padding *within* a single
component remains that component's own dimension and is not governed by the
scale.

A literal value MAY remain in a component rule only where it is specific to that
component and would be meaningless as a token — a single element's dimensions,
for example. A value that expresses depth, roundness, timing, surface tint, or
the space separating stacked elements SHALL come from a token.

#### Scenario: A component draws its elevation from the scale

- **WHEN** a floating surface needs a shadow
- **THEN** it references an elevation token rather than declaring its own
  `box-shadow` values

#### Scenario: A component draws its timing from the scale

- **WHEN** a rule transitions or animates a property
- **THEN** its duration and easing come from motion tokens rather than being
  written inline

#### Scenario: A component draws its spacing from the scale

- **WHEN** a rule sets the space separating one stacked element from the next
- **THEN** that space comes from a spacing token rather than a literal value

#### Scenario: Adding a surface requires no new values

- **WHEN** a new panel, dialog, or tray is added to the application
- **THEN** its background, border, radius, elevation, spacing, and transition
  timing are all available as existing tokens, so it matches the surfaces
  already present without introducing new literals

#### Scenario: The rhythm is retuned from one place

- **WHEN** the spacing scale is changed
- **THEN** every screen drawing from it follows, without any component rule
  being edited

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

### Requirement: Form controls share one visual vocabulary

Every form control the application presents — text field, number field, select,
and checkbox — SHALL be styled from one shared vocabulary drawn from the design
token contract, rather than each being styled where it first appears.

Controls SHALL take their surface, border, radius, and text colours from existing
tokens, so a form matches the panels and dialogs around it without introducing new
literal values. A control SHALL be legible in both of the application's surface
tiers, since a form may sit on the page or inside a panel.

A form control is an interactive element and SHALL therefore carry the same
hover, focus, and press feedback every other control carries, including a
keyboard focus indicator not conveyed by colour alone (see "Interactive elements
respond to pointer and focus"). A checkbox SHALL show its checked state by a mark
rather than by colour alone, so that its state survives being wrong about colour.

A field carrying a validation message SHALL identify itself by more than colour —
by the message beside it, associated with the field it concerns.

Where a form presents many related settings, they SHALL be grouped into labelled
sections drawn on the application's existing panel surface, so that a long form
reads as several short ones rather than one undifferentiated column.

Where several controls answer one question together — a set of checkboxes under a
single label — they SHALL form one labelled group rather than a heading followed
by loose controls. The group's label SHALL be associated with the controls it
names, so that assistive technology announces them as choices belonging to one
question. Such a group SHALL be spaced as a single field: its label, its controls,
and any explanation belonging to it SHALL sit at the spacing used within a field,
and the group as a whole SHALL sit at the spacing used between fields.

#### Scenario: A new control needs no new values

- **WHEN** a form control is added to the application
- **THEN** its background, border, radius, and focus treatment are available as
  existing tokens and shared rules

#### Scenario: A control shows keyboard focus

- **WHEN** a text field, select, or checkbox receives keyboard focus
- **THEN** it carries a clearly visible focus indicator, not conveyed by colour
  alone

#### Scenario: A checkbox state is not colour alone

- **WHEN** a checkbox is checked
- **THEN** its state is shown by a mark, so it is distinguishable without
  perceiving the colour

#### Scenario: A refused field is identified beyond colour

- **WHEN** a submitted field is refused
- **THEN** a message naming the problem is rendered with that field, associated
  with it, rather than the field being tinted alone

#### Scenario: A long form reads as sections

- **WHEN** a form presents settings from several unrelated groups
- **THEN** each group is drawn as a labelled section on the shared panel surface

#### Scenario: A set of checkboxes is announced as one question

- **WHEN** a user reaches a set of checkboxes that share a single label
- **THEN** assistive technology announces the label with the group, so each
  choice is heard as belonging to that question rather than as an unrelated
  control

#### Scenario: A grouped set is spaced as one field

- **WHEN** a labelled group of controls sits among ordinary fields in a section
- **THEN** its label sits close to its controls at the within-field spacing, and
  the group is separated from the fields around it at the between-field spacing

### Requirement: An unavailable control looks unavailable

Every interactive element the application switches off SHALL be visibly
distinguishable from the same element when it works. This completes the
interaction-state family: hover, focus, and press are already required of every
interactive element (see "Interactive elements respond to pointer and focus"),
and the unavailable state is the fourth.

The treatment SHALL be drawn from the design token contract, introducing no new
literal values, and SHALL be stated once for every caller rather than at each
control that happens to need it. An emphasised control SHALL surrender its
emphasis while unavailable — a control that keeps its accent fill is still
reading as the thing to press.

The state SHALL NOT be conveyed by transparency alone, since a lowered opacity is
equally readable as "behind something" or "fading in" and says nothing about
whether the control will respond.

An unavailable control SHALL NOT offer hover or press feedback. This is the
substance of the requirement rather than an aside: feedback is the application's
promise that an element will respond, so an element that brightens under the
pointer or moves under a press while doing nothing is worse than one with no
feedback at all. The pointer cursor SHALL likewise indicate that the control will
not respond.

An unavailable control SHALL remain legible. Its label states what the control
would do and is the only thing on screen that does, so the treatment SHALL keep
the label readable rather than reducing it toward the background.

#### Scenario: A switched-off button is distinguishable from a working one

- **WHEN** a button is switched off
- **THEN** it is visibly distinguishable from the same button when it works, and
  an emphasised button no longer carries its emphasis

#### Scenario: A switched-off control does not react to the pointer

- **WHEN** the pointer moves onto a switched-off control on a device that
  supports hovering
- **THEN** its appearance does not change, and the cursor indicates that the
  control will not respond

#### Scenario: A switched-off control does not react to being pressed

- **WHEN** a switched-off control is pressed, by pointer or by touch
- **THEN** it gives no press feedback

#### Scenario: The state survives being wrong about transparency

- **WHEN** a control is switched off
- **THEN** its state is carried by more than a reduction in opacity

#### Scenario: A switched-off control can still be read

- **WHEN** a control is switched off
- **THEN** its label remains legible against the surface it sits on

### Requirement: An unavailable control stays reachable and reports its state

An interactive element the application switches off SHALL remain reachable by
keyboard and SHALL report its unavailable state to assistive technology, rather
than being taken out of the reading and tab order.

A control removed from the tab order cannot be discovered, so a user navigating
by keyboard or screen reader is not told it is unavailable — they are not told it
exists. That is a worse account of the screen than the sighted user gets, and it
is the same failure as the untreated appearance, in another modality.

Switching a control off SHALL NOT move the user's focus. A control that becomes
unavailable in response to being operated is the common case — a button that
starts the work it then guards against being started twice — and a user operating
it by keyboard is focused on it at that moment. Focus SHALL stay where the user
put it.

Because a control that remains reachable also remains operable, the application
SHALL refuse the action at the point that performs it. The visible state SHALL
NOT be the only thing preventing an unavailable control from acting, and this
SHALL hold for every route to that action, including a form submitted without
its own control being operated.

#### Scenario: A switched-off control can still be reached

- **WHEN** a user navigates by keyboard through a screen containing a
  switched-off control
- **THEN** the control is reachable, and its unavailable state is reported rather
  than the control being absent

#### Scenario: Operating a control that switches itself off keeps focus

- **WHEN** a keyboard user operates a control that becomes unavailable as a
  result
- **THEN** focus remains on that control rather than returning to the start of
  the document

#### Scenario: A switched-off control refuses its action

- **WHEN** a switched-off control is operated anyway, by any means
- **THEN** the action does not run, refused where it is performed rather than
  only where it is offered

#### Scenario: A form refuses submission its control would have refused

- **WHEN** a form whose submitting control is switched off is submitted by some
  other route
- **THEN** the submission is refused on the same condition that switched the
  control off

### Requirement: Switching a control off and removing it are different decisions

The application SHALL choose between leaving an unavailable control in place and
omitting it from the screen by one rule rather than case by case.

A control that the user can bring to life by acting on the same screen SHALL be
left in place and shown as unavailable. Leaving it standing is what teaches the
sequence — it names the destination of a multi-step flow, and it keeps a control
in the position a returning user already learned. Removing it takes the
explanation away with it.

A control with nothing to act on in the current state SHALL be omitted instead.
There is no sequence to teach and no reason to give, so a permanently inert
control would be furniture.

A control that is not on screen SHALL NOT be able to affect the outcome of an
action taken on that screen. Where a hidden control's value would still be
submitted, the condition that hides it SHALL be no looser than the condition that
permits the submission, so that anything capable of changing what happens is
visible while it can.

#### Scenario: A control reachable from this screen stays put

- **WHEN** a control is unavailable but the user can make it available by acting
  on the same screen
- **THEN** it remains in place, shown as unavailable, rather than being removed

#### Scenario: A control with nothing to act on is omitted

- **WHEN** a control has nothing to act on in the current state and nothing on
  the screen would change that
- **THEN** it is omitted rather than shown as unavailable

#### Scenario: A hidden control cannot change the outcome

- **WHEN** a control is hidden but its value would still be submitted with the
  form it belongs to
- **THEN** it is hidden only under conditions in which that form cannot be
  submitted

### Requirement: Vertical rhythm is composed from the spacing scale

The application SHALL express the vertical space between elements as the gap a
container states for its children, rather than as margins each child states for
itself. A container that stacks content SHALL declare one spacing value that
applies between its children, and those children SHALL NOT carry vertical
margins of their own.

Spacing SHALL be nested rather than uniform, and the nesting SHALL carry meaning:
a screen spaces its major blocks, a section spaces the questions within it, and a
field spaces the parts of a single question. A reader SHALL therefore be able to
tell a field boundary from a section boundary by the space alone, without relying
on a rule, a heading, or a surface change to mark it.

Because a gap applies only between children, a stacked container SHALL NOT need a
rule that suppresses spacing on its first or last child. Any such rule is a
defect: it depends on the order and element type of the container's children, so
it silently produces the wrong spacing when content is added or reordered.

#### Scenario: A stacked container states the spacing once

- **WHEN** a container stacks fields, sections, or blocks of content
- **THEN** the space between them comes from a single gap declared by the
  container, drawn from the spacing scale, and not from margins on the
  individual children

#### Scenario: Nesting distinguishes a field from a section

- **WHEN** a reader looks at a form containing several sections, each holding
  several fields
- **THEN** the space between two sections is visibly larger than the space
  between two fields, which is visibly larger than the space between a label and
  the control it names

#### Scenario: Reordering content does not change the spacing

- **WHEN** an element is added to, removed from, or moved within a stacked
  container
- **THEN** the spacing between every remaining pair of children is unchanged, and
  no element loses or gains space because of its position among its siblings

#### Scenario: A section that opens with prose is spaced like any other

- **WHEN** a section begins with an explanatory paragraph before its first
  control, rather than beginning with its heading and control directly
- **THEN** the paragraph and the control that follows it are separated by the
  section's own spacing, exactly as two controls in that section would be

### Requirement: A surface that behaves as a dialog declares itself one

Every overlay that covers the page, takes a backdrop, and holds its own controls
SHALL declare `role="dialog"`, `aria-modal="true"`, and an accessible name.

Focus management SHALL be driven by that declaration rather than by a list of
overlays held somewhere else, so an overlay added later is managed by being
marked up correctly rather than by being registered. This includes an overlay
injected into the page at runtime, such as one arriving inside a fetched
fragment.

An overlay holding no focusable content SHALL NOT declare itself a dialog, and
SHALL therefore not be managed: there is nothing to move focus to, and keeping
focus inside it would keep it nowhere.

#### Scenario: A newly added overlay is managed without being listed

- **WHEN** a new modal overlay is added to the application and declares itself a
  dialog
- **THEN** it takes, keeps, and returns focus without any overlay-specific
  wiring being written for it

#### Scenario: An overlay arriving at runtime is managed

- **WHEN** an overlay is injected into the page as part of a fetched fragment and
  then opened
- **THEN** it is managed on the same terms as one present when the page loaded

#### Scenario: An overlay with its own controls is announced as a dialog

- **WHEN** an overlay that offers the user controls is opened
- **THEN** assistive technology reports it as a modal dialog with a name, rather
  than as unlabelled content over the page

### Requirement: An overlay takes focus when it opens

When an overlay opens, the application SHALL move focus into it rather than
leaving focus on the page behind it.

Focus SHALL land on the overlay's own panel, so that the overlay's accessible
name is announced and the user moves forward through its contents in order.
Focus SHALL NOT be placed on the first control the overlay happens to contain: an
overlay whose contents are fetched after it opens has no such control at the
moment focus must move, and an overlay offering a destructive action must not
open with that action under the user's hands.

Moving focus SHALL NOT scroll the page or the overlay.

#### Scenario: An overlay opens

- **WHEN** a user opens an overlay
- **THEN** focus moves into the overlay and its name is reported, rather than
  remaining on the page behind the backdrop

#### Scenario: An overlay whose contents arrive later

- **WHEN** an overlay opens and its contents are still being fetched
- **THEN** focus still moves into the overlay, and the contents become reachable
  by moving forward from there once they arrive

#### Scenario: Taking focus does not move the page

- **WHEN** an overlay opens over a page scrolled away from the top
- **THEN** neither the page nor the overlay scrolls as focus moves in

### Requirement: Focus stays inside the overlay that holds it

While an overlay holds focus, moving forward past its last control SHALL return
to its first, and moving backward past its first SHALL return to its last. Focus
SHALL NOT reach the page behind the overlay.

Focus arriving outside the open overlay by any other route SHALL be returned to
it. This is not a theoretical case: hiding an element that currently has focus
hands that focus to the document, and the application hides focused controls as a
matter of course — a control that switches itself off, or an action row replaced
by the confirmation that follows it. Left alone, the user's next keystroke would
resume at the top of the document, which is the failure this requirement exists
to prevent.

#### Scenario: Moving forward past the last control

- **WHEN** a user moves focus forward from the last control in an open overlay
- **THEN** focus returns to the start of that overlay rather than entering the
  page behind it

#### Scenario: Moving backward past the first control

- **WHEN** a user moves focus backward from the first control in an open overlay
- **THEN** focus moves to the last control in that overlay

#### Scenario: Focus falls out from under the user

- **WHEN** the element holding focus inside an open overlay is hidden or removed
  as a result of the user operating it
- **THEN** focus is returned to that overlay rather than being left on the
  document

### Requirement: Focus returns where it came from when an overlay closes

When an overlay closes, the application SHALL return focus to whatever held it
when that overlay opened.

This SHALL hold for every way an overlay closes — dismissed by keyboard, by its
close control, by its backdrop, by a swipe, by completing the action it offered,
or by the page navigating away — because a user who is returned to their place on
some dismissals and stranded on others is worse served than one who learns the
behaviour is not there at all.

Returning focus SHALL take effect as the overlay begins to close, not when its
exit animation finishes, consistent with dismissal never being delayed by its own
animation.

Where the element that held focus is no longer in the document — the poster card
whose deletion the overlay confirmed, the row the action removed — focus SHALL be
returned to the nearest surviving part of the page that contained it, so the user
resumes near where they were rather than at the top of the document.

#### Scenario: An overlay is dismissed

- **WHEN** a user dismisses an overlay by any means
- **THEN** focus returns to the control that opened it

#### Scenario: An overlay completes its action

- **WHEN** an overlay closes because the action it offered has completed
- **THEN** focus returns to the page rather than being left on the dismissed
  overlay

#### Scenario: The origin of focus no longer exists

- **WHEN** an overlay closes and the element that held focus when it opened has
  been removed from the page
- **THEN** focus is returned to the nearest surviving region that contained it,
  rather than to the start of the document

#### Scenario: The return is not delayed by the exit animation

- **WHEN** a user dismisses an overlay and immediately continues by keyboard
- **THEN** that keystroke acts from the returned position, rather than being
  applied while focus is still inside the closing overlay

### Requirement: Overlays that stack hand focus back in turn

An overlay opened from within another overlay SHALL take focus from it, and SHALL
return focus into the overlay beneath it when it closes, leaving that one still
open and still holding focus.

The order SHALL be the order the overlays were opened in. It SHALL NOT be derived
from their order in the markup or from their drawn stacking order, neither of
which describes which overlay the user reached last.

#### Scenario: An overlay raised from another takes focus

- **WHEN** an overlay is opened from a control inside an overlay that is already
  open
- **THEN** focus moves into the newly opened overlay, and moving through controls
  reaches only that overlay's

#### Scenario: A stacked overlay closes

- **WHEN** the topmost of two open overlays is dismissed
- **THEN** focus returns into the overlay beneath it, which stays open

#### Scenario: Markup order does not decide which overlay holds focus

- **WHEN** an overlay raises another that appears earlier in the page's markup
- **THEN** the overlay the user opened last is the one that holds focus

### Requirement: Every tray presents its title the same distance below its grab handle

Trays that dock to the bottom edge SHALL present their title at one distance
below the grab handle, and that distance SHALL NOT vary with which family of
overlay the tray is built from. A user who opens one tray after another SHALL
see the title land in the same place each time.

Marquee builds bottom trays from two lineages — a tray that only ever exists as
a tray, and a dialog that becomes one at phone width — and both wear the same
grab handle. Because they wear the same handle they read as one component, so
any difference in how they space themselves below it reads as misalignment
rather than as variety.

The distance SHALL be produced by the same declared spacing **and** the same
title type in both lineages. Equal spacing alone does not satisfy this
requirement: a title's leading contributes to the distance the eye measures, so
two heads that declare identical spacing but set their titles at different
sizes or line heights still present the title at different distances.

Where a normalization changes this distance, the value adopted SHALL be the
largest of those already in use, so that no tray's title moves closer to its
handle and no tray's title is set smaller than it was.

#### Scenario: Trays from different lineages agree

- **WHEN** a user opens a tray built as a tray (Sort, Import from Plex,
  Orphaned posters, Settings, Actions, Poster actions) and then one built as a
  dialog docked to the bottom edge (Change poster, a confirmation, Support
  development)
- **THEN** the distance from the bottom of the grab handle to the tray's title
  is the same in both

#### Scenario: The titles are set the same way

- **WHEN** the title of any two trays is compared
- **THEN** both are set at the same size and the same line height, so that the
  space above the title's glyphs contributes equally in each

#### Scenario: Normalizing never tightens a tray

- **WHEN** the shared distance is established from trays that previously
  differed
- **THEN** every tray's handle-to-title distance is greater than or equal to
  what it was, and no tray's title is set smaller than it was

#### Scenario: A new tray inherits the spacing

- **WHEN** a tray is added
- **THEN** it presents its title at the shared distance without declaring
  spacing of its own, and no per-tray exception is needed to place it correctly

### Requirement: A tray head is sized by its title, not by what else it holds

A tray head SHALL take its height from the line its title occupies. Anything
else the head holds — an icon, a mark, a badge, a close control — SHALL be
placed against that line without enlarging it.

A head that lets its tallest occupant set its height moves the title whenever
that occupant changes, which silently breaks the shared distance the previous
requirement establishes. The failure is invisible in the head that causes it:
the head looks balanced on its own, and only reveals itself when the tray is
opened next to another one.

An adornment taller than the title's line SHALL extend equally above and below
that line rather than pushing the title down, and SHALL remain clear of the
grab handle above it.

#### Scenario: An adorned head places its title like any other

- **WHEN** a tray head holds an icon or mark alongside its title, as Support
  development holds its heart
- **THEN** the title sits at the same distance below the grab handle as the
  title of a tray head holding nothing but a title

#### Scenario: An adornment stays level with the title it accompanies

- **WHEN** a head holds an adornment taller than its title's line
- **THEN** the adornment is centred on that line, extending equally above and
  below it, rather than sitting above or below the title

#### Scenario: An adornment does not crowd the handle

- **WHEN** a head holds an adornment that extends above its title's line
- **THEN** a visible gap remains between the bottom of the grab handle and the
  top of the adornment, so the handle reads as a separate affordance

#### Scenario: Changing an adornment does not move the title

- **WHEN** an adornment in a tray head is resized, replaced, or removed
- **THEN** the title stays where it was, and every other tray's title is
  unaffected

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

