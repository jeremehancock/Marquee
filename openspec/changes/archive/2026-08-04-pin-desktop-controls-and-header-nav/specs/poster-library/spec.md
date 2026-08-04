## ADDED Requirements

### Requirement: Responsive gallery layout and pinned controls
The gallery SHALL remain usable on small screens without horizontal overflow:
the category tabs — including the All tab, five in total — fit on screen without
scrolling or crowding, toolbar controls fit their row, and posters are sized so
at least two fit per row on a phone.

The gallery view SHALL stay focused on the posters at every width: the secondary
navigation actions (Poster Wall, Import from Plex, Orphans, Support Development)
SHALL NOT be presented in the gallery toolbar. On a pointer/desktop screen they
SHALL be presented in the shared page header instead (see "Secondary navigation in
the desktop page header" in `application-shell`); on a narrow screen they SHALL be
presented in the app-wide menu tray. On a narrow screen sort SHALL additionally
move out of the toolbar into its own tray, leaving the mobile toolbar to search
plus a sort trigger above the poster grid. On a pointer/desktop screen the inline
sort control SHALL remain in the toolbar beside search.

The gallery's controls SHALL remain pinned to the top of the viewport as the
gallery scrolls, so they are reachable at any scroll position without returning to
the top of the page. What is pinned differs by width. On a narrow screen the
toolbar alone is pinned — search and the sort trigger — because the category tabs
are already permanently on screen as a bottom tab bar. On a pointer/desktop screen
the category tabs and the toolbar SHALL be pinned together as a single block, so
switching category, searching, and re-sorting are all reachable while scrolled.

The pinned controls SHALL be opaque, so no poster is visible passing behind them,
and SHALL span the full width of the region the poster grid occupies, so no poster
is visible passing beside them either. They SHALL layer above the poster grid and
below every overlay — the bottom tab bar, trays, dialogs, and the fullscreen
viewer — so an open overlay always covers them.

Pinning applies only while the page can scroll. When the results are too short to
fill the viewport — a search with no matches, most obviously — the controls SHALL
rest in their normal position below the topbar. This is the same state as an
unscrolled page and is not a failure to pin.

The toolbar SHALL stay pinned to the visible area while an on-screen keyboard is
open, so searching from part-way down the gallery does not push it out of view.
Where a browser offers the choice, the application SHALL ask that the keyboard
resize the layout viewport rather than only the visual one, since that is the
coordinate space the pinned toolbar and the fixed bottom tab bar resolve against.

#### Scenario: No overflow on a phone
- **WHEN** the gallery is viewed on a narrow (phone-width) screen
- **THEN** the tabs, toolbar, and poster grid fit without horizontal page overflow

#### Scenario: Tabs fit on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs all fit on screen without overflowing the page width or
  crowding the other controls (see the native-style bottom tab bar)

#### Scenario: Secondary navigation is behind the menu on a phone
- **WHEN** the gallery is viewed on a narrow screen
- **THEN** the Poster Wall, Import from Plex, Orphans, and Support Development
  actions are not shown in the gallery toolbar
- **AND** they are reachable from the app menu tray instead

#### Scenario: Secondary navigation is in the header on desktop
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the Poster Wall, Import from Plex, Orphans, and Support Development
  actions are not shown in the gallery toolbar
- **AND** they are presented in the shared page header instead

#### Scenario: Toolbar stays available while scrolling on a phone
- **WHEN** a user on a narrow screen scrolls down the gallery
- **THEN** the toolbar stays pinned to the top of the viewport
- **AND** the search field and the sort trigger remain usable without scrolling
  back to the top

#### Scenario: Tabs and toolbar stay available while scrolling on desktop
- **WHEN** a user on a pointer/desktop-width screen scrolls down the gallery
- **THEN** the category tabs and the toolbar stay pinned together at the top of
  the viewport
- **AND** the search field, the inline sort control, and every category tab remain
  usable without scrolling back to the top

#### Scenario: Pinned toolbar hides the posters passing under it
- **WHEN** the gallery is scrolled on a narrow screen with the toolbar pinned
- **THEN** posters scrolling past are fully hidden behind the toolbar, including
  at the left and right edges of the viewport

#### Scenario: Pinned desktop controls hide the posters passing under them
- **WHEN** the gallery is scrolled on a pointer/desktop-width screen
- **THEN** posters scrolling past are fully hidden behind the pinned tabs and
  toolbar, including at the left and right edges of the poster grid

#### Scenario: Results too short to scroll leave the toolbar in flow
- **WHEN** a search returns no matches, on a narrow or a pointer/desktop screen
- **THEN** the page is too short to scroll and the gallery's controls rest below
  the topbar, as they do on an unscrolled page

#### Scenario: Toolbar survives the on-screen keyboard
- **WHEN** a user on a narrow screen scrolls part-way down the gallery and taps
  the search field, opening the on-screen keyboard
- **THEN** the toolbar stays visible at the top of the remaining area rather than
  being pushed out of view

#### Scenario: Overlays cover the pinned toolbar
- **WHEN** any tray, dialog, or the fullscreen viewer is open, on a narrow or a
  pointer/desktop screen
- **THEN** it renders above the gallery's pinned controls

### Requirement: Category tab presentation by screen size
On a narrow screen the category tabs SHALL be presented as a fixed, always-visible
bottom tab bar in which all five tabs fit the screen at once — each tab an icon
above a short label — rather than a scrolling row, so switching categories feels
like a native app tab bar. The gallery content SHALL reserve space so the tab bar
never hides the last posters or the footer. On a pointer/desktop screen the tabs
SHALL remain text tabs above the toolbar, and SHALL be pinned together with it as
the gallery scrolls (see "Responsive gallery layout and pinned controls"). Each tab SHALL retain its
full category name as its accessible name regardless of which presentation is
shown.

#### Scenario: All tabs fit at once in a bottom bar on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs are shown as a fixed bottom bar of equal-width columns that all
  fit on screen without scrolling, each an icon over a short label
- **AND** content is not hidden behind the bar

#### Scenario: Active tab is indicated
- **WHEN** a category is active on a phone
- **THEN** its tab is visually highlighted as the current one

#### Scenario: Desktop tabs keep their text presentation
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the tabs render as text tabs directly above the toolbar, rather than as
  the phone's icon-over-label bottom bar

## REMOVED Requirements

### Requirement: Responsive gallery layout
**Reason**: Replaced by "Responsive gallery layout and pinned controls". This
version specified desktop by negation: its "Desktop toolbar is unchanged"
scenario asserted that the secondary actions stayed in the gallery toolbar and
that the toolbar scrolled with the page. Both are now false — the actions moved
to the shared page header, and the tabs and toolbar are pinned together as one
block. A scenario whose entire content is "this did not change" cannot be carried
forward once it has, and the renamed requirement covers pinning on both form
factors rather than treating it as a phone affordance.

**Migration**: None. No route, endpoint, or stored data is affected. Every phone
scenario is carried into the replacement unchanged.

### Requirement: Native-style category tab bar on small screens
**Reason**: Replaced by "Category tab presentation by screen size". Its "Desktop
tabs are unchanged" scenario fixed the tabs in "their original position", which
they no longer occupy now that they are pinned with the toolbar. The old name
also undersold the requirement, which always specified the desktop presentation
as well as the phone one.

**Migration**: None. The phone bottom tab bar is specified exactly as before.
