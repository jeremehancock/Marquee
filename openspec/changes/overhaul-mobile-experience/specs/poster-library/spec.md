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

#### Scenario: No overflow on a phone
- **WHEN** the gallery is viewed on a narrow (phone-width) screen
- **THEN** the tabs, toolbar, and poster grid fit without horizontal page overflow

#### Scenario: Secondary navigation is behind the menu on a phone
- **WHEN** the gallery is viewed on a narrow screen
- **THEN** the Poster Wall, Import from Plex, and Orphans actions are not shown in
  the gallery toolbar
- **AND** they are reachable from the app menu tray instead

#### Scenario: Desktop toolbar is unchanged
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the secondary navigation actions and the inline sort control render in
  the gallery toolbar as they did before this change

### Requirement: Native-style category tab bar on small screens
On a narrow screen the category tabs SHALL be presented as a fixed, always-visible
bottom tab bar in which all five tabs fit the screen at once — each tab an icon
above a short label — rather than a scrolling row, so switching categories feels
like a native app tab bar. The gallery content SHALL reserve space so the tab bar
never hides the last posters or the footer. On a pointer/desktop screen the tabs
SHALL remain text tabs in their original position. Each tab SHALL retain its full
category name as its accessible name regardless of which presentation is shown.

#### Scenario: All tabs fit at once in a bottom bar on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs are shown as a fixed bottom bar of equal-width columns that all
  fit on screen without scrolling, each an icon over a short label
- **AND** content is not hidden behind the bar

#### Scenario: Active tab is indicated
- **WHEN** a category is active on a phone
- **THEN** its tab is visually highlighted as the current one

#### Scenario: Desktop tabs are unchanged
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the tabs render as the text tabs used before this change, in their
  original position

### Requirement: Infinite scroll on small screens
On a narrow screen the gallery SHALL load posters by infinite scroll instead of
pagination: it SHALL append the next page of posters as the user nears the bottom
of the current results, continuing until the last page is reached, so the whole
library becomes reachable by scrolling without loading it all at once. The
pagination controls SHALL be hidden on a narrow screen and SHALL remain on a
pointer/desktop screen.

#### Scenario: Scrolling loads more posters
- **WHEN** a user on a narrow screen scrolls near the bottom of the current
  posters and more pages exist
- **THEN** the next page of posters is appended below without a manual page change

#### Scenario: Loading stops at the last page
- **WHEN** the last page of posters has been appended
- **THEN** no further loading is attempted

#### Scenario: Desktop keeps pagination
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the pagination controls are shown and used as before

### Requirement: Import and orphans run inside their trays on small screens
When the import or orphans experience is opened in a tray on a phone, it SHALL be
fully contained: running an import or deleting orphans SHALL happen in place
without navigating away, progress SHALL be shown contained within the tray rather
than as a full-screen overlay, and the result SHALL be reported to the user. After
an import completes the gallery SHALL reflect the newly imported posters, and after
orphans are deleted the gallery SHALL reflect their removal.

#### Scenario: Import completes without leaving the tray
- **WHEN** a user submits the import form inside the import tray
- **THEN** progress is shown within the tray, the import runs without navigating to
  another page, the result is reported, and the gallery reflects any new posters

#### Scenario: Deleting orphans stays within the tray
- **WHEN** a user deletes one or all orphans from the orphans tray
- **THEN** progress is shown within the tray, the deletion happens without
  navigating away, and the result is reported

#### Scenario: Tray progress is contained
- **WHEN** an import or orphan operation is running inside a tray
- **THEN** its progress indicator is confined to the tray rather than covering the
  whole screen

### Requirement: Sort selection via a tray on small screens
On a narrow screen the sort control SHALL be presented as a tray opened from a
sort trigger in the toolbar, offering the same sort orders (Alphabetical and Date
added) as the desktop control and indicating the current order, so the toolbar
stays uncluttered.

#### Scenario: Sort trigger opens the sort tray
- **WHEN** a user on a narrow screen activates the sort trigger
- **THEN** a tray opens offering Alphabetical and Date added, with the current
  order indicated

#### Scenario: Choosing an order sorts the gallery
- **WHEN** the user chooses a sort order from the tray
- **THEN** the gallery is ordered accordingly

### Requirement: Import from Plex via a tray on small screens
On a touch device viewing the gallery, choosing Import from Plex SHALL open the
import experience in a tray over the gallery rather than navigating to a separate
page, loading the same Plex import form used by the import page. On a pointer
device, or on a page without the gallery, Import from Plex SHALL navigate to the
import page as before.

#### Scenario: Import opens in a tray on a phone
- **WHEN** a user on a touch device taps Import from Plex from the gallery menu
- **THEN** the import form opens in a tray over the gallery without navigating away

#### Scenario: Import navigates on desktop
- **WHEN** a user on a pointer device chooses Import from Plex
- **THEN** the import page opens as a normal page

#### Scenario: Submitting the import still works from the tray
- **WHEN** a user completes and submits the import form inside the tray
- **THEN** the import runs and the user is returned to the gallery with the result
  reported
