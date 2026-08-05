## MODIFIED Requirements

### Requirement: Sort order selection
The system SHALL support two gallery sort fields — **Alphabetical** (article-aware
title order) and **Date added** (by when each poster's media was added to Plex) —
each in either direction, giving four effective orders: titles ascending (A–Z),
titles descending (Z–A), date added newest first, and date added oldest first.
The selected order SHALL apply to both a single category and the aggregate `all`
view.

Each field SHALL have a default direction: ascending for Alphabetical and
descending (newest first) for Date added.

#### Scenario: Alphabetical ascending lists by title
- **WHEN** the effective sort order is Alphabetical ascending
- **THEN** the gallery lists posters by title using the existing article-aware
  ordering, from A to Z

#### Scenario: Alphabetical descending reverses the title order
- **WHEN** the effective sort order is Alphabetical descending
- **THEN** the gallery lists posters by the same article-aware title ordering
  reversed, from Z to A

#### Scenario: Date-added order lists newest first
- **WHEN** the effective sort order is Date added descending
- **THEN** the gallery lists posters by their Plex "added at" timestamp with the
  most recently added poster first

#### Scenario: Date-added ascending lists oldest first
- **WHEN** the effective sort order is Date added ascending
- **THEN** the gallery lists posters by their Plex "added at" timestamp with the
  least recently added poster first

#### Scenario: Sort order applies to the aggregate view
- **WHEN** a user views the `all` slug with any of the four orders
- **THEN** posters from every category are merged and ordered together under that
  order

#### Scenario: Ordering stays deterministic in either direction
- **WHEN** two posters compare equal on the selected field in a mixed listing
- **THEN** the tie is broken so the listing is stable, and reversing the
  direction does not leave equal posters in an arbitrary order

### Requirement: Default sort order configuration
The system SHALL read a preferred default sort order from the `DEFAULT_SORT`
environment variable, accepting `alphabetical` or `date_added`, and SHALL fall
back to Alphabetical when the variable is unset, empty, or holds an unrecognized
value. Each accepted value SHALL select its field in that field's default
direction, so `alphabetical` means A–Z and `date_added` means newest first.

#### Scenario: Default is Alphabetical when unset
- **WHEN** `DEFAULT_SORT` is not set
- **THEN** the gallery orders posters alphabetically from A to Z until the user
  chooses otherwise

#### Scenario: Date-added set as the install default
- **WHEN** `DEFAULT_SORT` is `date_added`
- **THEN** the gallery orders posters by date added, newest first, until the user
  chooses otherwise

#### Scenario: Unrecognized value falls back to Alphabetical
- **WHEN** `DEFAULT_SORT` holds a value other than `alphabetical` or `date_added`
- **THEN** the system uses Alphabetical ascending rather than raising an error

### Requirement: Sort order toggle
The system SHALL present a control in the gallery toolbar with one button per
sort field. Activating the button of the field that is already active SHALL
reverse that field's direction; activating the other field's button SHALL switch
to that field. The resulting order SHALL persist across navigation within the
session, taking precedence over `DEFAULT_SORT`. When the user has not made a
choice, the control SHALL reflect the configured default.

#### Scenario: Toggling re-orders the gallery
- **WHEN** a user selects a sort order from the control
- **THEN** the gallery re-renders its listing in that order

#### Scenario: Activating the active field reverses it
- **WHEN** the gallery is ordered A–Z and the user activates the title button
- **THEN** the gallery is reordered Z–A

#### Scenario: Reversing again restores the original direction
- **WHEN** the gallery is ordered Z–A and the user activates the title button
- **THEN** the gallery is reordered A–Z

#### Scenario: Activating the other field switches to it
- **WHEN** the gallery is ordered Z–A and the user activates the date-added
  button
- **THEN** the gallery is ordered by date added

#### Scenario: Choice persists across navigation
- **WHEN** a user selects date added and then navigates to another category
- **THEN** that category is also ordered by date added without re-selecting it

#### Scenario: Direction persists across navigation
- **WHEN** a user selects Z–A and then navigates to another category
- **THEN** that category is also ordered Z–A without re-selecting it

#### Scenario: Control reflects the configured default before any choice
- **WHEN** a user opens the gallery having made no sort selection this session
- **THEN** the control indicates the field and direction given by `DEFAULT_SORT`

### Requirement: Date-added fallback for posters without a Plex timestamp
When sorting by Date added in either direction, the system SHALL order posters
that have no stored Plex "added at" timestamp using their file modification time,
so every poster holds a stable position in the ordering.

#### Scenario: Poster without a Plex timestamp still sorts
- **WHEN** a poster has no stored Plex "added at" timestamp and the gallery is
  ordered by date added
- **THEN** the poster is ordered by its file modification time rather than being
  omitted or grouped arbitrarily

## ADDED Requirements

### Requirement: Sort direction remembered per field
The system SHALL remember, for the duration of the session, the direction last
used for each sort field independently. Switching to the other field and back
SHALL restore the direction that field was last left in, rather than resetting to
its default direction.

Before a field has been used in the session, its remembered direction SHALL be
its default direction.

#### Scenario: Returning to a field restores its direction
- **WHEN** a user sets titles to Z–A, switches to date added, then switches back
  to titles
- **THEN** the gallery is ordered Z–A rather than A–Z

#### Scenario: Each field remembers its own direction
- **WHEN** a user sets titles to Z–A and date added to oldest first, then
  alternates between the two fields
- **THEN** each field is restored to the direction it was last left in, and
  changing one field's direction does not change the other's

#### Scenario: Unused field starts at its default direction
- **WHEN** a user has only ever used titles this session and switches to date
  added for the first time
- **THEN** the gallery is ordered by date added, newest first

### Requirement: Sort control indicates field and direction
Each button in the sort control SHALL identify its field and show a direction
indicator: an arrow pointing down when that field is running its default
direction, and up when it has been reversed. The title button's label SHALL read
`A–Z` when its direction is ascending and `Z–A` when it is descending; the
date-added button SHALL keep a constant label and convey direction through its
indicator alone.

The indicator SHALL report reversal rather than ascending or descending, because
those do not read alike across the two fields: A–Z is ascending and newest-first
is descending, yet each is the ordinary way to read its own field. Keying the
arrow to reversal means both buttons rest pointing down, and an arrow that has
turned over always carries the same meaning.

The direction indicator SHALL be drawn as one half of the glyph that opens the
phone sort tray, that glyph being a down arrow beside an up arrow, so the control
and its trigger read as one mark whole and halved.

Each button SHALL carry a leading glyph identifying its field, drawn in the
application's existing icon style.

The active button SHALL show the order the gallery is currently in, while
activating it applies the reversed order. Each inactive button SHALL show that
field's remembered direction, which is also the order activating it applies.

Because an arrow alone is not announced by assistive technology, each button
SHALL carry a text alternative, and the same wording SHALL serve as its tooltip
save for the verb naming the action: the tooltip SHALL say "click", appearing as
it does only on hover, and the text alternative SHALL use a device-neutral verb,
being read where there may be no pointer at all.

The active button's wording SHALL both state the order the gallery is in and name
the order activating it applies, those being different orders. Wording that names
only the order the button shows SHALL NOT be used on the active button: phrased as
an instruction it would tell the user to sort the way the gallery is already
sorted, which is the opposite of what activating it does. The inactive button
shows and applies the same order and SHALL name that one order as an instruction.

#### Scenario: Active button says what it is and what it does
- **WHEN** a user reads or hovers the active button while the gallery is ordered
  A–Z
- **THEN** the wording states that the gallery is sorted by title A to Z and names
  Z to A as what activating it applies

#### Scenario: Inactive button names only what it does
- **WHEN** a user reads or hovers an inactive button
- **THEN** the wording names the single order activating it applies, as an
  instruction

#### Scenario: The tooltip names the action as a click
- **WHEN** a user hovers the active button
- **THEN** the tooltip names the action as clicking, a tooltip being reachable
  only with a pointer
- **AND** the button's text alternative names the same action in a way that does
  not assume one

#### Scenario: No button instructs the order it already shows
- **WHEN** any button is read in any of the four orders
- **THEN** it never presents the order it is showing as the order activating it
  would apply

#### Scenario: Active title button shows the current direction
- **WHEN** the gallery is ordered Z–A
- **THEN** the title button is marked active, reads `Z–A`, and shows an upward
  arrow, Z–A being the title field reversed

#### Scenario: Both fields rest pointing the same way
- **WHEN** the gallery is ordered A–Z and the date-added field has not been
  reversed this session
- **THEN** both buttons show a downward arrow, despite A–Z being ascending and
  the date field's default being descending

#### Scenario: Direction indicator matches the tray's trigger
- **WHEN** a user opens the phone sort tray from its trigger
- **THEN** the direction shown on each row inside is the same mark as one half of
  the trigger's own two-arrow glyph

#### Scenario: Activating the active button applies the reverse of its label
- **WHEN** the title button reads `Z–A` and the user activates it
- **THEN** the gallery is reordered A–Z and the button then reads `A–Z`

#### Scenario: Inactive button previews its remembered direction
- **WHEN** the gallery is ordered by date added and the title field was last left
  at Z–A
- **THEN** the inactive title button reads `Z–A`, and activating it orders the
  gallery Z–A

#### Scenario: Date-added button conveys direction by indicator
- **WHEN** the gallery is ordered by date added, oldest first
- **THEN** the date-added button keeps its label and shows an upward arrow, oldest
  first being the date field reversed

#### Scenario: Direction is available as text
- **WHEN** assistive technology reads a sort button
- **THEN** it announces the button's field and direction in words rather than
  only the label, the arrow carrying no announcement of its own

#### Scenario: Control is consistent on phone and desktop
- **WHEN** a user opens the phone sort tray
- **THEN** its buttons carry the same labels, glyphs, direction indicators, and
  toggle behavior as the desktop toolbar control

### Requirement: Selected sort survives paging and category switching
The system SHALL carry the selected sort order — field and direction — in the
links it renders for pagination and for switching category, so neither action
resets the order.

#### Scenario: Paging keeps a reversed order
- **WHEN** a user orders the gallery Z–A and moves to page two
- **THEN** page two is also ordered Z–A

#### Scenario: Switching category keeps a reversed order
- **WHEN** a user orders the gallery by date added oldest first and switches to
  another category
- **THEN** that category is also ordered by date added oldest first
