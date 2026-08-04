## RENAMED Requirements

- FROM: `### Requirement: App-wide mobile navigation menu`
- TO: `### Requirement: App-wide mobile actions menu`

## MODIFIED Requirements

### Requirement: App-wide mobile actions menu
The shared layout SHALL provide an app-wide **actions** menu for small screens so
that the application's secondary actions are reachable from every authenticated
page without crowding the content. The menu's entries are actions rather than
in-place navigation destinations: on a narrow screen Import from Plex and Orphans
open as trays over the current page, Poster Wall and Support Development open in a
new browsing context, and Log out is an action. The trigger's glyph SHALL
therefore signify an overflow / "more actions" affordance rather than a hamburger,
which conventionally promises an edge-anchored navigation drawer the menu does not
provide.

On a narrow screen the topbar SHALL present a single overflow menu control;
activating it SHALL open a tray listing the secondary links (Poster Wall, Import
from Plex, Orphans, Support Development, and Log out). The tray SHALL reuse the
application's shared bottom-sheet/tray presentation (including its app-style
dismissal — see "App-style tray dismissal") rather than introducing a separate
drawer system, and SHALL also dismiss after a link is chosen. The secondary links
SHALL have a single source of truth shared between their desktop placement and the
mobile tray, so the same set of links is presented in both without divergent
markup. The Support Development link SHALL open the project's support page at
`https://getmarquee.now/#support` in a new browsing context, leaving the current
page in place. The menu SHALL be available whether or not authentication is
bypassed, so the secondary actions stay reachable on a phone; only the Log out
link SHALL be gated on authentication being enabled. On a pointer/desktop screen
the menu control and tray SHALL NOT be shown and the secondary links SHALL render
in their existing desktop positions.

The topbar SHALL NOT be pinned to the viewport, so the menu control scrolls out of
view with it. This is deliberate: a phone already keeps the category tab bar and
the gallery toolbar permanently on screen, and a third pinned bar would cost more
of the viewport than the menu's occasional use justifies.

#### Scenario: Menu control presents an overflow affordance
- **WHEN** the topbar is rendered on a narrow screen
- **THEN** the menu control's glyph signifies "more actions" rather than a
  hamburger
- **AND** it carries an accessible name identifying it as the menu

#### Scenario: Menu opens the navigation tray on a phone
- **WHEN** an authenticated user on a narrow screen activates the topbar menu
  control
- **THEN** a tray opens listing Poster Wall, Import from Plex, Orphans, Support
  Development, and Log out

#### Scenario: Support Development opens the project's support page
- **WHEN** a user activates the Support Development link, from either the
  desktop placement or the mobile tray
- **THEN** `https://getmarquee.now/#support` opens in a new browsing context
- **AND** the page they were on is left in place

#### Scenario: Tray dismisses
- **WHEN** the menu tray is open and the user taps the backdrop, presses Escape,
  or chooses one of its links
- **THEN** the tray closes

#### Scenario: Menu control scrolls away with the topbar
- **WHEN** a user on a narrow screen scrolls down a page
- **THEN** the topbar and its menu control scroll out of view rather than
  remaining pinned to the viewport

#### Scenario: Menu is hidden on desktop
- **WHEN** the app is viewed on a pointer/desktop-width screen
- **THEN** the topbar menu control and its tray are not shown and the secondary
  navigation renders in its existing desktop positions

#### Scenario: Menu is present even when auth is bypassed
- **WHEN** an authenticated-optional install runs with authentication bypassed
- **THEN** the phone menu still opens the secondary actions, but the tray omits
  the Log out link
- **AND** the Support Development link is still present

#### Scenario: Menu is absent on pages with no navigation
- **WHEN** a page renders without any navigation (for example the login page,
  which overrides the navigation region)
- **THEN** no menu control is shown

### Requirement: App-style tray dismissal
Every bottom-sheet tray in the application (the app-wide actions menu, and the
poster-library poster/sort/import trays) SHALL present a centered grab handle at
its top instead of a close (×) button, and SHALL be dismissible by dragging the
tray downward past a threshold as well as by tapping the backdrop and pressing
Escape, so the trays behave like native app sheets.

#### Scenario: Tray shows a grab handle, not a close button
- **WHEN** any tray is open
- **THEN** it shows a centered grab handle at the top and no × close control

#### Scenario: Swipe down dismisses a tray
- **WHEN** the user drags an open tray downward past the dismissal threshold from
  its handle or heading
- **THEN** the tray closes

#### Scenario: Backdrop and Escape still dismiss a tray
- **WHEN** the user taps outside an open tray or presses Escape
- **THEN** the tray closes
