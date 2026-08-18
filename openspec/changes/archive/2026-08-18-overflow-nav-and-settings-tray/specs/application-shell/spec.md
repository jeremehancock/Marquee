## MODIFIED Requirements

### Requirement: App-wide mobile actions menu
The shared layout SHALL provide an app-wide **actions** menu for small screens so
that the application's secondary actions are reachable from every authenticated
page without crowding the content. The menu's entries are predominantly actions
rather than in-place navigation destinations: on a narrow screen Import from Plex,
Orphans and Settings open as trays over the current page, Poster Wall and Support
Development open in a new browsing context, and Log out is an action. The
trigger's glyph SHALL therefore signify an overflow / "more actions" affordance
rather than a hamburger, which conventionally promises an edge-anchored navigation
drawer the menu does not provide.

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

### Requirement: Secondary navigation in the desktop page header
On a pointer/desktop screen the shared layout SHALL present the application's
secondary navigation — Poster Wall, Import from Plex, Orphans, Settings, Support
Development, and Log out — in the page header, rather than inside any single
page's own content. Because the header is shared, these actions SHALL therefore be
reachable from every page that renders navigation, not only the gallery, matching
the reach the menu tray already provides on a narrow screen. A page that renders
no navigation at all (the login page) SHALL continue to show none.

These actions SHALL be presented in two tiers: a bar carrying the actions that
operate on the poster library — Poster Wall, Import from Plex, and Orphans —
followed by a single overflow menu control holding the rest, and then the Plex
connection status. The overflow menu SHALL hold Settings, Support Development, and
Log out: configuration, the external ask, and the session exit, none of which is
part of managing posters. The header's contents are aligned to the content column
rather than the viewport, and share that width with a brand of unbounded length, so
six labelled actions plus a status do not reliably fit; the split is what keeps the
bar within its width at any site title rather than only at short ones.

The Plex connection status SHALL remain in the bar rather than move into the
overflow menu. It is a reading rather than a destination, and a reading that has to
be opened is not one.

The overflow control SHALL use the same "more actions" glyph the narrow-screen menu
button uses, so a single affordance means the same thing at both widths, and SHALL
carry an accessible name identifying what it opens, along with its expanded state.
Its menu SHALL be dismissable by pressing Escape and by activating anything outside
it. Dismissing it with Escape SHALL return focus to the control, because the
keyboard user has nowhere else to be; dismissing it by activating something else
SHALL NOT, because focus belongs to whatever was activated. The menu SHALL present
its entries with their full names rather than the shortened labels the bar may use,
matching the narrow-screen tray.

The actions SHALL be presented consistently with one another as icon-and-label
controls, drawing on the same icon set used by the menu tray. Log out SHALL be
presented in the same form as the others rather than as a plain text link, so the
group reads as one set of actions. Log out SHALL remain gated on authentication
not being bypassed; the remaining actions SHALL be shown regardless.

Where the header is too narrow to present labels beside the brand — which is
user-configurable and therefore of unbounded width — the actions SHALL fall back
to their icons alone, identified by the application's custom tooltips. Each
action's full name SHALL remain its accessible name at every width, so the visible
label may shorten but what assistive technology announces SHALL NOT. This fallback
SHALL continue to apply to the bar alongside the overflow split rather than being
replaced by it.

Where one of these actions targets the page currently being viewed, it SHALL be
marked as the current page and SHALL NOT be presented as a live link to itself.
Where that action sits inside the overflow menu, the overflow control SHALL also be
marked as current, so that hiding an entry behind a click does not hide from the
user that they are already on it.

The header SHALL NOT be pinned to the viewport on any screen, so these actions
scroll out of view with it. This is deliberate on desktop for the same reason it
is on a phone: the gallery's own pinned controls are the surface that earns the
permanent space, and a second pinned bar would cost more of the viewport than
these occasional destinations justify. The overflow menu SHALL nonetheless be drawn
above the page's own pinned controls while it is open, since it opens downward over
the content region they occupy.

#### Scenario: Secondary actions render in the header on desktop
- **WHEN** a page that renders navigation is viewed on a pointer/desktop-width
  screen
- **THEN** Poster Wall, Import from Plex, and Orphans are presented in the header bar
  as icon-and-label actions, followed by an overflow control and the Plex connection
  status
- **AND** Settings, Support Development, and Log out are presented inside the menu the
  overflow control opens
- **AND** Log out is presented in the same form as the others rather than as a
  plain text link

#### Scenario: The overflow menu opens and dismisses
- **WHEN** a user on a pointer/desktop-width screen activates the overflow control
- **THEN** a menu opens listing Settings, Support Development, and Log out under their
  full names
- **AND** the control reports that it is expanded
- **AND** pressing Escape closes the menu and returns focus to the control
- **AND** activating anything outside the menu closes it without taking focus back
  from what was activated

#### Scenario: The overflow menu draws above the page's pinned controls
- **WHEN** the overflow menu is open over a page whose own controls are pinned below
  the header
- **THEN** the menu is drawn above them rather than behind them

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

#### Scenario: The overflow control is marked when it holds the current destination
- **WHEN** a user on a pointer/desktop-width screen is viewing a page targeted by an
  action inside the overflow menu
- **THEN** the overflow control itself is marked as current
- **AND** the action inside the menu is still marked as current and does not act as a
  link to the page already being viewed

#### Scenario: Labels give way to icons on a narrow desktop window
- **WHEN** the viewport is too narrow to fit the labelled actions beside the brand,
  but is wider than a narrow (phone-width) screen
- **THEN** the bar's actions render as icons alone, identified by the application's
  custom tooltips
- **AND** the overflow control and its menu are unaffected, the control having no
  label to drop and the menu showing full names at every width

#### Scenario: Accessible names do not shorten with the labels
- **WHEN** the header actions are rendered at any width, whether labelled, shortened,
  or icon-only
- **THEN** each action's accessible name is its full name

#### Scenario: Log out follows the authentication setting
- **WHEN** an install runs with authentication bypassed
- **THEN** the header presents the other secondary actions but omits Log out, which is
  omitted from the overflow menu it sits in

#### Scenario: Header actions scroll away with the header
- **WHEN** a user on a pointer/desktop-width screen scrolls down a page
- **THEN** the header and its actions scroll out of view rather than remaining
  pinned to the viewport
