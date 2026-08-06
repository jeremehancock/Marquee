## MODIFIED Requirements

### Requirement: Typed configuration from environment
The system SHALL read all configuration from environment variables exactly once
at bootstrap into immutable, typed configuration objects, applying documented
defaults when a variable is absent.

The Plex authentication token MAY additionally come from a persisted store
written by the in-application sign-in flow. When both sources supply a token the
environment variable SHALL win, so a deployment that sets `PLEX_TOKEN` behaves
exactly as it did before the store existed. Resolution SHALL still happen once
at bootstrap into the same immutable configuration object; no other setting
gains a second source.

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

#### Scenario: Environment token wins over a stored token
- **WHEN** `PLEX_TOKEN` is set and a token has also been stored by signing in
- **THEN** the configuration exposes the environment token
- **AND** Plex requests are made with the environment token

#### Scenario: Stored token used when the variable is absent
- **WHEN** `PLEX_TOKEN` is unset or empty and a token has been stored by
  signing in
- **THEN** the configuration exposes the stored token
- **AND** the system reports Plex as configured

#### Scenario: Neither source supplies a token
- **WHEN** `PLEX_TOKEN` is unset and no token has been stored
- **THEN** the system reports Plex as not configured

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
- **WHEN** the stored Plex token is removed and no `PLEX_TOKEN` is set
- **THEN** the system presents the sign-in prompt rather than an error state

## ADDED Requirements

### Requirement: Sign in to Plex from the application
The system SHALL let an authenticated user obtain a Plex token by signing in to
Plex from within the application, using Plex's PIN authorization flow, so that
no token has to be supplied through the environment.

The flow SHALL open Plex's own sign-in page in a separate browser window and
poll for completion, rather than relying on a redirect back to Marquee. Marquee
therefore never needs to know its own externally reachable URL, and the flow
works unchanged behind a reverse proxy.

The system SHALL identify itself to Plex with a client identifier that is
generated once and persists across restarts, so that repeated sign-ins do not
accumulate duplicate device entries in the user's Plex account.

The resulting token SHALL be written outside the SQLite database with
owner-only permissions, and SHALL be readable by the scheduled auto-import
process, which runs without a browser session.

The system SHALL NOT copy an existing `PLEX_TOKEN` into the store on its own.
Storing a credential SHALL always be the result of a deliberate sign-in.

#### Scenario: Successful sign-in
- **WHEN** an authenticated user starts sign-in and approves Marquee in Plex
- **THEN** the system stores the returned token and reports Plex as connected

#### Scenario: Sign-in not completed
- **WHEN** the user closes the Plex window without approving, or the
  authorization request expires
- **THEN** the system stores no token and reports that sign-in did not complete
- **AND** any previously stored token is left untouched

#### Scenario: Scheduled import uses a stored token
- **WHEN** a token was obtained by signing in and no `PLEX_TOKEN` is set
- **THEN** the scheduled auto-import authenticates to Plex with the stored token

#### Scenario: Signing out
- **WHEN** an authenticated user signs out of Plex
- **THEN** the system removes the stored token
- **AND** Plex is reported as not connected unless `PLEX_TOKEN` supplies one

#### Scenario: An existing environment token is never stored automatically
- **WHEN** `PLEX_TOKEN` is set and the user has never signed in
- **THEN** no token is written to the store

### Requirement: The stored Plex token is never disclosed
The system SHALL NOT render a Plex token — from either source — into any page,
response body, or log entry. The connection is described by the name of the
connected server, never by the credential that reached it.

An in-progress sign-in SHALL be bound to the session that started it, so that
one browser session cannot complete or claim an authorization request begun by
another.

#### Scenario: Token absent from the connection panel
- **WHEN** any connection state renders
- **THEN** the page contains no Plex token

#### Scenario: Authorization request is not transferable
- **WHEN** a session polls an authorization request that a different session
  started
- **THEN** the system refuses it and stores no token

#### Scenario: Sign-in details stay out of the log
- **WHEN** a sign-in succeeds or fails
- **THEN** no log entry contains the token

### Requirement: Plex connection panel
The system SHALL present a connection panel that states whether Plex is
connected, names the connected server, and identifies which source supplied the
token. The panel SHALL replace the previous instruction to set environment
variables and restart.

Because `PLEX_TOKEN` takes precedence, the panel MUST distinguish a stored token
that is in use from one that is being overridden, so a user is never told they
are signed in while a different credential is actually serving requests.

The panel SHALL name the connected server using the friendly name reported by
the Plex server itself, and SHALL NOT display the Plex account identifier, which
is an email address. Where the name cannot be obtained the panel SHALL still
report the connection source.

The panel SHALL link to documentation comparing the two connection sources.

#### Scenario: Connected by signing in
- **WHEN** a stored token is in use
- **THEN** the panel names the connected server, states that the user is signed
  in, and offers to sign out

#### Scenario: Connected by environment variable
- **WHEN** `PLEX_TOKEN` supplies the token and no token is stored
- **THEN** the panel names the connected server, states that `PLEX_TOKEN` is in
  use, and offers to sign in to Plex

#### Scenario: Signed in but overridden
- **WHEN** a token is stored and `PLEX_TOKEN` is also set
- **THEN** the panel states that the stored sign-in is not in use because
  `PLEX_TOKEN` takes precedence
- **AND** the panel explains that removing the variable and restarting will
  activate the sign-in

#### Scenario: Not connected
- **WHEN** neither source supplies a token
- **THEN** the panel reports that Plex is not connected and offers to sign in

#### Scenario: Server name unavailable
- **WHEN** the connected server's name cannot be read
- **THEN** the panel still reports the connection source rather than failing

### Requirement: Plex connection status is visible app-wide
The system SHALL report the Plex connection — the connected server's name and
which source supplied the token — on every authenticated page, so the connection
is legible from the gallery where posters are sent to and fetched from Plex, and
not only on the import page.

The status SHALL be served from cached information and SHALL NOT contact Plex on
page render, so a slow or unreachable Plex server never delays a page. It
therefore reports the configured connection, and SHALL NOT claim that Plex is
currently reachable.

The status SHALL NOT appear on the poster wall, which runs unattended on a
display and carries no application chrome.

#### Scenario: Status shown alongside other application status
- **WHEN** an authenticated page renders
- **THEN** the connected server's name and the connection source appear with the
  application's other status information

#### Scenario: Status does not delay a page
- **WHEN** the Plex server is unreachable
- **THEN** pages render without waiting on Plex

#### Scenario: Wall carries no status
- **WHEN** the poster wall renders
- **THEN** no connection status appears

### Requirement: Plex failures name the applicable remedy
When a Plex request fails, the system SHALL describe the remedy that matches the
connection source in use. A user who signed in SHALL NOT be advised to check an
environment variable they have not set.

This applies wherever a Plex operation can fail, including sending a poster to
Plex, fetching one from it, importing, and detecting orphans.

#### Scenario: Rejected credential while signed in
- **WHEN** Plex rejects the credential and the token came from signing in
- **THEN** the message advises signing in to Plex again and offers a way to do so

#### Scenario: Rejected credential from the environment
- **WHEN** Plex rejects the credential and the token came from `PLEX_TOKEN`
- **THEN** the message advises checking `PLEX_TOKEN`

#### Scenario: Not connected at all
- **WHEN** a Plex operation is attempted while neither source supplies a token
- **THEN** the message reports that Marquee is not connected to Plex and points
  to the connection panel
