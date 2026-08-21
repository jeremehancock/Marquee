## ADDED Requirements

### Requirement: In-app support ask
The application SHALL present its request for financial support as an overlay over
the page the user is already on, rather than by sending them to the project's
marketing site. The ask is one mark, one heading, one paragraph and one button —
small enough to be answered where it is made, and small enough that a page load
spent on it is a page load the user did not ask for. Reading it SHALL leave the
user exactly where they were, with nothing to navigate back from.

The overlay SHALL be available from every page that renders navigation, and SHALL
NOT depend on any page's own interactive state. Its content is fixed text and a
fixed link, so unlike the trays that hold Import from Plex, Orphans and Settings
it SHALL NOT fetch anything, and SHALL NOT fall back to navigating on a page that
cannot host it — there is no such page.

The overlay SHALL hold: a heart mark drawn from the application's icon set,
matching the one the Support Development navigation entry already wears; the
heading "Support development"; the project's support copy, stating that Marquee is
free and always will be and inviting a contribution to ongoing development; and a
single accented button labelled "Hard drive fund" linking to the project's Buy Me a
Coffee page. That button SHALL open in a new browsing context — the payment page is
genuinely elsewhere, and it is the only thing in the overlay that leaves the app.

The mark SHALL be presented with the heading rather than as an element of its own
above the copy. The project site centres its version of this ask, but the site's
card has no title bar and its heading is therefore the top of the composition;
an overlay's heading sits in a head beside the close control, so a mark centred
beneath that head belongs to neither the heading it names nor the copy it sits
above.

Activating the button SHALL also dismiss the overlay. The payment page opens
alongside the current page rather than replacing it, so an overlay left standing
is one the user returns from their contribution to find still asking for it. This
matches how the application already treats a destination that opens in a new
browsing context: the actions tray dismisses itself when Poster Wall is chosen.

The overlay SHALL use the application's shared dialog presentation and therefore
SHALL take the two forms that presentation already gives: on a pointer/desktop
screen a centred dialog with its own close control, and on a narrow screen an
app-style bottom tray with a grab handle, dismissable by the drag gesture, the
backdrop and Escape like every other tray. It SHALL NOT introduce a second overlay
system to achieve this.

The overlay SHALL be dismissable without contributing, by every means the shared
presentation offers, and dismissing it SHALL leave the page beneath it untouched —
not reloaded, not navigated, not scrolled.

The overlay SHALL declare itself a dialog and SHALL be focus-managed on the same
terms as every other overlay in the application: focus moves onto it when it opens
and returns to the control that opened it when it closes.

#### Scenario: The support ask opens over the current page

- **WHEN** a user activates Support Development from the desktop header's overflow
  menu or from the narrow-screen actions tray
- **THEN** the support ask opens as an overlay over the page they were on
- **AND** no navigation occurs and no new browsing context is opened
- **AND** the page beneath it is left in place

#### Scenario: The support ask holds the ask and one way to answer it

- **WHEN** the support overlay is open
- **THEN** it shows the heart mark, the heading "Support development", the
  project's support copy, and a "Hard drive fund" button
- **AND** that button links to the project's Buy Me a Coffee page and opens it in a
  new browsing context
- **AND** the mark is presented with the heading rather than as a separate element
  above the copy

#### Scenario: Contributing dismisses the ask

- **WHEN** a user activates the "Hard drive fund" button
- **THEN** the payment page opens in a new browsing context
- **AND** the overlay closes rather than remaining over the page behind it

#### Scenario: Presented as a dialog on desktop and a tray on a phone

- **WHEN** the support overlay is opened on a pointer/desktop-width screen
- **THEN** it is presented as a centred dialog carrying its own close control
- **WHEN** it is opened on a narrow screen
- **THEN** it is presented as an app-style bottom tray carrying a grab handle

#### Scenario: The support ask is dismissed without contributing

- **WHEN** the support overlay is open and the user activates its close control,
  taps the backdrop, presses Escape, or drags the tray down on a narrow screen
- **THEN** the overlay closes
- **AND** the page beneath it is neither reloaded nor navigated away from

#### Scenario: The support ask is reachable from every page with navigation

- **WHEN** a user is on the Import from Plex page or the Orphaned posters page, at
  any width
- **THEN** Support Development still opens the overlay in place rather than
  navigating
- **AND** this holds without the page providing anything of its own

#### Scenario: Focus is managed like every other overlay

- **WHEN** a keyboard user opens the support overlay
- **THEN** focus moves onto the dialog and its name is announced
- **WHEN** they dismiss it
- **THEN** focus returns to the control they opened it from

## MODIFIED Requirements

### Requirement: App-wide mobile actions menu
The shared layout SHALL provide an app-wide **actions** menu for small screens so
that the application's secondary actions are reachable from every authenticated
page without crowding the content. The menu's entries are predominantly actions
rather than in-place navigation destinations: on a narrow screen Import from Plex,
Orphans, Settings and Support Development open as trays over the current page,
Poster Wall opens in a new browsing context, and Log out is an action. The
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
Log out: configuration, the support ask, and the session exit, none of which is
part of managing posters. The header's contents are aligned to the content column
rather than the viewport, and share that width with a brand of unbounded length, so
six labelled actions plus a status do not reliably fit; the split is what keeps the
bar within its width at any site title rather than only at short ones.

Support Development SHALL open the application's own support ask as a dialog over
the current page — see "In-app support ask" — rather than navigating or opening a
new browsing context, and choosing it SHALL dismiss the overflow menu. It is
therefore never a page one can be viewing: it SHALL NOT be markable as the current
destination, and SHALL NOT cause the overflow control to be marked as current.

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
group reads as one set of actions. Support Development SHALL likewise keep that
form despite opening an overlay rather than a page, so what changes is where it
leads and not how it reads. Log out SHALL remain gated on authentication
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

#### Scenario: Support Development opens the support dialog from the overflow menu
- **WHEN** a user on a pointer/desktop-width screen chooses Support Development
  from the overflow menu
- **THEN** the overflow menu closes and the support ask opens as a centred dialog
  over the current page
- **AND** no navigation occurs and no new browsing context is opened

#### Scenario: Support Development is never the current destination
- **WHEN** the header is rendered on any page
- **THEN** Support Development is presented as an action rather than as a current
  page, on every page
- **AND** the overflow control is not marked as current on its account

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
