## MODIFIED Requirements

### Requirement: Responsive gallery layout
The gallery SHALL remain usable on small screens without horizontal overflow:
the category tabs — now including the All tab, five in total — scroll rather than
overflow or crowd the screen, toolbar controls wrap to their own rows, and
posters are sized so at least two fit per row on a phone. On a narrow screen the
tab strip SHALL lay the tabs out in a single horizontal row that scrolls
(rather than a wrapping grid), so adding the All tab does not push tabs off the
edge of the screen or leave an awkward orphaned row.

On a narrow screen the gallery view SHALL stay focused on the posters: the
secondary navigation actions (Poster Wall, Import from Plex, Orphans) SHALL move
out of the gallery toolbar and into the app-wide menu tray, leaving the mobile
toolbar to the primary gallery controls — search and the sort toggle — above the
poster grid. On a pointer/desktop screen those secondary actions SHALL remain in
the toolbar exactly as before.

#### Scenario: No overflow on a phone
- **WHEN** the gallery is viewed on a narrow (phone-width) screen
- **THEN** the tabs, toolbar, and poster grid fit without horizontal page overflow

#### Scenario: Tabs fit on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs are laid out in a scrollable horizontal row without
  overflowing the page width or crowding the other controls

#### Scenario: Secondary navigation is behind the menu on a phone
- **WHEN** the gallery is viewed on a narrow screen
- **THEN** the Poster Wall, Import from Plex, and Orphans actions are not shown in
  the gallery toolbar
- **AND** they are reachable from the app menu tray instead

#### Scenario: Primary gallery controls stay on the phone toolbar
- **WHEN** the gallery is viewed on a narrow screen
- **THEN** search and the sort toggle remain directly available above the grid
  without opening the menu

#### Scenario: Desktop toolbar is unchanged
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the secondary navigation actions render in the gallery toolbar as they
  did before this change
