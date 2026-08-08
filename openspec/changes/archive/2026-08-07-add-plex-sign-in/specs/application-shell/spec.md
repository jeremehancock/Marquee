## MODIFIED Requirements

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

## ADDED Requirements

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

The resulting token SHALL be written outside the SQLite database with
owner-only permissions, and SHALL be readable by the scheduled auto-import
process, which runs without a browser session.

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
sign in or sign out. It SHALL be the only place the Plex connection is managed;
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
Marquee can change the Plex connection. Bypass now exposes a stored credential
that can write to the user's Plex library, not merely a gallery of posters, and
the screen carrying the sign-in and sign-out actions is where that consequence
is concrete.

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
- **THEN** the screen warns that anyone who can reach Marquee can change the
  Plex connection

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
