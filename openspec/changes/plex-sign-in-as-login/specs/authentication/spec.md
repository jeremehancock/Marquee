## MODIFIED Requirements

### Requirement: Session-based login
The system SHALL establish an authenticated server-side session only by a
successful Plex sign-in, and SHALL accept no other credential. There is no
username, no password, and no way to authenticate without Plex.

The system SHALL grant a session only to the Plex account that owns the
configured server, and SHALL refuse every other account. This is the same rule
that governs connecting Marquee to Plex; it now governs access as well, so that
the app's access rule and its authority are the same thing.

plex.tv SHALL be consulted only while logging in. Once a session exists, no
request SHALL depend on plex.tv being reachable, because nothing Marquee does
after login involves it — the Plex server address is configured, and every
operation goes to that server directly.

#### Scenario: The owner signs in
- **WHEN** a visitor completes a Plex sign-in and the approving account owns the
  configured server
- **THEN** the system creates an authenticated session and takes the visitor
  into the application

#### Scenario: An account that does not own the server is refused
- **WHEN** a visitor completes a Plex sign-in and the approving account does not
  own the configured server
- **THEN** the system creates no session
- **AND** any previously stored Plex token is left untouched

#### Scenario: No credential authenticates
- **WHEN** a request supplies a username and password by any means
- **THEN** the system creates no session

#### Scenario: A session does not depend on plex.tv
- **WHEN** an authenticated session makes a request while plex.tv cannot be
  reached
- **THEN** the request is served normally

### Requirement: Protected routes require authentication
The system SHALL require a valid authenticated session for all routes except the
login route, the routes that start and poll a Plex sign-in, the logout route, the
health endpoint, the web app manifest, and static assets.

The routes that start and poll a sign-in are reachable without a session because
they are how a session is obtained. They SHALL NOT disclose anything about the
install to an unauthenticated caller beyond whether an authorization request has
completed.

#### Scenario: Unauthenticated access is redirected
- **WHEN** an unauthenticated user requests a protected route
- **THEN** the system redirects to the login page

#### Scenario: Health and assets remain public
- **WHEN** an unauthenticated user requests `/health` or a static asset
- **THEN** the system serves the response without requiring authentication

#### Scenario: Signing in is reachable without a session
- **WHEN** an unauthenticated visitor starts or polls a Plex sign-in
- **THEN** the system serves the request rather than redirecting to login

#### Scenario: Polling discloses nothing but the outcome
- **WHEN** an unauthenticated visitor polls a sign-in
- **THEN** the response reports only the status of that authorization request

### Requirement: Session expiry
The system SHALL expire an authenticated session after the number of seconds
configured by `SESSION_DURATION`, after which access is treated as
unauthenticated. `SESSION_DURATION` SHALL default to 30 days.

The window SHALL be renewed by use rather than fixed at login: each request from
an authenticated session extends its expiry by the configured duration. An
absolute deadline stamped at login would eject a user in the middle of using
Marquee, which is the opposite of trusting the application's own session.

The default is long because it is the only tolerance left. With no second
credential, the time between logins is the entire window in which a plex.tv
outage cannot affect the user.

#### Scenario: An idle session expires
- **WHEN** the time since a session's last request exceeds `SESSION_DURATION`
- **THEN** the next request to a protected route is treated as unauthenticated
  and redirected to login

#### Scenario: Use renews the window
- **WHEN** an authenticated session makes a request before its window elapses
- **THEN** its expiry is extended by `SESSION_DURATION` from that request

#### Scenario: A session outlives its login by more than the configured duration
- **WHEN** a session is used regularly for longer than `SESSION_DURATION` since
  it was created
- **THEN** it remains authenticated

### Requirement: Logout
The system SHALL provide a logout action that destroys the current session and
returns the user to the login screen. A logout control SHALL be shown to every
authenticated user.

Logging out SHALL NOT clear the stored Plex token, disconnect the install, or
affect any other session. It ends this browser's session and nothing else.

This matters beyond tidiness. The scheduled auto-import runs as a separate
process with no session and authenticates with the stored token; clearing that
token on logout would stop scheduled imports at the next run, silently and with
nothing in the interface to report it. Disconnecting remains the only action
that forgets the token.

#### Scenario: User logs out
- **WHEN** an authenticated user triggers logout
- **THEN** the system destroys the session and redirects to the login screen

#### Scenario: The logout control is always available when signed in
- **WHEN** an authenticated user views any page
- **THEN** a logout control is shown

#### Scenario: Logging out leaves Plex connected
- **WHEN** an authenticated user logs out
- **THEN** the stored Plex token is unchanged
- **AND** the install still reports Plex as connected

#### Scenario: A scheduled import still runs after logout
- **WHEN** a scheduled auto-import runs after the user has logged out
- **THEN** it authenticates to Plex with the stored token and completes normally

### Requirement: Logging in issues a new session identifier
The system SHALL replace the session identifier when a login succeeds, carrying
the session's contents across, and SHALL discard the identifier that was in use
before. It SHALL also refuse to adopt a session identifier it did not itself
issue.

Together these close session fixation. Regeneration means an identifier known to
somebody else before the user logged in is worthless afterwards; refusing an
unissued identifier means one cannot be planted to begin with. Either alone
leaves a gap, so both are required.

A refused sign-in SHALL NOT regenerate the identifier: nothing has been granted,
so there is nothing to protect, and rotating on refusal would let an
unauthenticated caller churn session identifiers at will.

#### Scenario: A successful login replaces the identifier
- **WHEN** a Plex sign-in by the server's owner completes
- **THEN** the session identifier in use afterwards differs from the one the
  request arrived with
- **AND** the session is authenticated

#### Scenario: Session contents survive the replacement
- **WHEN** the session identifier is replaced on login
- **THEN** values stored in the session before the login are still readable
  afterwards

#### Scenario: A refused sign-in does not replace the identifier
- **WHEN** a sign-in is refused because the account does not own the server
- **THEN** the session identifier is unchanged
- **AND** no authenticated session is created

#### Scenario: An identifier the system did not issue is refused
- **WHEN** a request arrives carrying a session identifier that the system never
  issued
- **THEN** the system does not adopt it as a valid session

### Requirement: State-changing requests must originate from Marquee
The system SHALL refuse any state-changing request that does not carry a secret
token bound to the requesting session, and SHALL do so before the request
reaches the handler that would act on it. A refused request SHALL change
nothing.

The token SHALL be generated once per session, held server-side, and compared in
a way that does not leak its value through timing. It SHALL NOT appear in a URL,
because URLs are logged, shared, and sent as referrers.

Requests that only read SHALL NOT require a token. `GET`, `HEAD`, and `OPTIONS`
are unaffected, so the poster wall, the health endpoint, and ordinary page loads
behave exactly as before.

This exists because `SameSite=Lax` does not cover the deployment Marquee is
built for. "Site" ignores the port, so every other service on the same host
address is same-site with Marquee and its requests still carry the session
cookie. A self-hosted machine runs many.

Starting a sign-in SHALL be the exception to how a refusal is presented. A
sign-in whose token does not match SHALL be refused with an explanation the
screen can show the user, rather than an error page, and SHALL start no
authorization request. It is the one state-changing route reachable with no
session behind it, so it is the one a user meets after their session has gone — a
container recreation discards them all — and it is the one route they cannot get
past it on, because it is the way in. The explanation SHALL be carried in the
form the caller can read, so that it reaches the user rather than failing to
parse. The request is still refused and still authenticates nobody.

#### Scenario: A state-changing request without a token is refused
- **WHEN** an authenticated session sends a `POST` carrying no token
- **THEN** the system refuses the request with HTTP 403
- **AND** the action is not performed

#### Scenario: A sign-in with a stale token is refused but explained
- **WHEN** a sign-in is started with a token that does not match the session's
- **THEN** the system refuses it with an explanation that the page expired and
  should be tried again
- **AND** the explanation is carried in the form the caller reads, so the screen
  can show it
- **AND** no authorization request is started
- **AND** the response is not an error page

#### Scenario: A state-changing request with the wrong token is refused
- **WHEN** an authenticated session sends a `POST` carrying a token that does
  not match the one held for that session
- **THEN** the system refuses the request with HTTP 403
- **AND** the action is not performed

#### Scenario: One session's token does not work for another
- **WHEN** a request carries a token that is valid for a different session
- **THEN** the system refuses it

#### Scenario: A state-changing request with the right token proceeds
- **WHEN** an authenticated session sends a `POST` carrying the token held for
  that session
- **THEN** the system handles the request normally

#### Scenario: Reading is unaffected
- **WHEN** a `GET` request is made to any route
- **THEN** no token is required

## REMOVED Requirements

### Requirement: Authentication bypass
**Reason**: With the environment credential gone, `AUTH_BYPASS` would be the only
remaining way into Marquee that is not a Plex sign-in — the same second path as a
fallback password, with no credential at all. Keeping it would remove the
stronger door and retain the weaker one, and would leave an environment variable
that silently makes the application's one access rule untrue. The connection
screen already had to warn that bypass grants a visitor the use of the stored
Plex credential: changing and deleting posters, altering the user's Plex library,
and disconnecting the install.

**Migration**: Remove `AUTH_BYPASS` from the environment; it is read only to
report that it is obsolete and never grants access. Deployments that used it to
delegate authentication to a reverse proxy (Authelia, Tailscale, Cloudflare
Access) must now complete a Plex sign-in in Marquee as well; with a 30-day
sliding session this is required roughly monthly. Unattended displays are
unaffected: the Poster Wall is specified to run without anyone signing in and
remains reachable without a session.
