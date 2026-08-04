## MODIFIED Requirements

### Requirement: Responsive gallery layout
The gallery SHALL remain usable on small screens without horizontal overflow:
the category tabs — including the All tab, five in total — fit on screen without
scrolling or crowding, toolbar controls fit their row, and posters are sized so
at least two fit per row on a phone.

On a narrow screen the gallery view SHALL stay focused on the posters: the
secondary navigation actions (Poster Wall, Import from Plex, Orphans) SHALL move
out of the gallery toolbar into the app-wide menu tray, and sort SHALL move out of
the toolbar into its own tray, leaving the mobile toolbar to search plus a sort
trigger above the poster grid. On a pointer/desktop screen the secondary actions
and the inline sort control SHALL remain in the toolbar exactly as before.

On a narrow screen the gallery toolbar SHALL remain pinned to the top of the
viewport as the gallery scrolls, so search and the sort trigger are reachable at
any scroll position without returning to the top of the page. The pinned toolbar
SHALL be opaque and SHALL span the full viewport width, so no poster is visible
passing behind or beside it. It SHALL layer above the poster grid and below every
overlay — the bottom tab bar, trays, dialogs, and the fullscreen viewer — so an
open overlay always covers it. On a pointer/desktop screen the toolbar SHALL
continue to scroll with the page.

#### Scenario: No overflow on a phone
- **WHEN** the gallery is viewed on a narrow (phone-width) screen
- **THEN** the tabs, toolbar, and poster grid fit without horizontal page overflow

#### Scenario: Tabs fit on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs all fit on screen without overflowing the page width or
  crowding the other controls (see the native-style bottom tab bar)

#### Scenario: Secondary navigation is behind the menu on a phone
- **WHEN** the gallery is viewed on a narrow screen
- **THEN** the Poster Wall, Import from Plex, and Orphans actions are not shown in
  the gallery toolbar
- **AND** they are reachable from the app menu tray instead

#### Scenario: Toolbar stays available while scrolling on a phone
- **WHEN** a user on a narrow screen scrolls down the gallery
- **THEN** the toolbar stays pinned to the top of the viewport
- **AND** the search field and the sort trigger remain usable without scrolling
  back to the top

#### Scenario: Pinned toolbar hides the posters passing under it
- **WHEN** the gallery is scrolled on a narrow screen with the toolbar pinned
- **THEN** posters scrolling past are fully hidden behind the toolbar, including
  at the left and right edges of the viewport

#### Scenario: Overlays cover the pinned toolbar
- **WHEN** any tray, dialog, or the fullscreen viewer is open on a narrow screen
- **THEN** it renders above the pinned toolbar

#### Scenario: Desktop toolbar is unchanged
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the secondary navigation actions and the inline sort control render in
  the gallery toolbar as they did before this change
- **AND** the toolbar scrolls with the page rather than staying pinned
