## ADDED Requirements

### Requirement: Poster action controls carry icons
Every control in a poster's action set — Change poster, Send to Plex, Fetch from
Plex, Download, Copy URL, Full screen, and Delete — SHALL present an icon beside
its label, so an action can be found by shape without reading the stack. The icon
SHALL lead the control and the controls SHALL be aligned so their icons form a
single column, since a glyph at a different horizontal position on every row
cannot be scanned.

Labels SHALL be retained on every control, at every viewport width, in both the
pointer-device overlay and the touch action sheet. The icon is an aid to
recognition and SHALL NOT be the only thing identifying a control: each icon SHALL
be hidden from assistive technology, and the control's accessible name SHALL
continue to come from its label.

The icon SHALL NOT increase a control's height. It sits beside the label rather
than above it, so the action stack occupies no more of the card than it does
without icons.

The two Plex actions SHALL be distinguished by direction and by nothing else.
Fetch from Plex SHALL use the same glyph as Import from Plex, because it performs
the same operation on a single item rather than a library. Send to Plex SHALL use
that glyph with its arrow reversed, keeping every other part of the mark
identical, so direction carries the whole distinction between two actions that
move the same image opposite ways and are each irreversible.

The action sheet shown on touch devices SHALL present the same icons as the
pointer-device overlay, since it renders the poster's own action controls.

#### Scenario: Each action control shows an icon beside its label
- **WHEN** a poster's actions are shown on a pointer device
- **THEN** each control displays an icon followed by its text label
- **AND** no label has been removed or shortened to make room

#### Scenario: Icons line up in a column
- **WHEN** the action controls are shown
- **THEN** their icons share a single horizontal position, so the set reads as a
  column rather than as centred rows

#### Scenario: Fetch from Plex matches Import from Plex
- **WHEN** the Fetch from Plex control and the Import from Plex navigation action
  are compared
- **THEN** they display the same glyph

#### Scenario: Send to Plex reverses that glyph
- **WHEN** the Send to Plex and Fetch from Plex controls are compared
- **THEN** their glyphs are identical apart from the direction of the arrow

#### Scenario: The touch action sheet shows the same icons
- **WHEN** a user taps a poster on a touch device and the action sheet opens
- **THEN** each action in the sheet displays the same icon it has in the
  pointer-device overlay

#### Scenario: Icons do not name the controls
- **WHEN** a poster's action controls are read by assistive technology
- **THEN** each control is announced by its label, and its icon is not announced

#### Scenario: Icons do not make the stack taller
- **WHEN** a poster's action stack is rendered with icons
- **THEN** each control occupies the same height it did without one

### Requirement: Poster cards fit their full action stack
A poster card SHALL be tall enough to show its complete action stack without
clipping or scrolling, at every viewport width at which the gallery is shown on a
pointer device. Because a card's height is a fixed ratio of the grid's column
width, the grid's minimum column width SHALL be large enough that the tallest
possible stack — the seven actions shown for a poster linked to Plex — fits
within the resulting card.

This makes precise the sizing already required by "Poster presentation", which
states that posters are sized large enough for the overlay action stack to fit.
Before this requirement that held at most column widths but not the narrowest,
where a linked poster's stack was clipped and the overlay scrolled.

#### Scenario: A linked poster's full stack fits at the narrowest column
- **WHEN** the gallery is viewed on a pointer device at the viewport width that
  produces the narrowest grid columns
- **AND** a poster linked to Plex is hovered, showing all seven actions
- **THEN** every action is visible within the card without the overlay scrolling

#### Scenario: An unlinked poster is unaffected
- **WHEN** a poster with no Plex link is hovered at any viewport width
- **THEN** its five actions are shown in full, as before

#### Scenario: Column count at the full content width is unchanged
- **WHEN** the gallery is viewed at or above the content column's maximum width
- **THEN** the grid shows the same number of columns as it did before this
  requirement
