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
The system SHALL resolve all configuration exactly once at bootstrap into
immutable, typed configuration objects, applying documented defaults when a
value is absent.

Where each setting comes from is defined by `settings`. The directories that
locate the settings store itself, and the error-display switch, are read from
the environment; every other setting is resolved from the settings store, which
the environment seeds once. This requirement governs the bootstrap contract —
read once, immutable, typed, defaulted — not the source.

Every setting SHALL have a default that the system applies when the value is
absent or empty, and each of those defaults SHALL be asserted by a test. A
default is a promise about where a self-hosted install keeps its data; one that
nothing asserts can be moved by a refactor with no test failing and no user
warned. This holds for every setting the configuration reads, not only the ones
documented for users — an undocumented setting is still a location something
depends on.

Settings that name a filesystem directory SHALL default to a path on the
persistent `/config` volume, and SHALL have any trailing separator removed before
the value is exposed. Paths are composed by appending, so an untrimmed trailing
slash produces a doubled separator in every path built from it. Trimming SHALL
apply uniformly to every directory setting, so that none of them behaves
differently from its siblings.

The Plex authentication token SHALL come from the persisted connection store
written by signing in to Plex, and SHALL NOT be read from the environment, nor
from the settings store. A `PLEX_TOKEN` variable, if present, SHALL NOT be used
to authenticate to Plex — the system MAY read it only to tell the user it is no
longer used. Resolution SHALL still happen once at bootstrap into the same
immutable configuration object.

The Plex server address is resolved from the settings store like any other
setting, seeded from `PLEX_SERVER_URL`. Signing in supplies a credential, never
an address, and the two SHALL remain separately sourced: a credential is
obtained by an authorization the user performs, while an address is configuration.

#### Scenario: Default applied for missing variable
- **WHEN** an optional setting such as the site title is not set in the store or
  the environment
- **THEN** the corresponding configuration value uses its documented default
  ("Marquee")

#### Scenario: Directory settings default onto the persistent volume
- **WHEN** none of the directory variables is set
- **THEN** the poster directory, the data directory, and the session directory
  each resolve to their named default beneath `/config`

#### Scenario: A trailing separator is trimmed from a directory setting
- **WHEN** any directory variable is set to a path ending in `/`
- **THEN** the configuration exposes the path without the trailing separator
- **AND** the behaviour is the same for every directory setting

#### Scenario: Boolean and integer coercion
- **WHEN** a value expected to be boolean is set to `"1"`, `"true"`, `"yes"`,
  or `"on"` in any casing
- **THEN** the configuration exposes it as boolean `true`
- **WHEN** it is set to any other non-empty value
- **THEN** the configuration exposes it as boolean `false`
- **WHEN** a value expected to be an integer is set to a numeric string
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

#### Scenario: Configuration is resolved once per request
- **WHEN** a request is served
- **THEN** the settings store is read during bootstrap
- **AND** no later code path reads it again to answer that request

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
The system SHALL let a visitor sign in to Plex from within the application using
Plex's PIN authorization flow, and SHALL treat that sign-in as the way to log in
to Marquee. This is the only way to supply a Plex credential and the only way to
obtain a session. It SHALL be reachable without an existing session.

The flow SHALL open Plex's own sign-in page in a separate browser window and
poll for completion, rather than relying on a redirect back to Marquee. Marquee
therefore never needs to know its own externally reachable URL, and the flow
works unchanged behind a reverse proxy.

Closing that window SHALL end the wait. Nothing in the authorization request's
own state records that the user walked away — it stays pending at Plex until it
expires — so a flow that watched only the request would leave the control
reporting that it was waiting for Plex until the request's full lifetime elapsed.
The interface SHALL notice the window has gone and say so instead.

It SHALL still let the poll answer first, because Plex invites the user to close
the window once they have approved, so a close can arrive moments after a
successful approval. An approved sign-in SHALL therefore complete normally even
if the window is closed while it is being confirmed.

The system SHALL identify itself to Plex with a client identifier that is
generated once and persists across restarts, so that repeated sign-ins do not
accumulate duplicate device entries in the user's Plex account.

The system SHALL accept only the Plex account that owns the configured server,
and SHALL refuse any other, storing nothing and creating no session. Ownership
SHALL be established using the token being offered when nothing has been
recorded yet, because the check runs at the one moment no token is stored —
deciding it from stored configuration would refuse every first connection. Plex
prevents an unprivileged account from altering the library, but not from
deleting posters here — and a poster that never reached Plex has no upstream copy
to restore. Where ownership cannot be established the sign-in SHALL be refused,
because a check that passes when it cannot run is not a check. The refusal SHALL
NOT name the owner, who is by definition not the person reading it.

A successful sign-in SHALL store the token it was verified with and create a
session, whether or not a token was already stored. Signing in again is how a
user replaces a token they revoked in their Plex account; keeping the stored one
would leave that install unable to reach Plex with no action left to take. Only
a refusal leaves an existing connection untouched.

A refusal SHALL identify which of the two things went wrong: reaching the Plex
server, or the account that was used. Both refuse and store nothing, so they are
identical in effect and opposite in remedy — one is fixed in the compose file,
the other by signing in as somebody else. Reporting an unreachable server as an
ownership verdict tells the owner their own account is not theirs, and sends
them to the one place that is working.

This distinction is load-bearing now that signing in is also how Marquee is
entered. An install whose configured address is wrong cannot establish ownership,
and the screen that explains the address is the screen the visitor is already
looking at. A refusal that does not name the address setting leaves no way to
learn what is wrong.

The distinction SHALL follow what the server did, not why Marquee wanted to ask.
A server that answers and refuses the token has given an ownership answer: the
account has no access to it. A server that does not answer at all has given
none, and SHALL be reported as unreachable, naming the server address and the
server itself as what to check.

Where the ownership check fails because plex.tv cannot be reached, the system
SHALL report Plex as unavailable rather than as an ownership verdict. Nothing
about the account has been learned, so nothing about the account may be claimed.

#### Scenario: Successful sign-in
- **WHEN** the owner completes a sign-in on an install that already has a token
- **THEN** the system creates an authenticated session and takes the user into
  the application
- **AND** the token it was verified with replaces the stored one

#### Scenario: Signing in again replaces a revoked token
- **WHEN** the owner signs in on an install whose stored token has been revoked
  in their Plex account
- **THEN** the newly approved token replaces the stored one

#### Scenario: The first sign-in succeeds with nothing stored
- **WHEN** the owner signs in on an install that has no token stored yet
- **THEN** the system establishes ownership from the token being offered, not
  from stored configuration
- **AND** stores the token and creates an authenticated session

#### Scenario: An account that does not own the server is refused
- **WHEN** the approving Plex account does not own the configured server
- **THEN** the system stores no token, creates no session, and reports that the
  account does not own it
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
- **AND** no token is stored and no session is created

#### Scenario: A mistyped address explains itself on the login screen
- **WHEN** a first sign-in is attempted on an install whose configured server
  address cannot be reached
- **THEN** the screen the visitor is on names `PLEX_SERVER_URL` as what to check

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
- **THEN** the system stores no token, creates no session, and reports that
  sign-in did not complete
- **AND** any previously stored token is left untouched

#### Scenario: Closing the window ends the wait promptly
- **WHEN** the user closes the Plex window without approving
- **THEN** the interface stops reporting that it is waiting for Plex, rather than
  waiting for the authorization request to expire

#### Scenario: Closing the window just after approving still completes
- **WHEN** the user approves the request and closes the Plex window before the
  approval has been confirmed
- **THEN** the sign-in completes normally

#### Scenario: A blocked popup is not treated as abandonment
- **WHEN** the browser blocks the Plex window and the user follows the offered
  link instead
- **THEN** the interface keeps waiting for the approval

#### Scenario: Scheduled import uses the stored token
- **WHEN** a token was obtained by signing in
- **THEN** the scheduled auto-import authenticates to Plex with the stored token

#### Scenario: Signing out
- **WHEN** an authenticated user disconnects from Plex
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

### Requirement: A Plex connection is required to use the application
The system SHALL require a connected Plex server before any route that depends
on one may be used, redirecting to the connection screen until Plex is
connected.

Signing in is what satisfies both this gate and authentication, so a new
installation asks for one thing rather than two in sequence. Where a visitor has
neither a session nor a connection, both gates send them to the same screen, and
one sign-in clears both.

Both paths to the connection screen, the routes that start and poll a sign-in,
the logout route, the health endpoint, the web app manifest, static assets, and
the Poster Wall SHALL remain reachable while Plex is not connected. The wall is
exempt because it is specified to run unattended without anyone signing in; a
gate in front of it would break that.

#### Scenario: Gallery is unreachable until Plex is connected
- **WHEN** an authenticated user requests the gallery while Plex is not
  connected
- **THEN** the system redirects to the connection screen

#### Scenario: Connecting releases the gate
- **WHEN** a visitor with no session signs in to Plex on an install with no
  stored token
- **THEN** the previously gated routes are served normally
- **AND** the user is taken to the gallery with a confirmation, rather than left
  on the connection screen

#### Scenario: Authentication comes first
- **WHEN** an unauthenticated visitor requests a gated route while Plex is not
  connected
- **THEN** the system sends them to the sign-in path rather than the connection
  path

#### Scenario: Each gate uses the path that names what is missing
- **WHEN** an unauthenticated visitor requests a gated route, connected or not
- **THEN** the system sends them to the sign-in path

#### Scenario: A signed-in visitor with no connection is sent to the connection path
- **WHEN** an authenticated visitor requests a gated route while Plex is not
  connected
- **THEN** the system sends them to the connection path

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
Signing in to Plex is now how Marquee is entered, so the interface SHALL describe
the way in using Plex's own words. The vocabulary rule applies to leaving, where
two genuinely different actions remain and a wrong guess costs the user
something.

The interface SHALL describe ending the user's Marquee session as logging out,
and forgetting the Plex connection as disconnecting, and SHALL NOT use one set of
words for the other.

What each leaves behind SHALL be stated where both are offered together — the
connection screen — rather than on the controls themselves. Logging out is
reached from every page and is two words; loading it with an explanation of
something the user is not asking about makes the most-used control the largest
one on screen. The screen where the two are weighed against each other is where
the difference is worth spelling out.

The interface SHALL NOT present logging out as revoking Marquee's access to Plex.
Once the way in is called signing in to Plex, that is the reading a user arrives
at unaided, and it is wrong — the stored token survives logging out deliberately,
so that unattended imports keep working.

#### Scenario: The way in uses Plex's words
- **WHEN** the screen offers the action that both logs the user in and connects
  Plex
- **THEN** it describes it as signing in to Plex

#### Scenario: Connection controls use connection words
- **WHEN** the interface offers to leave the Plex connection
- **THEN** the control says disconnect rather than sign out

#### Scenario: The application's own session keeps its own words
- **WHEN** the interface offers to end the user's Marquee session
- **THEN** it says log out

#### Scenario: The two exits keep their own words
- **WHEN** the interface offers to end the user's Marquee session and to forget
  the Plex connection
- **THEN** the first says log out and the second says disconnect

#### Scenario: The screen offering both says what each leaves behind
- **WHEN** the connection screen offers disconnecting
- **THEN** it states that scheduled imports stop with it
- **AND** it states that logging out does neither

#### Scenario: The log out control is just the action
- **WHEN** the log out control renders in the navigation
- **THEN** it names the action and does not explain what survives it

#### Scenario: Logging out is not described as revoking Plex access
- **WHEN** logging out is offered or confirmed
- **THEN** the interface does not claim that it disconnects Plex or revokes
  Marquee's access to it

### Requirement: Later logins do not depend on the Plex server
The system SHALL record the owner it verified when a Plex sign-in succeeds, and
SHALL use the recorded value to decide later logins rather than asking the Plex
server again. plex.tv is still asked which account approved the request; the
answer is compared against what was recorded.

Ownership is established by asking the user's own Plex server who owns it. That
is correct when no token is stored and the candidate token is the only one there
is. As a check on every login it would make logging in depend on the user's Plex
server being reachable, so a server reboot would lock the owner out of Marquee —
where today it only prevents sending posters, and the rest of the application
still works.

Where no owner has been recorded — an install connected before this was
specified — the system SHALL perform the full check against the server and record
the result, so the first login after upgrading behaves exactly like a first
connection.

The recorded owner SHALL be held with the connection rather than in the database,
which is specified as a cache of Plex data that is safe to delete. Losing it
SHALL cost only the next login's server round trip, never access.

#### Scenario: A later login does not contact the Plex server
- **WHEN** the owner signs in on an install that has already recorded its owner
- **THEN** the system decides the sign-in without asking the Plex server who owns
  it

#### Scenario: The owner can log in while the Plex server is down
- **WHEN** the owner signs in while the configured Plex server cannot be reached,
  on an install that has already recorded its owner
- **THEN** the system creates an authenticated session

#### Scenario: A non-owner is still refused against the recorded owner
- **WHEN** an account that is not the recorded owner completes a sign-in
- **THEN** the system refuses it and creates no session

#### Scenario: An install with no recorded owner performs the full check
- **WHEN** the owner signs in on an install that has a token but no recorded
  owner
- **THEN** the system asks the Plex server who owns it, accepts the sign-in, and
  records the owner

### Requirement: An outstanding authorization request is reused
While a session's authorization request is unexpired, the system SHALL return
that request rather than asking Plex for a new one. A session SHALL hold at most
one outstanding authorization request.

Starting a sign-in is reachable without a session and is the only unauthenticated
action that causes an outbound request to plex.tv, holding a worker for the
duration of that round trip. Minting a new request per call would multiply every
repeated attempt into a plex.tv call and a parked worker.

This also removes a defect visible in ordinary use: activating the sign-in
control twice creates two authorization requests and abandons the first, so the
window the user is looking at is no longer the one being polled.

#### Scenario: Repeated starts return the same request
- **WHEN** a session starts a sign-in twice while the first request is unexpired
- **THEN** the second returns the same authorization request
- **AND** no second request is created at Plex

#### Scenario: An expired request is replaced
- **WHEN** a session starts a sign-in after its previous request has expired
- **THEN** the system creates a new authorization request

#### Scenario: Separate sessions get separate requests
- **WHEN** two different sessions each start a sign-in
- **THEN** each holds its own authorization request

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
was down — the same reason the connection gate reads configuration only.

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

### Requirement: Starting a sign-in is rate limited by the web server
The image SHALL ship a web server configuration that limits how often the route
that starts a sign-in may be requested. The limit SHALL be enforced before the
request reaches the application, because what it protects — the worker pool and
the store of sessions — is consumed by the request arriving at all.

The limit SHALL be coarse. It SHALL NOT attempt to identify individual clients,
and no configuration for trusting forwarded client addresses SHALL be introduced.
Behind a reverse proxy the limit degrades to one shared allowance, which is
acceptable because a 30-day sliding session makes the login route one a
legitimate user reaches roughly never; being refused there costs an established
session nothing.

The limit SHALL be generous enough that a person signing in, failing, and trying
again is never refused.

#### Scenario: Ordinary sign-in is never refused
- **WHEN** a user starts a sign-in, abandons it, and starts another
- **THEN** neither request is refused by the limit

#### Scenario: The limit is enforced ahead of the application
- **WHEN** requests to start a sign-in exceed the configured rate
- **THEN** the excess is refused without reaching the application

### Requirement: Sign-in and connection screen
The system SHALL provide one screen that is both where a visitor logs in and
where the Plex connection is managed. It SHALL be the only place the Plex
connection is managed; no other page SHALL offer to change it.

The screen SHALL be addressed by two paths: one that names signing in, reachable
without a session, and one that names the connection, requiring one. Each SHALL
redirect to the other when the visitor is in the wrong state, so that neither can
be reached showing something its path does not name. A URL that misdescribes what
it is showing reads as a fault, and "log in" is what a visitor without a session
needs to see.

The authentication gate SHALL send a visitor to the sign-in path and the
connection gate to the connection path. The connection gate runs inside the
authentication one, so anyone it turns away already has a session.

The screen SHALL offer a single action to a visitor who is not signed in:
signing in to Plex. Because that action is both the login and the connection,
presenting them as two choices would ask the user to pick between two names for
one thing.

When connected and signed in, the screen SHALL name the connected server using
the friendly name reported by the Plex server itself, and SHALL NOT display the
Plex account identifier, which is an email address. Where the name cannot be
obtained the screen SHALL still report that Plex is connected.

The screen SHALL offer disconnecting only to a signed-in user, and SHALL state
what disconnecting costs: that Marquee stops working until someone signs in
again, that scheduled imports stop, and that the Poster Wall keeps the posters
already imported but stops reporting what is playing. Naming the action alone
tells a user none of that, and the wall is the part they are least likely to
predict — it is specified to run unattended, so nobody is watching the screen
where the consequence appears.

The two exits SHALL be contrasted by naming each of them, rather than describing
one and referring to the other. A reader should not have to hold two consequences
in mind and map a pronoun back onto them to learn which action does what.

Because a Plex address cannot be supplied by signing in, the screen SHALL
distinguish a missing server address from a missing credential and say that the
address must be set in the environment.

When a `PLEX_TOKEN` variable is present in the environment, the screen SHALL
state that it is no longer used and that signing in replaces it, so that an
install disconnected by upgrading explains itself.

When `AUTH_USERNAME`, `AUTH_PASSWORD`, or `AUTH_BYPASS` is present in the
environment, the screen SHALL state that they are no longer used and that
signing in to Plex is now how Marquee is entered. `AUTH_BYPASS` SHALL be called
out specifically, because an install running on it has no login at all today and
will begin demanding one; its operator needs to be told why rather than meeting
it as a fault.

#### Scenario: Connected and signed in
- **WHEN** a token is stored, the server address is set, and the user is signed
  in
- **THEN** the screen names the connected server and offers to disconnect
- **AND** offers a way back to the gallery

#### Scenario: One action when signed out
- **WHEN** the screen renders for a visitor with no session
- **THEN** it offers signing in to Plex and no other action

#### Scenario: The sign-in path is reachable without a session
- **WHEN** a visitor with no session requests the sign-in path
- **THEN** the system serves the screen

#### Scenario: A signed-in visitor is sent from the sign-in path to the connection path
- **WHEN** a visitor with a session requests the sign-in path
- **THEN** the system redirects them to the connection path

#### Scenario: A signed-out visitor is sent from the connection path to the sign-in path
- **WHEN** a visitor with no session requests the connection path
- **THEN** the system redirects them to the sign-in path

#### Scenario: No way back while the gate is up
- **WHEN** the screen renders while Plex is not connected
- **THEN** it offers no link to the gallery, which the gate would refuse

#### Scenario: Disconnecting states its cost
- **WHEN** the screen offers to disconnect
- **THEN** it states that Marquee stops working until someone signs in again,
  that scheduled imports stop, and that the Poster Wall stops reporting what is
  playing

#### Scenario: Both exits are named rather than one implied
- **WHEN** the screen describes what disconnecting and logging out leave behind
- **THEN** each is named as the subject of its own statement

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

#### Scenario: Obsolete authentication variables explained
- **WHEN** `AUTH_USERNAME` or `AUTH_PASSWORD` is set in the environment
- **THEN** the screen states that it is no longer used and that signing in to
  Plex is how Marquee is entered

#### Scenario: A bypassed install is told its login is back
- **WHEN** `AUTH_BYPASS` is set in the environment
- **THEN** the screen states that it no longer disables the login and that
  signing in to Plex is now required

### Requirement: The documented configuration surface is chosen by audience
Documentation of an environment variable SHALL follow from who is expected to set
it, not from whether the code happens to read it. Reading a variable is not by
itself a reason to publish it.

Variables an install is expected to set SHALL be listed in the README's
configuration table with their default. Variables that exist only for local
development SHALL be documented in `docs/development-workflow.md` instead, where
the toolchain they serve is described, so the user-facing table stays a list of
decisions a user actually has to make.

The layout of the `/config` volume SHALL be presented as fixed. `DATA_DIR` and
`POSTERS_DIR` SHALL therefore remain absent from the README, even though the code
reads them: the README's promise is that backing up `/config` backs up
everything, and advertising the subpaths as movable invites installs that split
the volume and then discover the promise no longer holds for them. They remain
overridable for the operator who already knows they exist; they are not offered.

This split SHALL be asserted by a test, for the same reason each default is: a
decision recorded only in prose is one a later edit reverses without anything
failing. The test SHALL assert both directions — that the variables meant to be
documented are present where they belong, and that the ones deliberately withheld
are absent — so that neither an accidental removal nor an accidental addition
passes unnoticed.

#### Scenario: A developer-only setting is kept out of the user-facing table
- **WHEN** a reader looks up `DISPLAY_ERRORS`
- **THEN** it is described in `docs/development-workflow.md`
- **AND** it does not appear in the README's configuration table

#### Scenario: The volume layout reads as fixed
- **WHEN** a reader consults the README for what `/config` contains
- **THEN** the posters, data, and session directories are described as its
  contents
- **AND** no environment variable is offered for relocating them individually

#### Scenario: An undocumented setting is still pinned by a test
- **WHEN** a variable is deliberately left out of the user-facing documentation
- **THEN** its default is still asserted by a test, so that omitting it from the
  documentation does not also omit it from the guarantees

#### Scenario: Adding a withheld variable to the README fails a test
- **WHEN** an edit names `DATA_DIR` or `POSTERS_DIR` in the README
- **THEN** a test fails, requiring the decision to be overturned deliberately
  rather than drifting

#### Scenario: Removing a documented variable from the README fails a test
- **WHEN** an edit removes a variable an install is expected to set, such as
  `SESSION_DIR`, from the README
- **THEN** a test fails, so the absence assertions cannot be satisfied by
  documentation that has gone missing entirely

