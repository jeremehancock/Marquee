## ADDED Requirements

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
