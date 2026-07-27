## MODIFIED Requirements

### Requirement: Type badge in the aggregate view
In the All view the system SHALL display a small type badge on each poster
indicating its category (Movie, TV Show, TV Season, or Collection). The badges
SHALL be styled from the application's own color palette rather than unrelated
stock colors, while keeping the four types visually distinguishable from one
another. On pointer (hover-capable) devices the badge SHALL hide while the
poster's action overlay is shown, so it does not obscure or compete with the
actions. On touch devices, where the overlay is not used, the badge SHALL remain
visible. Type badges SHALL appear only in the All view; single-category views
SHALL NOT show them.

#### Scenario: Badge shows the poster's type in All
- **WHEN** the All view renders a poster
- **THEN** a badge on the poster identifies its category as Movie, TV Show,
  TV Season, or Collection

#### Scenario: Badges use the app palette
- **WHEN** the All view renders posters of different types
- **THEN** each badge is tinted from the application's theme palette and the four
  types remain distinguishable from one another

#### Scenario: Badge hides on hover on pointer devices
- **WHEN** a user hovers a poster in the All view on a pointer device and the
  action overlay is revealed
- **THEN** the type badge is hidden while the overlay is shown

#### Scenario: Badge persists on touch
- **WHEN** the All view is viewed on a touch device
- **THEN** the type badge stays visible on each poster

#### Scenario: No badge in single-category views
- **WHEN** a single category (Movies, TV Shows, TV Seasons, or Collections) is
  viewed
- **THEN** no type badge is shown on its posters

### Requirement: Poster presentation
The gallery SHALL show each poster's title in a caption beneath the poster, size
posters large enough for the overlay action stack to fit, and lazy-load images
with a subtle placeholder animation that resolves when the image loads. The
placeholder animation and fade-in SHALL apply on every page that renders poster
cards, not only the gallery, and SHALL resolve whether the image loads or fails.
Plex import bakes the source library name into the poster's filename, so it
surfaces as a trailing token in the derived title (e.g. "… 2003 Movies"). The
visible caption and its tooltip SHALL omit that library token, because the type is
already conveyed by the badge and the active tab; the token is identified using
the poster's known library name so the rest of the title (including any year) is
preserved. The caption SHALL be constrained to a single line no wider than the
poster above it, truncating with an ellipsis rather than wrapping, so a long title
never pushes the following row of posters down.

#### Scenario: Title beneath the poster
- **WHEN** the gallery renders a poster
- **THEN** its title appears in a caption below the image rather than inside the
  hover overlay

#### Scenario: Caption omits the library token
- **WHEN** the gallery renders a Plex-imported poster whose derived title ends in
  its library name (e.g. "Louis and the Nazis 2003 Movies" from the "Movies"
  library)
- **THEN** the visible caption drops that trailing library token (showing "Louis
  and the Nazis 2003") while the rest of the title is unchanged

#### Scenario: Tooltip also omits the library token
- **WHEN** a user hovers a poster whose caption dropped its library token
- **THEN** the tooltip shows the same title without the library token

#### Scenario: Non-Plex poster keeps its full title
- **WHEN** the gallery renders a poster with no known library (e.g. an uploaded
  poster)
- **THEN** the caption shows the full derived title unchanged

#### Scenario: Long caption truncates instead of wrapping
- **WHEN** a poster's caption is longer than the poster is wide
- **THEN** the caption stays on a single line no wider than the poster and ends
  in an ellipsis, and the posters below it are not pushed down

#### Scenario: Lazy-load animation
- **WHEN** a poster image has not yet loaded
- **THEN** a subtle placeholder animation is shown and the image fades in once
  loaded

#### Scenario: Poster cards outside the gallery
- **WHEN** a page other than the gallery renders poster cards, such as the
  orphans page
- **THEN** each poster image fades in once loaded, rather than staying invisible
  behind a placeholder that animates indefinitely

#### Scenario: Image that fails to load
- **WHEN** a poster image request fails
- **THEN** the placeholder animation stops rather than continuing to suggest the
  image is still loading

### Requirement: Poster actions on touch devices
On touch devices the poster actions SHALL be presented in a bottom action sheet
opened by tapping the poster, rather than overlaid on the poster itself, so every
action is shown at full size and none can be triggered by accident. The sheet's
heading SHALL name the poster; for a Plex-imported poster the heading SHALL keep
the source library and show it in parentheses (e.g. "Louis and the Nazis 2003
(Movies)"), even though the caption drops it.

#### Scenario: Tapping a poster opens the action sheet
- **WHEN** a user taps a poster on a touch device
- **THEN** a sheet opens listing that poster's actions (change, send/fetch to
  Plex when linked, download, copy URL, full screen, delete) with its title

#### Scenario: Sheet heading shows the library in parentheses
- **WHEN** the action sheet opens for a Plex-imported poster
- **THEN** its heading shows the poster title with the source library in
  parentheses

#### Scenario: No tap-through
- **WHEN** a user taps a poster on a touch device
- **THEN** the tap opens the sheet only and does not trigger any poster action

#### Scenario: Dismissing the sheet
- **WHEN** the user taps outside the sheet, presses Escape, or runs one of its
  actions
- **THEN** the sheet closes

### Requirement: Poster actions on pointer devices
On pointer (hover-capable) devices the gallery SHALL reveal a poster's action
overlay on hover, without the actions being clipped or hidden off-card, and
clicking the poster itself SHALL open it full screen. Each action control in the
overlay SHALL present a distinct hover and keyboard-focus state so the control
about to be activated is clearly indicated before it is clicked.

#### Scenario: Hover reveals actions on desktop
- **WHEN** a user hovers a poster on a pointer device
- **THEN** the action overlay is shown

#### Scenario: Action control indicates hover and focus
- **WHEN** a user moves the pointer over, or keyboard-focuses, one of the overlay
  action controls
- **THEN** that control changes appearance to indicate it is the one that will be
  activated

#### Scenario: Clicking opens full screen on desktop
- **WHEN** a user clicks a poster (not one of its action buttons) on a pointer
  device
- **THEN** the poster opens full screen
