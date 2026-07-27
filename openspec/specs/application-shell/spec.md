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
SHALL identify the software.

#### Scenario: Pages extend the base layout
- **WHEN** any HTML page is rendered
- **THEN** it extends the shared base layout and displays the configured
  `SITE_TITLE` as the brand in the page header

#### Scenario: Footer names the product
- **WHEN** any HTML page is rendered
- **THEN** its footer displays the product name and the current version,
  regardless of how `SITE_TITLE` is configured

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

