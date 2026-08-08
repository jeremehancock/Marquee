# Authentication Specification

## Purpose

Marquee is single-user and self-hosted. Access is controlled by one username
and password supplied through the environment, held in a server-side session,
with an opt-in bypass for deployments on a trusted network (including kiosk use
of the Poster Wall).

This capability owns who may reach a route. What each route does is specified
by the capability that owns it.
## Requirements
### Requirement: Session-based login
The system SHALL authenticate users against a single username and password
supplied via environment variables and establish a server-side session on
success.

#### Scenario: Successful login
- **WHEN** a user submits credentials matching `AUTH_USERNAME` and
  `AUTH_PASSWORD`
- **THEN** the system creates an authenticated session and redirects to the
  home page

#### Scenario: Failed login
- **WHEN** a user submits credentials that do not match the configured values
- **THEN** the system re-renders the login page with an error and does not
  create a session

### Requirement: Protected routes require authentication
The system SHALL require a valid authenticated session for all routes except
the login route, the logout route, the health endpoint, the web app manifest,
and static assets.

#### Scenario: Unauthenticated access is redirected
- **WHEN** an unauthenticated user requests a protected route
- **THEN** the system redirects to the login page

#### Scenario: Health and assets remain public
- **WHEN** an unauthenticated user requests `/health` or a static asset
- **THEN** the system serves the response without requiring authentication

### Requirement: Session expiry
The system SHALL expire an authenticated session after the number of seconds
configured by `SESSION_DURATION`, after which access is treated as
unauthenticated.

#### Scenario: Expired session is rejected
- **WHEN** the time since a session was established exceeds `SESSION_DURATION`
- **THEN** the next request to a protected route is treated as unauthenticated
  and redirected to login

### Requirement: Logout
The system SHALL provide a logout action that destroys the current session. The
logout control SHALL be shown only when authentication is enabled, since there
is nothing to log out of when authentication is bypassed.

#### Scenario: User logs out
- **WHEN** an authenticated user triggers logout
- **THEN** the system destroys the session and redirects to the login page

#### Scenario: Auth enabled shows logout
- **WHEN** authentication is enabled
- **THEN** a logout link is shown

#### Scenario: Auth bypassed hides logout
- **WHEN** authentication is bypassed and any page renders
- **THEN** no logout link is shown

### Requirement: Authentication bypass
The system SHALL support an `AUTH_BYPASS` option that, when enabled, grants
access without login for deployments on a trusted network.

What that grants has changed with the Plex connection. Marquee performs every
Plex operation with the credential it stores, and no route distinguishes one
visitor from another, so bypassing authentication grants use of that credential:
sending artwork to the user's Plex library, changing and deleting posters, and
disconnecting the install. Restricting sign-in to the server's owner does not
narrow this — it guarantees the stored credential is a privileged one. Anything
describing this option to a user SHALL say so rather than implying that the
owner-only rule limits what a visitor can do.

#### Scenario: Bypass grants access
- **WHEN** `AUTH_BYPASS` is `true` and any route is requested
- **THEN** the system treats the request as authenticated without presenting the
  login page

#### Scenario: Bypass disabled enforces login
- **WHEN** `AUTH_BYPASS` is `false` or unset and an unauthenticated user
  requests a protected route
- **THEN** the system redirects to the login page

### Requirement: The session cookie's attributes are Marquee's decision
The system SHALL set the session cookie's attributes itself before the session
is started, rather than inheriting whatever the runtime's configuration
supplies. The cookie SHALL be marked `HttpOnly` and SHALL carry
`SameSite=Lax`.

`SameSite=Lax` is what stops another site driving a state-changing request with
the user's session attached. It is set here rather than left to the runtime
because a default is not a decision: an image rebuild, a base-image change, or a
different `php.ini` would silently remove it, and nothing would fail.

The system SHALL NOT set the `Secure` attribute. Marquee is routinely reached
over plain HTTP on a local network, and a `Secure` cookie is never sent over
HTTP, so setting it unconditionally would prevent logging in at all on those
installs. Withholding it is a deliberate trade, not an oversight.

#### Scenario: The cookie is not readable from script
- **WHEN** the session cookie is issued
- **THEN** it carries the `HttpOnly` attribute

#### Scenario: The cookie is withheld from cross-site requests
- **WHEN** the session cookie is issued
- **THEN** it carries `SameSite=Lax`

#### Scenario: Plain HTTP installs can still log in
- **WHEN** Marquee is reached over plain HTTP
- **THEN** the session cookie is sent with subsequent requests, because it is
  not marked `Secure`

### Requirement: Logging in issues a new session identifier
The system SHALL replace the session identifier on a successful login, carrying
the session's contents across, and SHALL discard the identifier that was in use
before. It SHALL also refuse to adopt a session identifier it did not itself
issue.

Together these close session fixation. Regeneration means an identifier known to
somebody else before the user logged in is worthless afterwards; refusing an
unissued identifier means one cannot be planted to begin with. Either alone
leaves a gap, so both are required.

A failed login SHALL NOT regenerate the identifier: nothing has been granted, so
there is nothing to protect, and rotating on failure would let an unauthenticated
caller churn session identifiers at will.

#### Scenario: A successful login replaces the identifier
- **WHEN** a user submits credentials matching `AUTH_USERNAME` and
  `AUTH_PASSWORD`
- **THEN** the session identifier in use afterwards differs from the one the
  request arrived with
- **AND** the session is authenticated

#### Scenario: Session contents survive the replacement
- **WHEN** the session identifier is replaced on login
- **THEN** values stored in the session before the login are still readable
  afterwards

#### Scenario: A failed login does not replace the identifier
- **WHEN** a user submits credentials that do not match the configured values
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

Protection SHALL apply while `AUTH_BYPASS` is enabled. Bypass removes the login,
which makes a forged request more valuable rather than less.

This exists because `SameSite=Lax` does not cover the deployment Marquee is
built for. "Site" ignores the port, so every other service on the same host
address is same-site with Marquee and its requests still carry the session
cookie. A self-hosted machine runs many.

Logging in SHALL be the exception to how a refusal is presented. A login whose
token does not match SHALL re-render the login page with an explanation and no
authenticated session, rather than an error page. It is the one route reachable
with no session behind it, so it is the one a user meets after their session has
gone — a container recreation discards them all — and an error page there reads
as a broken installation rather than as a page to submit again. The request is
still refused and still authenticates nobody.

#### Scenario: A state-changing request without a token is refused
- **WHEN** an authenticated session sends a `POST` carrying no token
- **THEN** the system refuses the request with HTTP 403
- **AND** the action is not performed

#### Scenario: A login with a stale token is refused but explained
- **WHEN** a login is submitted with a token that does not match the session's
- **THEN** the system re-renders the login page with an explanation that the
  page expired and should be submitted again
- **AND** no authenticated session is created
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

#### Scenario: Bypass does not disable the check
- **WHEN** `AUTH_BYPASS` is enabled and a `POST` arrives without a token
- **THEN** the system refuses it

### Requirement: Marquee's own pages carry the token
The system SHALL include the token in every form it renders that performs a
state-changing request, and SHALL make it available to its own scripts, so that
using Marquee never requires the user to supply it.

The token SHALL be accepted either as a form field or as a request header.
Forms submit a field; scripted requests that build their own body have no form
to carry one, and a header serves both without a second mechanism.

#### Scenario: A rendered form carries the token
- **WHEN** a page containing a state-changing form is rendered
- **THEN** the form includes the session's token as a field

#### Scenario: A scripted request carries the token
- **WHEN** the application's own script sends a state-changing request
- **THEN** the request carries the session's token as a header

#### Scenario: Either carrier is accepted
- **WHEN** a valid token arrives as a form field, or as a header
- **THEN** the system accepts the request in both cases

#### Scenario: The token is not placed in a URL
- **WHEN** any page or request carries the token
- **THEN** it is not present in the query string of any URL

