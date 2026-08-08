## ADDED Requirements

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

## MODIFIED Requirements

### Requirement: Sign in to Plex from the application
The system SHALL let a visitor sign in to Plex from within the application using
Plex's PIN authorization flow, and SHALL treat that sign-in as the way to log in
to Marquee. This is the only way to supply a Plex credential and the only way to
obtain a session. It SHALL be reachable without an existing session.

The flow SHALL open Plex's own sign-in page in a separate browser window and
poll for completion, rather than relying on a redirect back to Marquee. Marquee
therefore never needs to know its own externally reachable URL, and the flow
works unchanged behind a reverse proxy.

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

#### Scenario: Successful sign-in on a connected install
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

#### Scenario: Scheduled import uses the stored token
- **WHEN** a token was obtained by signing in
- **THEN** the scheduled auto-import authenticates to Plex with the stored token

#### Scenario: Disconnecting
- **WHEN** an authenticated user disconnects from Plex
- **THEN** the system removes the stored token and reports Plex as not connected

### Requirement: Plex connection screen
The system SHALL provide one screen that is both where a visitor logs in and
where the Plex connection is managed. It SHALL be reachable without a session,
SHALL be where both the authentication gate and the connection gate send a
visitor, and SHALL be the only place the Plex connection is managed; no other
page SHALL offer to change it.

The screen SHALL offer a single action to a visitor who is not signed in:
signing in to Plex. Because that action is both the login and the connection,
presenting them as two choices would ask the user to pick between two names for
one thing.

When connected and signed in, the screen SHALL name the connected server using
the friendly name reported by the Plex server itself, and SHALL NOT display the
Plex account identifier, which is an email address. Where the name cannot be
obtained the screen SHALL still report that Plex is connected.

The screen SHALL offer disconnecting only to a signed-in user, and SHALL state
what disconnecting costs — that Marquee stops working until someone signs in
again, and that scheduled imports stop with it. Naming the action alone does not
tell a user that.

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

#### Scenario: No way back while the gate is up
- **WHEN** the screen renders while Plex is not connected
- **THEN** it offers no link to the gallery, which the gate would refuse

#### Scenario: Disconnecting states its cost
- **WHEN** the screen offers to disconnect
- **THEN** it states that Marquee stops working until someone signs in again and
  that scheduled imports stop

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

### Requirement: A Plex connection is required to use the application
The system SHALL require a connected Plex server before any route that depends
on one may be used, redirecting to the connection screen until Plex is
connected.

Signing in is what satisfies both this gate and authentication, so a new
installation asks for one thing rather than two in sequence. Where a visitor has
neither a session nor a connection, both gates send them to the same screen, and
one sign-in clears both.

The connection screen itself, the routes that start and poll a sign-in, the
logout route, the health endpoint, the web app manifest, static assets, and the
Poster Wall SHALL remain reachable while Plex is not connected. The wall is
exempt because it is specified to run unattended without anyone signing in; a
gate in front of it would break that.

#### Scenario: Gallery is unreachable until Plex is connected
- **WHEN** an authenticated user requests the gallery while Plex is not
  connected
- **THEN** the system redirects to the connection screen

#### Scenario: One sign-in clears both gates
- **WHEN** a visitor with no session signs in to Plex on an install with no
  stored token
- **THEN** the previously gated routes are served normally
- **AND** the user is taken to the gallery with a confirmation, rather than left
  on the connection screen

#### Scenario: Both gates send a visitor to the same screen
- **WHEN** an unauthenticated visitor requests a gated route while Plex is not
  connected
- **THEN** the system sends them to the screen that offers signing in to Plex

#### Scenario: The wall runs without a Plex connection
- **WHEN** the poster wall is requested while Plex is not connected
- **THEN** the system serves it rather than redirecting

#### Scenario: Health stays reachable
- **WHEN** the health endpoint is requested while Plex is not connected
- **THEN** the system serves it

### Requirement: The Plex connection and the login read as different things
Signing in to Plex is now how Marquee is entered, so the interface SHALL describe
the way in using Plex's own words. The vocabulary rule applies to leaving, where
two genuinely different actions remain and a wrong guess costs the user
something.

The interface SHALL describe ending the user's Marquee session as logging out,
and forgetting the Plex connection as disconnecting, and SHALL NOT use one set of
words for the other. Each SHALL say what it leaves behind: logging out leaves
Plex connected and scheduled imports running; disconnecting stops both.

The interface SHALL NOT present logging out as revoking Marquee's access to Plex.
Once the way in is called signing in to Plex, that is the reading a user arrives
at unaided, and it is wrong — the stored token survives logging out deliberately,
so that unattended imports keep working.

#### Scenario: The way in uses Plex's words
- **WHEN** the screen offers the action that both logs the user in and connects
  Plex
- **THEN** it describes it as signing in to Plex

#### Scenario: The two exits keep their own words
- **WHEN** the interface offers to end the user's Marquee session and to forget
  the Plex connection
- **THEN** the first says log out and the second says disconnect

#### Scenario: Each exit says what it leaves behind
- **WHEN** either exit is offered
- **THEN** the interface states whether the Plex connection and scheduled imports
  survive it

#### Scenario: Logging out is not described as revoking Plex access
- **WHEN** logging out is offered or confirmed
- **THEN** the interface does not claim that it disconnects Plex or revokes
  Marquee's access to it
