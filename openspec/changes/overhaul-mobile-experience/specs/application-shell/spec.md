## ADDED Requirements

### Requirement: App-wide mobile navigation menu
The shared layout SHALL provide an app-wide navigation menu for small screens so
that secondary navigation is reachable from every authenticated page without
crowding the content. On a narrow screen the topbar SHALL present a single menu
(hamburger) control; activating it SHALL open a tray listing the secondary
navigation links (Poster Wall, Import from Plex, Orphans, and Log out). The tray
SHALL reuse the application's existing bottom-sheet/overlay presentation rather
than introducing a separate drawer system, and SHALL dismiss on backdrop tap, on
Escape, and after a link is chosen. The secondary navigation links SHALL have a
single source of truth shared between their desktop placement and the mobile
tray, so the same set of links is presented in both without divergent markup.
On a pointer/desktop screen the menu control and tray SHALL NOT be shown and the
secondary links SHALL render in their existing desktop positions.

#### Scenario: Menu opens the navigation tray on a phone
- **WHEN** an authenticated user on a narrow screen activates the topbar menu
  control
- **THEN** a tray opens listing Poster Wall, Import from Plex, Orphans, and Log
  out

#### Scenario: Tray dismisses
- **WHEN** the menu tray is open and the user taps the backdrop, presses Escape,
  or chooses one of its links
- **THEN** the tray closes

#### Scenario: Menu is hidden on desktop
- **WHEN** the app is viewed on a pointer/desktop-width screen
- **THEN** the topbar menu control and its tray are not shown and the secondary
  navigation renders in its existing desktop positions

#### Scenario: Menu is absent when there is no navigation to show
- **WHEN** a page renders without secondary navigation (for example the login
  page, or when auth is bypassed and Log out is not offered)
- **THEN** no menu control is shown

## MODIFIED Requirements

### Requirement: Consistent custom tooltips
Tooltips across the application SHALL be rendered by a single themed custom
tooltip component rather than the browser's native `title=` tooltip, so hint text
matches the app's look and can display content the native tooltip cannot present
well (such as a poster's full title). Any element that offers a tooltip SHALL
declare its text through a `data-tooltip` attribute, and the shared tooltip SHALL
appear on pointer hover and on keyboard focus for focusable targets, including
targets added to the page after load (e.g. via AJAX or client-side rendering).
Removing a native `title` from an icon-only control SHALL NOT remove that
control's accessible name — the name SHALL be preserved by an `aria-label`.

A non-interactive element that carries a tooltip (such as a poster caption) SHALL
present a cursor that signals a tooltip — a `help` cursor — rather than the
text/I-beam cursor used for editable text, so hovering it reads as "more
information is available" rather than "edit this." Interactive tooltip hosts
(links and buttons) SHALL keep their normal pointer affordance.

#### Scenario: Custom tooltip replaces the native tooltip
- **WHEN** a user hovers an element that offers a tooltip
- **THEN** the themed custom tooltip is shown and no separate native browser
  tooltip appears

#### Scenario: Tooltip works on keyboard focus
- **WHEN** a user moves keyboard focus to a focusable element that offers a
  tooltip
- **THEN** the custom tooltip is shown for that element

#### Scenario: Tooltip works on dynamically added content
- **WHEN** an element with a tooltip is added to the page after initial load
  (such as paginated results or client-rendered previews)
- **THEN** hovering it shows the custom tooltip just as for elements present at
  load

#### Scenario: Icon-only control keeps its accessible name
- **WHEN** an icon-only control that previously relied on a native `title` is
  converted to the custom tooltip
- **THEN** the control still exposes an accessible name to assistive technology

#### Scenario: Non-interactive tooltip host signals a tooltip
- **WHEN** a user hovers a non-interactive element that carries a tooltip (such
  as a truncated poster caption)
- **THEN** the cursor indicates a tooltip (a `help` cursor) rather than the
  text/I-beam cursor
