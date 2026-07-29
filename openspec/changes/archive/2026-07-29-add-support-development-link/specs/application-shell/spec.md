## MODIFIED Requirements

### Requirement: App-wide mobile navigation menu
The shared layout SHALL provide an app-wide navigation menu for small screens so
that secondary navigation is reachable from every authenticated page without
crowding the content. On a narrow screen the topbar SHALL present a single menu
(hamburger) control; activating it SHALL open a tray listing the secondary
navigation links (Poster Wall, Import from Plex, Orphans, Support Development,
and Log out). The tray SHALL reuse the application's shared bottom-sheet/tray
presentation (including its app-style dismissal — see "App-style tray dismissal")
rather than introducing a separate drawer system, and SHALL also dismiss after a
link is chosen. The secondary navigation links SHALL have a single source of
truth shared between their desktop placement and the mobile tray, so the same set
of links is presented in both without divergent markup. The Support Development
link SHALL open the project's support page at `https://getmarquee.now/#support`
in a new browsing context, leaving the current page in place. The menu SHALL be
available whether or not authentication is bypassed, so the secondary navigation
stays reachable on a phone; only the Log out link SHALL be gated on
authentication being enabled. On a pointer/desktop screen the menu control and
tray SHALL NOT be shown and the secondary links SHALL render in their existing
desktop positions.

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

#### Scenario: Menu is hidden on desktop
- **WHEN** the app is viewed on a pointer/desktop-width screen
- **THEN** the topbar menu control and its tray are not shown and the secondary
  navigation renders in its existing desktop positions

#### Scenario: Menu is present even when auth is bypassed
- **WHEN** an authenticated-optional install runs with authentication bypassed
- **THEN** the phone menu still opens the secondary navigation, but the tray omits
  the Log out link
- **AND** the Support Development link is still present

#### Scenario: Menu is absent on pages with no navigation
- **WHEN** a page renders without any navigation (for example the login page,
  which overrides the navigation region)
- **THEN** no menu control is shown
