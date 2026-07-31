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

### Requirement: App-style tray dismissal
Every bottom-sheet tray in the application (the navigation menu, and the
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
- **THEN** the brand, the desktop Log out link, the menu control, and the
  navigation tray are presented and behave as their own requirements specify,
  unaffected by the header's alignment or colour

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

