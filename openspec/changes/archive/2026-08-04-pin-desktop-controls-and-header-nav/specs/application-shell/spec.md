## ADDED Requirements

### Requirement: Secondary navigation in the desktop page header
On a pointer/desktop screen the shared layout SHALL present the application's
secondary navigation — Poster Wall, Import from Plex, Orphans, Support
Development, and Log out — in the page header, rather than inside any single
page's own content. Because the header is shared, these actions SHALL therefore be
reachable from every page that renders navigation, not only the gallery, matching
the reach the menu tray already provides on a narrow screen. A page that renders
no navigation at all (the login page) SHALL continue to show none.

The actions SHALL be presented consistently with one another as icon-and-label
controls, drawing on the same icon set used by the menu tray. Log out SHALL be
presented in the same form as the others rather than as a plain text link, so the
group reads as one set of actions. Log out SHALL remain gated on authentication
not being bypassed; the remaining actions SHALL be shown regardless.

Where the header is too narrow to present labels beside the brand — which is
user-configurable and therefore of unbounded width — the actions SHALL fall back
to their icons alone, identified by the application's custom tooltips. Each
action's full name SHALL remain its accessible name at every width, so the visible
label may shorten but what assistive technology announces SHALL NOT.

Where one of these actions targets the page currently being viewed, it SHALL be
marked as the current page and SHALL NOT be presented as a live link to itself.

The header SHALL NOT be pinned to the viewport on any screen, so these actions
scroll out of view with it. This is deliberate on desktop for the same reason it
is on a phone: the gallery's own pinned controls are the surface that earns the
permanent space, and a second pinned bar would cost more of the viewport than
these occasional destinations justify.

#### Scenario: Secondary actions render in the header on desktop
- **WHEN** a page that renders navigation is viewed on a pointer/desktop-width
  screen
- **THEN** Poster Wall, Import from Plex, Orphans, Support Development, and Log out
  are presented in the page header as icon-and-label actions
- **AND** Log out is presented in the same form as the others rather than as a
  plain text link

#### Scenario: Secondary actions are reachable from every page with navigation
- **WHEN** a user on a pointer/desktop-width screen is on the Import from Plex or
  Orphaned posters page
- **THEN** the secondary actions are available in the header, without returning to
  the gallery first

#### Scenario: The current destination is marked rather than linked
- **WHEN** a user on a pointer/desktop-width screen is viewing a page that one of
  the header actions targets
- **THEN** that action is marked as the current page and does not act as a link to
  the page already being viewed

#### Scenario: Labels give way to icons on a narrow desktop window
- **WHEN** the viewport is too narrow to fit the labelled actions beside the brand,
  but is wider than a narrow (phone-width) screen
- **THEN** the actions render as icons alone, identified by the application's
  custom tooltips

#### Scenario: Accessible names do not shorten with the labels
- **WHEN** the header actions are rendered at any width, whether labelled, shortened,
  or icon-only
- **THEN** each action's accessible name is its full name

#### Scenario: Log out follows the authentication setting
- **WHEN** an install runs with authentication bypassed
- **THEN** the header presents the other secondary actions but omits Log out

#### Scenario: Header actions scroll away with the header
- **WHEN** a user on a pointer/desktop-width screen scrolls down a page
- **THEN** the header and its actions scroll out of view rather than remaining
  pinned to the viewport

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
from Plex, Orphans, Support Development, and Log out). The control's accessible
name and the tray's heading SHALL both name these as actions, so what the glyph
signifies is also what the menu calls itself. The tray SHALL reuse the
application's shared bottom-sheet/tray presentation (including its app-style
dismissal — see "App-style tray dismissal") rather than introducing a separate
drawer system, and SHALL also dismiss after a link is chosen. The secondary links
SHALL have a single source of truth shared between their desktop placement and the
mobile tray, so the same set of links is presented in both without divergent
markup; where the desktop placement shortens a visible label to fit, the tray SHALL
continue to show the full name. The Support Development link SHALL open the
project's support page at `https://getmarquee.now/#support` in a new browsing
context, leaving the current page in place. The menu SHALL be available whether or
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
- **THEN** a tray opens listing Poster Wall, Import from Plex, Orphans, Support
  Development, and Log out

#### Scenario: Tray shows full names even where the header shortens them
- **WHEN** the menu tray is opened on a narrow screen
- **THEN** each link shows its full name, regardless of any shortened label the
  desktop header presents for the same link

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
  navigation renders in the page header

#### Scenario: Menu is present even when auth is bypassed
- **WHEN** an authenticated-optional install runs with authentication bypassed
- **THEN** the phone menu still opens the secondary actions, but the tray omits
  the Log out link
- **AND** the Support Development link is still present

#### Scenario: Menu is absent on pages with no navigation
- **WHEN** a page renders without any navigation (for example the login page,
  which overrides the navigation region)
- **THEN** no menu control is shown

### Requirement: Page header aligns with the content column on desktop
On a pointer/desktop-width screen the shared layout's page header SHALL place its
brand and its navigation at the same left and right edges as the page content
below them, so the header reads as part of the same column rather than as chrome
pinned to the viewport. The header itself SHALL continue to span the full
viewport width, and SHALL be presented as page-coloured chrome separated from the
content by a single rule along its bottom edge — matching the presentation of the
project's landing page — rather than as a raised or bordered panel. Where the
viewport is narrower than the content column's maximum width, the header's
contents SHALL fall back to the same edge spacing the content column uses at that
width. On a narrow screen the header SHALL be unchanged: a full-width bar flush
with the top and side edges of the viewport, keeping its existing surface,
bottom border, and spacing.

#### Scenario: Header contents align with the content column on a wide screen
- **WHEN** a page that extends the shared layout is viewed on a screen wider
  than the content column's maximum width
- **THEN** the brand starts at the same horizontal position as the content below
  it, and the navigation ends at that content's right edge

#### Scenario: Header reads as page-coloured chrome on a wide screen
- **WHEN** a page that extends the shared layout is viewed on a pointer/desktop
  screen
- **THEN** the header spans the full viewport width, is drawn in the page
  background rather than a raised surface, and is separated from the content by
  a single rule along its bottom edge

#### Scenario: Narrow screens keep the full-width bar
- **WHEN** a page that extends the shared layout is viewed on a narrow screen
- **THEN** the header is full-width, flush to the top and side edges, drawn on
  the raised surface with a bottom border, and none of the desktop alignment or
  page-coloured treatment applies

#### Scenario: Header and content agree at intermediate widths
- **WHEN** the viewport is wider than a narrow screen but narrower than the
  content column's maximum width
- **THEN** the header's contents use the same edge spacing as the content
  column, so the two still line up

#### Scenario: Alignment does not alter the header's contents
- **WHEN** the header is rendered on any page that extends the shared layout,
  including the login page which renders no navigation
- **THEN** the brand, the desktop secondary navigation actions, the Log out
  control, the menu control, and the navigation tray are presented and behave as
  their own requirements specify, unaffected by the header's alignment or colour
