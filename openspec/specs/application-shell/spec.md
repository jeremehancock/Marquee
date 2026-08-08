# Application Shell Specification

## Purpose

The foundation every other capability sits on: a single HTTP entry point, typed
configuration read once from the environment, centralized error handling and
logging, a health endpoint for container orchestration, and server-rendered
pages built on a shared layout.

This capability owns *how the application runs*, not what it does. Anything
about posters, Plex, or the gallery belongs elsewhere.
## Requirements
### Requirement: HTTP application bootstrap
The system SHALL serve all HTTP traffic through a single public front
controller that builds a dependency-injection container, registers middleware,
and dispatches to route handlers.

#### Scenario: Front controller handles a known route
- **WHEN** a request arrives for a registered route
- **THEN** the front controller dispatches it to the matching handler and
  returns the handler's response

#### Scenario: Unknown route returns 404
- **WHEN** a request arrives for a path with no registered route
- **THEN** the system responds with HTTP 404 and a rendered not-found page

### Requirement: Typed configuration from environment
The system SHALL read all configuration from environment variables exactly once
at bootstrap into immutable, typed configuration objects, applying documented
defaults when a variable is absent.

The Plex authentication token is the one exception: it SHALL come from the
persisted connection store written by signing in to Plex, and SHALL NOT be read
from the environment. A `PLEX_TOKEN` variable, if present, SHALL NOT be used to
authenticate to Plex — the system MAY read it only to tell the user it is no
longer used. Resolution SHALL still happen once at bootstrap into the same
immutable configuration object; no other setting gains a second source.

The Plex server address remains an environment variable. Signing in supplies a
credential, never an address.

#### Scenario: Default applied for missing variable
- **WHEN** an optional environment variable such as `SITE_TITLE` is not set
- **THEN** the corresponding configuration value uses its documented default
  ("Marquee")

#### Scenario: Boolean and integer coercion
- **WHEN** a variable expected to be boolean is set to `"1"`, `"true"`, `"yes"`,
  or `"on"` in any casing
- **THEN** the configuration exposes it as boolean `true`
- **WHEN** it is set to any other non-empty value
- **THEN** the configuration exposes it as boolean `false`
- **WHEN** a variable expected to be an integer is set to a numeric string
- **THEN** the configuration exposes it as an integer

#### Scenario: Stored token is the credential
- **WHEN** a token has been stored by signing in to Plex
- **THEN** Plex requests are made with the stored token

#### Scenario: An environment token is not a credential
- **WHEN** `PLEX_TOKEN` is set in the environment and no token has been stored
- **THEN** the system treats Plex as not connected
- **AND** no Plex request is made with the value of `PLEX_TOKEN`

#### Scenario: No token at all
- **WHEN** no token has been stored
- **THEN** the system reports Plex as not connected

### Requirement: Health endpoint
The system SHALL expose an unauthenticated `GET /health` endpoint that reports
service readiness for container healthchecks.

#### Scenario: Health check without authentication
- **WHEN** an unauthenticated client requests `GET /health`
- **THEN** the system responds with HTTP 200 and a JSON body indicating status
  "ok" without requiring a session

### Requirement: Centralized error handling and logging
The system SHALL catch unhandled errors, render a safe error response, and log
diagnostic detail to a file under the data directory without exposing stack
traces to the client.

#### Scenario: Unhandled error is logged and hidden
- **WHEN** a request handler throws an uncaught exception
- **THEN** the system responds with a generic HTTP 500 error page
- **AND** the exception detail is written to the application log
- **AND** no stack trace is included in the HTTP response

#### Scenario: JSON error for API clients
- **WHEN** a request with `Accept: application/json` triggers an error
- **THEN** the system responds with a JSON error object and an appropriate
  HTTP status code

### Requirement: Server-rendered pages with shared layout
The system SHALL render HTML pages with a templating engine using a shared base
layout, exposing both the configured site title and the fixed product name to
every page. The configured site title SHALL identify the site; the product name
SHALL identify the software. Wherever the layout presents the product name as
footer chrome — the page footer and the navigation drawer's footer — that name
SHALL link to the project website.

#### Scenario: Pages extend the base layout
- **WHEN** any HTML page is rendered
- **THEN** it extends the shared base layout and displays the configured
  `SITE_TITLE` as the brand in the page header

#### Scenario: Footer names the product
- **WHEN** any HTML page is rendered
- **THEN** its footer displays the product name and the current version,
  regardless of how `SITE_TITLE` is configured

#### Scenario: Page footer links to the project website
- **WHEN** a user activates the product name in the page footer
- **THEN** the project website at `https://getmarquee.now` opens in a new
  browsing context
- **AND** the version text and any update note continue to be displayed
  alongside it

#### Scenario: Drawer footer links to the project website
- **WHEN** a user activates the product name in the navigation drawer's footer
- **THEN** the project website at `https://getmarquee.now` opens in a new
  browsing context, leaving the drawer's page in place
- **AND** the version text and any update note continue to be displayed
  alongside it

#### Scenario: Product name is not configurable
- **WHEN** the application reads its configuration from the environment
- **THEN** the product name is a fixed value that no environment variable can
  override
- **AND** `SITE_TITLE` defaults to that same product name, so an install that
  does not set it presents the product name throughout

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

Tooltips are a pointer-device affordance. The shared tooltip SHALL be shown only
on a device whose primary input can hover with a fine pointer (a mouse or
trackpad). On a touch device no tooltip SHALL be shown by any trigger — pointer
events, the focus a tap places on a control, or programmatic display — so a tap
never leaves a hover hint stranded over the interface. The device capability
SHALL be evaluated at the moment a tooltip would be shown rather than only once
at page load, so a device whose input situation changes (a touchscreen laptop, or
a tablet that gains or loses a mouse) is judged by its current capability; a
tooltip already on screen SHALL be dismissed if the device stops qualifying.

A tooltip whose only purpose is to reveal text the host element has visually
truncated SHALL be shown only while that host is actually truncated. Such a host
SHALL declare that its tooltip is conditional, and the system SHALL determine
truncation from the element's rendered size at the moment a tooltip would be
shown — not once at page load — so a host that changes width (a window resize, a
different breakpoint, a late-loading font) is judged by its current state. A host
whose text fits SHALL show no tooltip. Hosts that carry a genuine hint rather
than a repetition of their own visible text SHALL be unaffected and SHALL always
show their tooltip.

Suppressing tooltips SHALL NOT remove any information a touch user needs: every
tooltip host SHALL remain usable and SHALL retain its accessible name, which
assistive technology exposes on every device regardless of tooltip suppression.

A non-interactive element that carries a tooltip (such as a poster caption) SHALL
present a cursor that signals a tooltip — a `help` cursor — rather than the
text/I-beam cursor used for editable text, so hovering it reads as "more
information is available" rather than "edit this." That cursor SHALL follow the
same condition as the tooltip itself: a host whose tooltip is suppressed because
its text is not truncated SHALL NOT present the `help` cursor, so the cursor
never promises a tooltip that will not appear. Interactive tooltip hosts (links
and buttons) SHALL keep their normal pointer affordance.

Tooltip text SHALL be phrased for the pointer users who are the only audience
that can see it, and SHALL NOT instruct the reader to tap.

#### Scenario: Custom tooltip replaces the native tooltip
- **WHEN** a user on a hover-capable pointer device hovers an element that offers
  a tooltip
- **THEN** the themed custom tooltip is shown and no separate native browser
  tooltip appears

#### Scenario: Tooltip works on keyboard focus
- **WHEN** a user on a hover-capable pointer device moves keyboard focus to a
  focusable element that offers a tooltip
- **THEN** the custom tooltip is shown for that element

#### Scenario: Tooltip works on dynamically added content
- **WHEN** an element with a tooltip is added to the page after initial load
  (such as paginated results or client-rendered previews)
- **THEN** hovering it on a hover-capable pointer device shows the custom tooltip
  just as for elements present at load

#### Scenario: Icon-only control keeps its accessible name
- **WHEN** an icon-only control that previously relied on a native `title` is
  converted to the custom tooltip
- **THEN** the control still exposes an accessible name to assistive technology

#### Scenario: Truncated text shows its tooltip
- **WHEN** a user on a hover-capable pointer device hovers a host whose tooltip
  is conditional on truncation and whose text is too long to fit, so it is shown
  with an ellipsis
- **THEN** the custom tooltip is shown with the full text

#### Scenario: Text that fits shows no tooltip
- **WHEN** a user on a hover-capable pointer device hovers a host whose tooltip
  is conditional on truncation and whose text fits entirely within it
- **THEN** no tooltip is shown, because the tooltip would only repeat text that
  is already fully visible

#### Scenario: Truncation is re-evaluated, not cached
- **WHEN** a host that fits at one window size is narrowed until its text
  truncates (or a truncated host is widened until its text fits)
- **THEN** the next hover is judged by the host's current rendered size, showing
  the tooltip only if the text is truncated at that moment

#### Scenario: Hint tooltips are not conditional
- **WHEN** a user hovers a tooltip host whose text is a hint rather than a
  repetition of the host's own visible text (such as a pagination step or the
  Sort trigger)
- **THEN** the tooltip is shown regardless of any truncation

#### Scenario: Non-interactive tooltip host signals a tooltip
- **WHEN** a user hovers a non-interactive element that carries a tooltip (such
  as a truncated poster caption)
- **THEN** the cursor indicates a tooltip (a `help` cursor) rather than the
  text/I-beam cursor

#### Scenario: Host with no tooltip to show signals nothing
- **WHEN** a user hovers a non-interactive host whose conditional tooltip is
  suppressed because its text is not truncated
- **THEN** the cursor does not indicate a tooltip

#### Scenario: Touch device never shows a tooltip
- **WHEN** a user on a touch-only device touches, long-presses, or scrolls over
  an element that offers a tooltip
- **THEN** no tooltip is shown

#### Scenario: Tapping a control that offers a tooltip
- **WHEN** a user on a touch-only device taps a focusable control that offers a
  tooltip (such as the Sort trigger) and the tap moves focus to that control
- **THEN** no tooltip is shown, and the control performs its normal action

#### Scenario: Device capability is re-evaluated, not cached
- **WHEN** the device's pointer capability changes after page load
- **THEN** the next tooltip trigger is judged by the current capability, and a
  tooltip that is on screen when the device stops qualifying is dismissed

#### Scenario: Tooltip text addresses a pointer user
- **WHEN** a tooltip presents an instruction for interacting with its host
- **THEN** the wording refers to clicking rather than tapping

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

### Requirement: Persisted state is recreatable
Everything Marquee persists SHALL be recreatable from Plex: the poster files
under the posters directory and the SQLite database under the data directory
together form a cache of Plex's artwork and of the mapping back to the Plex items
it came from. Removing either SHALL return Marquee to its first-run state rather
than a broken one — the system SHALL recreate the database schema and any missing
directory on demand, without manual repair and without a reinstall.

The one thing this invariant does not preserve is artwork that never left
Marquee. A poster the user applied to an item was uploaded to Plex and locked
there, so a later import brings it back; a poster only ever stored locally has no
upstream copy and is gone. That boundary is what makes a hand-run reset safe to
document, so the system MUST NOT begin persisting state that only exists locally
and cannot be rebuilt from Plex.

Connection credentials are the single exception, and they do not weaken the
invariant. A token obtained by signing in cannot be rebuilt from Plex, because
it is what reaches Plex in the first place. Losing it SHALL return the user to
the sign-in prompt — which is first-run state, not a broken one — and the system
SHALL keep it outside the SQLite database so that deleting the database remains
a safe reset that costs only the cache.

#### Scenario: Database removed
- **WHEN** the SQLite database file is deleted while the container is stopped and
  Marquee is started again
- **THEN** the system recreates the schema on first use and serves pages normally
- **AND** a subsequent import rebuilds the Plex item mappings from scratch

#### Scenario: Posters directory removed
- **WHEN** the posters directory or one of its category directories is missing
- **THEN** the gallery reports that category as empty rather than failing
- **AND** the next import recreates the directory and stores posters into it

#### Scenario: Reset returns art that Plex holds
- **WHEN** a user removes both the posters directory and the database, restarts,
  and runs an import
- **THEN** every poster the user had previously sent to Plex is imported back,
  because Plex holds and locks that artwork

#### Scenario: Deleting the database keeps the connection
- **WHEN** the SQLite database is deleted and Marquee is restarted
- **THEN** a previously stored Plex token still authenticates requests, because
  it is not held in the database

#### Scenario: Losing the stored credential returns to first-run
- **WHEN** the stored Plex token is removed
- **THEN** the system presents the sign-in prompt rather than an error state

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

### Requirement: Header brand mark matches the logo asset
The brand mark rendered inline in the page header SHALL draw the same shapes as
`public/assets/logo.svg`, the canonical source of the mark. The header markup is
a duplicate of that asset kept inline so it can be styled and animated, so any
edit to the logo SHALL be reflected in both. A user SHALL never see one mark in
the browser tab or on their home screen and a different one in the header.

#### Scenario: Header mark and logo asset draw the same shapes
- **WHEN** a page rendered from the shared layout is served
- **THEN** the inline brand SVG in the header contains every path and rect
  geometry declared by `public/assets/logo.svg`
- **AND** it declares no shape geometry that the asset does not

#### Scenario: Editing the logo asset alone is detectable
- **WHEN** `public/assets/logo.svg` is changed without the inline header copy
  being updated to match
- **THEN** the application-shell test suite fails, rather than the two marks
  silently diverging

### Requirement: Poster provider attribution

The shared layout SHALL credit the providers whose artwork reaches the user
through Marquee's poster source: TMDB, TheTVDB and fanart.tv. The credit SHALL
consist of the label `Posters provided by:` followed by each provider's logo,
and each logo SHALL link to that provider's own website in a new browsing
context. The logos SHALL be served as local static assets, so the credit renders
without a request to any third party.

The credit SHALL be part of the same footer chrome that carries the product name
and version, and SHALL be reachable on every screen size: on a pointer/desktop
screen it appears in the page footer, and on a narrow screen — where the page
footer is hidden — it appears in the navigation drawer's footer. In both places
it SHALL sit above the product name and version rather than replacing them.

The provider list SHALL be defined in exactly one place, so the two footers can
never credit different sets of providers. Providers whose artwork the poster
source does not return SHALL NOT be credited.

#### Scenario: Page footer credits the providers

- **WHEN** any HTML page is rendered
- **THEN** its footer displays the label `Posters provided by:` with the TMDB,
  TheTVDB and fanart.tv logos
- **AND** the product name and version continue to be displayed below them

#### Scenario: Provider logo links to the provider

- **WHEN** a user activates a provider's logo in either footer
- **THEN** that provider's own website opens in a new browsing context, leaving
  the current page in place

#### Scenario: Attribution is reachable on a phone

- **WHEN** a user on a narrow screen opens the navigation drawer
- **THEN** the drawer's footer displays the same credit line and logos, above
  its product name and version
- **AND** the page footer, which is hidden at that width, is not the only place
  the credit appears

#### Scenario: Logos are served locally

- **WHEN** a page carrying the attribution is rendered
- **THEN** each logo resolves to an asset served by Marquee itself
- **AND** no provider logo is loaded from a third-party host

#### Scenario: Uncredited providers are absent

- **WHEN** the attribution is rendered
- **THEN** it credits only providers the poster source returns artwork from
- **AND** Mediux, which it does not, is absent

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

### Requirement: Sign in to Plex from the application
The system SHALL let an authenticated user obtain a Plex token by signing in to
Plex from within the application, using Plex's PIN authorization flow. This is
the only way to supply a Plex credential.

The flow SHALL open Plex's own sign-in page in a separate browser window and
poll for completion, rather than relying on a redirect back to Marquee. Marquee
therefore never needs to know its own externally reachable URL, and the flow
works unchanged behind a reverse proxy.

The system SHALL identify itself to Plex with a client identifier that is
generated once and persists across restarts, so that repeated sign-ins do not
accumulate duplicate device entries in the user's Plex account.

The system SHALL accept only the Plex account that owns the configured server,
and SHALL refuse any other, storing nothing. Ownership SHALL be established
using the token being offered, because the check runs at the one moment no
token is stored — deciding it from stored configuration would refuse every
first connection. Plex prevents an unprivileged
account from altering the library, but not from deleting posters here — and a
poster that never reached Plex has no upstream copy to restore. Where ownership
cannot be established the sign-in SHALL be refused, because a check that passes
when it cannot run is not a check. The refusal SHALL NOT name the owner, who is
by definition not the person reading it.

A refusal SHALL identify which of the two things went wrong: reaching the Plex
server, or the account that was used. Both refuse and store nothing, so they are
identical in effect and opposite in remedy — one is fixed in the compose file,
the other by signing in as somebody else. Reporting an unreachable server as an
ownership verdict tells the owner their own account is not theirs, and sends
them to the one place that is working.

The distinction SHALL follow what the server did, not why Marquee wanted to ask.
A server that answers and refuses the token has given an ownership answer: the
account has no access to it. A server that does not answer at all has given
none, and SHALL be reported as unreachable, naming the server address and the
server itself as what to check.

Where the ownership check fails because plex.tv cannot be reached, the system
SHALL report Plex as unavailable rather than as an ownership verdict. Nothing
about the account has been learned, so nothing about the account may be claimed.

#### Scenario: Successful sign-in
- **WHEN** an authenticated user starts sign-in and approves Marquee in Plex
- **THEN** the system stores the returned token and reports Plex as connected

#### Scenario: The first sign-in succeeds with nothing stored
- **WHEN** the owner signs in on an install that has no token stored yet
- **THEN** the system establishes ownership from the token being offered, not
  from stored configuration, and accepts the sign-in

#### Scenario: An account that does not own the server is refused
- **WHEN** the approving Plex account does not own the configured server
- **THEN** the system stores no token and reports that the account does not own
  it
- **AND** any previously stored token is left untouched

#### Scenario: Ownership that cannot be established is refused
- **WHEN** the server does not report an owner, or the account behind the token
  cannot be identified
- **THEN** the system refuses the sign-in rather than treating it as permitted

#### Scenario: An unreachable server is not reported as an ownership failure
- **WHEN** the configured server address is wrong, the Plex server is not
  running, or the network between them refuses the connection
- **THEN** the system reports that it could not reach the Plex server
- **AND** it does not report that the account does not own the server
- **AND** the message names the server address setting and the server itself as
  what to check
- **AND** no token is stored

#### Scenario: A server that refuses the token is an ownership answer
- **WHEN** the configured server answers the ownership request by rejecting the
  token as unauthorised
- **THEN** the system reports that the account does not own the server, rather
  than reporting the server as unreachable

#### Scenario: plex.tv being unavailable is not an ownership verdict
- **WHEN** the account behind the token cannot be identified because plex.tv
  cannot be reached
- **THEN** the system reports that Plex is unavailable
- **AND** it does not report that the account does not own the server
- **AND** no token is stored

#### Scenario: The refusal does not identify the owner
- **WHEN** a sign-in is refused because the account does not own the server
- **THEN** the message does not disclose the owner's account

#### Scenario: Sign-in not completed
- **WHEN** the user closes the Plex window without approving, or the
  authorization request expires
- **THEN** the system stores no token and reports that sign-in did not complete
- **AND** any previously stored token is left untouched

#### Scenario: Scheduled import uses the stored token
- **WHEN** a token was obtained by signing in
- **THEN** the scheduled auto-import authenticates to Plex with the stored token

#### Scenario: Signing out
- **WHEN** an authenticated user signs out of Plex
- **THEN** the system removes the stored token and reports Plex as not connected

### Requirement: The stored Plex token is never disclosed
The system SHALL NOT render a Plex token into any page, response body, or log
entry. The connection is described by the name of the connected server, never by
the credential.

An in-progress sign-in SHALL be bound to the session that started it, so that
one browser session cannot complete or claim an authorization request begun by
another.

#### Scenario: Token absent from the connection screen
- **WHEN** any connection state renders
- **THEN** the page contains no Plex token

#### Scenario: Authorization request is not transferable
- **WHEN** a session polls an authorization request that a different session
  started
- **THEN** the system refuses it and stores no token

#### Scenario: Sign-in details stay out of the log
- **WHEN** a sign-in succeeds or fails
- **THEN** no log entry contains the token

### Requirement: Plex connection screen
The system SHALL provide a dedicated connection screen, reachable from the
application's navigation, that reports whether Plex is connected and offers to
connect or disconnect. It SHALL be the only place the Plex connection is managed;
no other page SHALL offer to change it.

When connected, the screen SHALL name the connected server using the friendly
name reported by the Plex server itself, and SHALL NOT display the Plex account
identifier, which is an email address. Where the name cannot be obtained the
screen SHALL still report that Plex is connected.

Because a Plex address cannot be supplied by signing in, the screen SHALL
distinguish a missing server address from a missing credential and say that the
address must be set in the environment.

When a `PLEX_TOKEN` variable is present in the environment, the screen SHALL
state that it is no longer used and that signing in replaces it, so that an
install disconnected by upgrading explains itself.

When authentication is bypassed, the screen SHALL warn that anyone who can reach
Marquee acts with the stored Plex connection — able to change and delete
posters, send artwork to the user's Plex library, and disconnect the install. It
SHALL NOT describe the risk as the ability to connect Marquee to Plex, which
only the server's owner can do. The distinction is the point: restricting who
may connect does not restrict what a visitor may do with a connection that
already exists, and the opposite reading is the one a user arrives at unaided.

#### Scenario: Connected
- **WHEN** a token is stored and the server address is set
- **THEN** the screen names the connected server and offers to sign out
- **AND** offers a way back to the gallery

#### Scenario: No way back while the gate is up
- **WHEN** the screen renders while Plex is not connected
- **THEN** it offers no link to the gallery, which the gate would refuse

#### Scenario: Not connected
- **WHEN** no token is stored
- **THEN** the screen reports that Plex is not connected and offers to sign in

#### Scenario: Server name unavailable
- **WHEN** the connected server's name cannot be read
- **THEN** the screen still reports that Plex is connected rather than failing

#### Scenario: Server address missing
- **WHEN** no Plex server address is configured
- **THEN** the screen says the address must be set in the environment
- **AND** does not present signing in as the remedy

#### Scenario: Obsolete environment token explained
- **WHEN** `PLEX_TOKEN` is set in the environment
- **THEN** the screen states that it is no longer used and that signing in
  replaces it

#### Scenario: Bypassed authentication is called out
- **WHEN** authentication is bypassed and the connection screen renders
- **THEN** the screen warns that anyone who can reach Marquee acts with the
  stored Plex connection

#### Scenario: The warning describes use, not connection
- **WHEN** the bypass warning renders
- **THEN** it names changing or deleting posters and altering the Plex library
- **AND** does not claim that a visitor could connect Marquee to Plex

#### Scenario: No warning when authentication is enforced
- **WHEN** authentication is enforced and the connection screen renders
- **THEN** no such warning appears

### Requirement: A Plex connection is required to use the application
The system SHALL require a connected Plex server before any route that depends
on one may be used, redirecting to the connection screen until Plex is
connected. Connecting is therefore the first thing a new installation asks for.

This gate SHALL apply after authentication, so a visitor signs in to Marquee
before being asked to connect Plex.

The connection screen itself, the login and logout routes, the health endpoint,
the web app manifest, static assets, and the Poster Wall SHALL remain reachable
while Plex is not connected. The wall is exempt because it is specified to run
unattended without anyone signing in; a gate in front of it would break that.

#### Scenario: Gallery is unreachable until Plex is connected
- **WHEN** an authenticated user requests the gallery while Plex is not
  connected
- **THEN** the system redirects to the connection screen

#### Scenario: Connecting releases the gate
- **WHEN** a user signs in to Plex successfully
- **THEN** the previously gated routes are served normally
- **AND** the user is taken to the gallery with a confirmation, rather than left
  on the connection screen

#### Scenario: Authentication comes first
- **WHEN** an unauthenticated visitor requests a gated route while Plex is not
  connected
- **THEN** the system redirects to login rather than to the connection screen

#### Scenario: The wall runs without a Plex connection
- **WHEN** the poster wall is requested while Plex is not connected
- **THEN** the system serves it rather than redirecting

#### Scenario: Health stays reachable
- **WHEN** the health endpoint is requested while Plex is not connected
- **THEN** the system serves it

### Requirement: Plex failures name the applicable remedy
When a Plex request fails, the system SHALL describe a remedy the user can act
on. Because a Plex credential can only come from signing in, a rejected
credential SHALL direct the user to sign in to Plex again, and no message SHALL
instruct the user to check an environment variable that is no longer read.

This applies wherever a Plex operation can fail, including sending a poster to
Plex, fetching one from it, importing, detecting orphans, and the scheduled
auto-import.

#### Scenario: Rejected credential
- **WHEN** Plex rejects the credential
- **THEN** the message advises signing in to Plex again and offers a way to do so

#### Scenario: No message names the obsolete variable
- **WHEN** any Plex failure is reported
- **THEN** the message does not instruct the user to check `PLEX_TOKEN`

#### Scenario: Server unreachable
- **WHEN** the Plex server cannot be reached
- **THEN** the message names the server address as the thing to check

### Requirement: Transient confirmations clear themselves
A flash message confirming something the user just did SHALL disappear on its
own after a few seconds. Messages reporting a failure or a caveat SHALL remain
until the page changes, because they carry a reason the user has to read and one
that vanishes mid-sentence is worse than none.

#### Scenario: A success message clears itself
- **WHEN** a success flash renders
- **THEN** it is removed from the page a few seconds later

#### Scenario: A failure message stays
- **WHEN** an error or warning flash renders
- **THEN** it remains until the user navigates away

### Requirement: The Plex connection and the login read as different things
The interface SHALL describe joining and leaving the Plex connection as
connecting and disconnecting, and reserve logging in and out for the
application's own authentication. Naming both "signing in" invites the reading
that they are one mechanism, which is the confusion this vocabulary exists to
prevent.

#### Scenario: Connection controls use connection words
- **WHEN** the connection screen offers to join or leave the Plex connection
- **THEN** the controls and confirmations say connect and disconnect rather than
  sign in and sign out

#### Scenario: The application's own session keeps its own words
- **WHEN** the interface offers to end the user's Marquee session
- **THEN** it says log out

