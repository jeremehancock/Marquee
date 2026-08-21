## MODIFIED Requirements

### Requirement: App-wide mobile actions menu and its destinations
The shared layout SHALL provide an app-wide **actions** menu for small screens so
that the application's secondary actions are reachable from every authenticated
page without crowding the content. The menu's entries are predominantly actions
rather than in-place navigation destinations: on a narrow screen Import from Plex,
Orphans, Settings, the Plex connection and Support Development open as trays over
the current page, Poster Wall opens in a new browsing context, and Log out is an
action. The trigger's glyph SHALL therefore signify an overflow / "more actions"
affordance rather than a hamburger, which conventionally promises an
edge-anchored navigation drawer the menu does not provide.

Settings SHALL open as a tray on a narrow screen rather than navigating to its own
page, in the same presentation Import from Plex and Orphans use — the taller tray
the application reserves for a tray that holds a whole page. A settings form is
long, so the tray SHALL scroll its own content rather than the page behind it, and
SHALL be reachable and dismissable by the same gestures as every other tray. This
reverses an earlier requirement that Settings navigate at every width on the
grounds that a form that long is worse in a bottom sheet than on a page: what makes
the tray work is the tall presentation plus the save contract below, and what made
the page wrong on a phone is that leaving the gallery to change how the gallery
behaves puts the way back in a "Back to gallery" link rather than in the swipe and
backdrop the device already offers.

Saving from the settings tray SHALL close the tray and reload the page. Settings
change what the page behind the tray is showing — the site title in the header, how
many posters a page holds, the order they are in, which libraries are visible — so a
save that left that page in place would leave it describing configuration that no
longer applies. The reload SHALL carry the same saved-settings confirmation the
settings page itself gives, so the save is acknowledged rather than merely enacted.
A submission that fails validation SHALL leave the tray open, showing the same
errors against the same fields the page would show, without reloading anything.

`/settings` SHALL remain a page in its own right. The tray is a second presentation
of that page and not a replacement for it: a pointer/desktop screen, a direct link,
and any page that cannot host the tray SHALL all continue to reach the settings
screen by navigating to it.

The connection screen SHALL open as a tray on a narrow screen on the same terms,
in the same tall presentation, reached from the connection status rather than from
a navigation entry — see "The Plex connection is shown as a status, not a
destination". It is the entry a user touches most casually, because it is a
reading rather than an errand, and it was the last one that charged a page load
for a glance.

Unlike the import tray, the connection tray SHALL be fetched on every open. What
it reports decays: the connection screen asks the Plex server for its name, and
the connection can be gone since the page behind the tray was rendered.

Disconnecting from the connection tray SHALL navigate to the connection screen
rather than resolving in place. Every other tray leaves a usable page behind it;
this action does not, because it is what the connection gate turns a user away
for. Following the form is what puts the user in front of the confirmation and
the way back in.

On a narrow screen the topbar SHALL present a single overflow menu control;
activating it SHALL open a tray listing the secondary links (Poster Wall, Import
from Plex, Orphans, Settings, Support Development, and Log out). The control's accessible
name and the tray's heading SHALL both name these as actions, so what the glyph
signifies is also what the menu calls itself. The tray SHALL reuse the
application's shared bottom-sheet/tray presentation (including its app-style
dismissal — see "App-style tray dismissal") rather than introducing a separate
drawer system, and SHALL also dismiss after a link is chosen. The secondary links
SHALL have a single source of truth shared between their desktop placement and the
mobile tray, so the same set of links is presented in both without divergent
markup; where the desktop placement shortens a visible label to fit, the tray SHALL
continue to show the full name. Support Development SHALL open the application's
own support ask over the current page rather than opening the project's marketing
site — see "In-app support ask" — and choosing it SHALL dismiss the actions tray
first, the way choosing Settings does. The menu SHALL be available whether or
not authentication is bypassed, so the secondary actions stay reachable on a phone;
only the Log out link SHALL be gated on authentication being enabled. On a
pointer/desktop screen the menu control and tray SHALL NOT be shown and the
secondary links SHALL render in the page header instead (see "Secondary navigation
in the desktop page header").

The topbar SHALL NOT be pinned to the viewport, so the menu control scrolls out of
view with it. This is deliberate: a phone already keeps the category tab bar and
the gallery toolbar permanently on screen, and a third pinned bar would cost more
of the viewport than the menu's occasional use justifies.

#### Scenario: Menu control presents an overflow affordance
- **WHEN** the topbar is rendered on a narrow screen
- **THEN** the menu control's glyph signifies "more actions" rather than a
  hamburger
- **AND** it carries an accessible name identifying it as the actions menu
- **AND** the tray it opens is headed as actions rather than as navigation

#### Scenario: Menu opens the navigation tray on a phone
- **WHEN** an authenticated user on a narrow screen activates the topbar menu
  control
- **THEN** a tray opens listing Poster Wall, Import from Plex, Orphans, Settings,
  Support Development, and Log out

#### Scenario: Settings opens as a tray from the actions tray
- **WHEN** a user on a narrow screen chooses Settings from the actions tray, on the
  gallery
- **THEN** the actions tray dismisses and the settings screen opens as a tray over
  the current page
- **AND** the page behind it is not navigated away from
- **AND** the tray uses the taller presentation reserved for a tray holding a whole
  page

#### Scenario: The connection opens as a tray from the actions tray
- **WHEN** a user on a narrow screen chooses the Plex connection status from the
  actions tray, on the gallery
- **THEN** the actions tray dismisses and the connection screen opens as a tray
  over the current page
- **AND** the page behind it is not navigated away from
- **AND** the tray uses the taller presentation reserved for a tray holding a whole
  page, and dismisses by the same gestures as every other tray

#### Scenario: The connection tray reports the connection as it is now
- **WHEN** a user opens the connection tray, dismisses it, and opens it again
- **THEN** the tray's contents are fetched afresh on each open rather than reused
  from the first

#### Scenario: Disconnecting from the tray leaves the gallery
- **WHEN** a user disconnects from Plex from within the connection tray
- **THEN** the browser navigates to the connection screen
- **AND** the confirmation that the connection was forgotten is shown there

#### Scenario: Saving from the settings tray reloads the page beneath it
- **WHEN** a user saves valid settings from the settings tray
- **THEN** the tray closes and the page is reloaded
- **AND** the reloaded page reflects the settings just saved
- **AND** it carries the same saved-settings confirmation the settings page gives

#### Scenario: An invalid save keeps the tray open
- **WHEN** a user saves settings from the tray and the submission fails validation
- **THEN** the tray stays open showing the errors against the fields they belong to
- **AND** the page behind the tray is neither reloaded nor navigated away from

#### Scenario: Settings opens as a page from the tray
- **WHEN** a user on a narrow screen chooses Settings from the actions tray, on a
  page that hosts no settings tray of its own — Import from Plex, or Orphaned
  posters
- **THEN** the actions tray dismisses and the settings page is opened
- **AND** this is the fallback rather than the ordinary case: Import from Plex and
  Orphans behave the same way on those pages

#### Scenario: The connection opens as a page where no tray hosts it
- **WHEN** a user on a narrow screen chooses the connection status on a page that
  hosts no connection tray of its own, or a user on a pointer/desktop screen
  chooses it anywhere
- **THEN** the connection screen is opened by navigating to it
- **AND** this is the same fallback Import from Plex, Orphans and Settings take

#### Scenario: The settings page remains reachable in its own right
- **WHEN** a user opens `/settings` directly, or reaches Settings from a
  pointer/desktop screen
- **THEN** the settings screen is presented as its own page
- **AND** the tray is an alternative presentation of that page rather than a
  replacement for it

#### Scenario: Tray shows full names even where the header shortens them
- **WHEN** the menu tray is opened on a narrow screen
- **THEN** each link shows its full name, regardless of any shortened label the
  desktop header presents for the same link

#### Scenario: Support Development opens the support ask in place
- **WHEN** a user on a narrow screen chooses Support Development from the actions
  tray
- **THEN** the actions tray dismisses and the support ask opens as a tray over the
  current page
- **AND** no new browsing context is opened and the page they were on is left in
  place
- **AND** this holds on every page that renders the tray, not only the gallery

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
  navigation renders in the page header

#### Scenario: Menu is present even when auth is bypassed
- **WHEN** an authenticated-optional install runs with authentication bypassed
- **THEN** the phone menu still opens the secondary actions, but the tray omits
  the Log out link
- **AND** the Support Development entry is still present

#### Scenario: Menu is absent on pages with no navigation
- **WHEN** a page renders without any navigation (for example the login page,
  which overrides the navigation region)
- **THEN** no menu control is shown

### Requirement: The Plex connection is shown as a status, not a destination
The interface SHALL carry the state of the Plex connection in its navigation for
a signed-in user, naming the connected server or reporting that Plex is not
connected. It SHALL NOT present the connection screen as an ordinary destination
alongside the poster actions.

The connection screen is somewhere a user goes once. Listing it beside Import and
Orphans presented it as a place to go, when what is worth carrying on every page
is whether Marquee can still reach Plex.

The status SHALL link to the connection screen, because that screen is the only
place disconnecting is offered and removing the link would leave the action
reachable only by typing a URL.

On a narrow touch screen the status SHALL open that screen as a tray over the
current page wherever a tray can host it, rather than navigating — the same
treatment Import from Plex, Orphans and Settings receive, and specified with them
under "App-wide mobile actions menu and its destinations". Reading which server
Marquee is talking to is a glance, and it was the last entry in the navigation
that charged a page load and a "Back to gallery" link for one. Where no tray can
host it the status SHALL navigate to the screen as before, so the link is never
inert.

The status SHALL carry one destination and one meaning at every width: the tray
is a second presentation of the connection screen, not a different place, and the
status SHALL NOT offer to change the connection itself.

It SHALL be presented differently from the navigation actions rather than as one
more of them. Every action in that bar is a labelled control carrying a glyph, so
a status wearing the same shape reads as another place to go — which is the thing
this replaced. The state indicator SHALL be what identifies it, and it SHALL NOT
carry an icon of its own.

The state SHALL NOT be conveyed by colour alone: the status SHALL carry a text
description of the condition for assistive technology and where labels are
hidden.

Reporting the status SHALL NOT contact Plex. It renders on every page, and a
reachability probe there would stall the whole application whenever the server
was down — the same reason the connection gate reads configuration only. Opening
the connection screen, as a page or as a tray, is a different matter: that screen
exists to describe the connection, so it asks.

The name it reports SHALL therefore be recorded when the connection is
established, not looked up when it is displayed. The server names itself in the
response the ownership check already reads, so a sign-in learns it at no extra
cost. Where no name has been recorded the status SHALL report the connection as
connected rather than naming anything.

#### Scenario: Connected names the server
- **WHEN** a signed-in user views any page with navigation while Plex is connected
- **THEN** the navigation shows the connected server's name

#### Scenario: The server is named without opening the connection screen
- **WHEN** a user signs in and goes straight to the gallery
- **THEN** the navigation already names the connected server

#### Scenario: An unnamed connection still reports itself as connected
- **WHEN** the status renders while Plex is connected but no server name has been
  recorded
- **THEN** it reports the connection as connected rather than naming anything

#### Scenario: Disconnected is reported as such
- **WHEN** a signed-in user views a page with navigation while Plex is not
  connected
- **THEN** the navigation reports that Plex is not connected

#### Scenario: The status is the way to the connection screen
- **WHEN** the status renders
- **THEN** it links to the connection screen

#### Scenario: The status opens the connection as a tray on a phone
- **WHEN** a signed-in user on a narrow touch screen activates the status on a
  page that can host the connection tray
- **THEN** the connection screen opens as a tray over that page
- **AND** the page behind it is not navigated away from

#### Scenario: The status still navigates where no tray can host it
- **WHEN** a signed-in user activates the status on a pointer/desktop screen, or
  on a page that hosts no connection tray
- **THEN** the browser navigates to the connection screen

#### Scenario: The connection is not listed among the poster actions
- **WHEN** the navigation renders
- **THEN** it offers no ordinary navigation item for the connection screen

#### Scenario: The status is not shaped like a navigation action
- **WHEN** the status renders
- **THEN** it carries no icon
- **AND** it does not take the presentation the navigation actions use

#### Scenario: The condition is available as text
- **WHEN** the status renders in either state
- **THEN** its accessible name states whether Plex is connected
