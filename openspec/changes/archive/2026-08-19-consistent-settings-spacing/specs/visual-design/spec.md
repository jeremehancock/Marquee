## ADDED Requirements

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

## MODIFIED Requirements

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
